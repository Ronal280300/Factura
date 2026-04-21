<?php

require_once __DIR__ . '/Db.php';

/**
 * Calcula todos los renglones del formulario D-150 (declaracion mensual de IVA)
 * que opera en TRIBU-CR desde agosto 2025.
 *
 * Reglas clave:
 *  - VENTAS (direction='issued'):
 *      FE/FEE/FEC/TE suman; NC resta; ND suma. REP se excluye (solo mueve el
 *      momento del IVA cobrado, no es un hecho generador nuevo — TRIBU-CR
 *      lo reconcilia desde la FE original).
 *  - COMPRAS (direction='received'):
 *      Solo FE/FEE/FEC con MensajeReceptor aceptado dan credito fiscal.
 *      NC recibida resta. ND recibida suma. TE nunca da credito. REP tampoco.
 *  - PRORRATEO:
 *      Si hay ventas exentas sin derecho, el credito se deduce × ratio.
 *      Ratio provisional se toma de prorrateo_anual del ano; si no hay,
 *      se usa 1.0.
 *
 * Todos los montos se manejan en CRC (columnas *_crc, con fallback a la
 * moneda original si no hay conversion).
 */
class D150Calculator {

  /** Retorna un array completo con la estructura del D-150 para [from, to]. */
  public static function compute(PDO $pdo, string $from, string $to, bool $requireMrAccepted = true): array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
      throw new InvalidArgumentException('Fechas invalidas');
    }

    $fromFull = $from . ' 00:00:00';
    $toFull   = $to   . ' 23:59:59';
    $year     = (int)substr($from, 0, 4);

    // Ratio de prorrateo del ano
    $pr = $pdo->prepare("SELECT ratio_provisional, ratio_definitivo FROM prorrateo_anual WHERE anio = ?");
    $pr->execute([$year]);
    $prRow = $pr->fetch();
    $ratio = $prRow
      ? (float)($prRow['ratio_definitivo'] ?? $prRow['ratio_provisional'])
      : 1.0;

    $ventas  = self::sumPeriod($pdo, $fromFull, $toFull, 'issued',   false);
    $comprasCondition = $requireMrAccepted
      ? "AND EXISTS (SELECT 1 FROM receptor_messages rm WHERE rm.invoice_id = i.id AND rm.estado_hacienda = 'aceptado' AND rm.mensaje IN ('1','2'))"
      : "";
    $compras = self::sumPeriod($pdo, $fromFull, $toFull, 'received', true, $comprasCondition);

    // Debito fiscal = suma del IVA neto de ventas gravadas
    $debitoTotal = 0.0;
    foreach ($ventas['por_tarifa'] as $t) $debitoTotal += $t['impuesto'];

    // Credito fiscal bruto = suma del IVA neto de compras aceptadas
    $creditoBruto = 0.0;
    foreach ($compras['por_tarifa'] as $t) $creditoBruto += $t['impuesto'];

    $creditoAplicable = round($creditoBruto * $ratio, 2);
    $saldo            = round($debitoTotal - $creditoAplicable, 2);

    return [
      'periodo' => [
        'from' => $from,
        'to'   => $to,
        'anio' => $year,
      ],
      'ventas'  => $ventas,
      'compras' => $compras,
      'prorrateo' => [
        'ratio'       => round($ratio, 6),
        'es_definitivo' => (bool)($prRow['ratio_definitivo'] ?? false),
        'anio'        => $year,
      ],
      'liquidacion' => [
        'debito_fiscal'      => round($debitoTotal, 2),
        'credito_fiscal_bruto'=> round($creditoBruto, 2),
        'credito_fiscal_aplicable' => $creditoAplicable,
        'saldo'              => $saldo,               // >0 = a pagar, <0 = a favor
        'a_pagar'            => $saldo > 0 ? $saldo : 0.0,
        'a_favor'            => $saldo < 0 ? abs($saldo) : 0.0,
      ],
    ];
  }

  /**
   * Agrega compras o ventas del periodo:
   *  - Totales por tarifa (base, impuesto, con signo segun NC/ND)
   *  - Totales gravadas, exentas, no sujetas, exoneradas
   *  - Lista de facturas incluidas
   */
  private static function sumPeriod(PDO $pdo, string $from, string $to, string $direction, bool $asPurchases, string $extraCondition = ''): array {
    // Signo: NC resta, resto suma. TE/REP se excluyen del agregado.
    $allowedTypes = "('FE','FEE','FEC','NC','ND')";
    $signExpr = "CASE WHEN i.tipo_documento = 'NC' THEN -1 ELSE 1 END";

    $sql = "
      SELECT
        i.id, i.clave, i.tipo_documento, i.fecha_emision,
        {$signExpr} AS signo,
        i.emisor_nombre, i.emisor_identificacion,
        i.receptor_nombre, i.receptor_identificacion,
        COALESCE(i.total_gravado_crc, i.total_gravado, 0)      AS total_gravado,
        COALESCE(i.total_exento_crc, i.total_exento, 0)        AS total_exento,
        COALESCE(i.total_exonerado_crc, i.total_exonerado, 0)  AS total_exonerado,
        COALESCE(i.total_impuesto_crc, i.total_impuesto, 0)    AS total_impuesto,
        COALESCE(i.total_comprobante_crc, i.total_comprobante, 0) AS total_comprobante,

        COALESCE(SUM(CASE WHEN b.tarifa =  1.00 THEN COALESCE(b.base_crc, b.base)                  END), 0) AS base_1,
        COALESCE(SUM(CASE WHEN b.tarifa =  1.00 THEN COALESCE(b.impuesto_neto_crc, b.impuesto_neto)END), 0) AS imp_1,
        COALESCE(SUM(CASE WHEN b.tarifa =  2.00 THEN COALESCE(b.base_crc, b.base)                  END), 0) AS base_2,
        COALESCE(SUM(CASE WHEN b.tarifa =  2.00 THEN COALESCE(b.impuesto_neto_crc, b.impuesto_neto)END), 0) AS imp_2,
        COALESCE(SUM(CASE WHEN b.tarifa =  4.00 THEN COALESCE(b.base_crc, b.base)                  END), 0) AS base_4,
        COALESCE(SUM(CASE WHEN b.tarifa =  4.00 THEN COALESCE(b.impuesto_neto_crc, b.impuesto_neto)END), 0) AS imp_4,
        COALESCE(SUM(CASE WHEN b.tarifa = 10.00 THEN COALESCE(b.base_crc, b.base)                  END), 0) AS base_10,
        COALESCE(SUM(CASE WHEN b.tarifa = 10.00 THEN COALESCE(b.impuesto_neto_crc, b.impuesto_neto)END), 0) AS imp_10,
        COALESCE(SUM(CASE WHEN b.tarifa = 13.00 THEN COALESCE(b.base_crc, b.base)                  END), 0) AS base_13,
        COALESCE(SUM(CASE WHEN b.tarifa = 13.00 THEN COALESCE(b.impuesto_neto_crc, b.impuesto_neto)END), 0) AS imp_13,
        COALESCE(SUM(CASE WHEN b.tarifa =  0.00 THEN COALESCE(b.base_crc, b.base)                  END), 0) AS base_0
      FROM invoices i
      LEFT JOIN invoice_tax_breakdown b ON b.invoice_id = i.id
      WHERE i.direction = ?
        AND i.fecha_emision >= ? AND i.fecha_emision <= ?
        AND i.tipo_documento IN {$allowedTypes}
        {$extraCondition}
      GROUP BY i.id
      ORDER BY i.fecha_emision ASC
    ";

    $st = $pdo->prepare($sql);
    $st->execute([$direction, $from, $to]);
    $rows = $st->fetchAll();

    $totBase    = ['1'=>0,'2'=>0,'4'=>0,'10'=>0,'13'=>0];
    $totImp     = ['1'=>0,'2'=>0,'4'=>0,'10'=>0,'13'=>0];
    $totExento  = 0.0;
    $totExonerado = 0.0;
    $totNoSujeto = 0.0;    // tarifa 0
    $totComprobante = 0.0;

    $detail = [];
    foreach ($rows as $r) {
      $s = (int)$r['signo'];
      foreach (['1','2','4','10','13'] as $k) {
        $totBase[$k] += $s * (float)$r["base_{$k}"];
        $totImp[$k]  += $s * (float)$r["imp_{$k}"];
      }
      $totNoSujeto   += $s * (float)$r['base_0'];
      $totExento     += $s * (float)$r['total_exento'];
      $totExonerado  += $s * (float)$r['total_exonerado'];
      $totComprobante+= $s * (float)$r['total_comprobante'];

      $detail[] = [
        'id'           => (int)$r['id'],
        'clave'        => $r['clave'],
        'tipo'         => $r['tipo_documento'],
        'signo'        => $s,
        'fecha'        => $r['fecha_emision'],
        'contraparte'  => $asPurchases
          ? ($r['emisor_nombre'] . ' (' . $r['emisor_identificacion'] . ')')
          : ($r['receptor_nombre'] . ' (' . $r['receptor_identificacion'] . ')'),
        'total'        => round($s * (float)$r['total_comprobante'], 2),
        'iva'          => round($s * ((float)$r['imp_1'] + (float)$r['imp_2'] + (float)$r['imp_4'] + (float)$r['imp_10'] + (float)$r['imp_13']), 2),
      ];
    }

    $porTarifa = [];
    foreach (['1','2','4','10','13'] as $k) {
      $porTarifa[$k] = [
        'tarifa'   => (float)$k,
        'base'     => round($totBase[$k], 2),
        'impuesto' => round($totImp[$k], 2),
      ];
    }

    $baseGravada = array_sum(array_column($porTarifa, 'base'));

    return [
      'por_tarifa'        => $porTarifa,
      'base_gravada_total'=> round($baseGravada, 2),
      'exento'            => round($totExento, 2),
      'exonerado'         => round($totExonerado, 2),
      'no_sujeto'         => round($totNoSujeto, 2),
      'total_comprobantes'=> round($totComprobante, 2),
      'count'             => count($rows),
      'detalle'           => $detail,
    ];
  }
}
