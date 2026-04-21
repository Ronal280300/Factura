<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require __DIR__ . '/../app/Db.php';

function jsonOut(array $data): void {
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $from  = $input['from'] ?? null;
    $to    = $input['to']   ?? null;

    if (!$from || !$to) throw new Exception('Fechas requeridas.');

    $fromDt = DateTime::createFromFormat('Y-m-d', $from);
    $toDt   = DateTime::createFromFormat('Y-m-d', $to);
    if (!$fromDt || !$toDt) throw new Exception('Formato de fecha inválido.');

    $pdo = Db::pdo();

    // ── Reglas de signo y filtro por tipo de documento ─────────────────
    // FE, FEE, FEC, ND        → sumar (+1)  [gasto normal]
    // NC                      → restar (-1) [nota de credito: disminuye el gasto]
    // TE                      → excluir     [solo consumidor final, no da credito]
    // REP                     → excluir     [cambia solo momento de IVA, no credito nuevo]
    //
    // Usamos las columnas *_crc del breakdown para trabajar todo en colones.
    $sql = "
        SELECT
            i.id,
            i.tipo_documento,
            CASE i.tipo_documento
                WHEN 'NC' THEN -1
                ELSE  1
            END                                                               AS signo,
            DATE(i.fecha_emision)                                             AS fecha,
            MONTH(i.fecha_emision)                                            AS mes_num,
            COALESCE(NULLIF(i.emisor_nombre_comercial,''), i.emisor_nombre)   AS emisor,
            i.emisor_identificacion                                           AS cedula,
            i.clave,
            i.clave_referencia,
            COALESCE(i.moneda,'CRC')                                          AS moneda,
            COALESCE(i.total_comprobante_crc, i.total_comprobante, 0)         AS total_comprobante,

            -- ── BIENES (en CRC, neto de exoneracion) ────────────────
            COALESCE(SUM(CASE WHEN b.tipo_gasto='bien' AND b.tarifa =  1.00 THEN COALESCE(b.base_crc,b.base)                   ELSE 0 END),0) AS bien_base_1,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='bien' AND b.tarifa =  1.00 THEN COALESCE(b.impuesto_neto_crc,b.impuesto_neto) ELSE 0 END),0) AS bien_iva_1,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='bien' AND b.tarifa =  2.00 THEN COALESCE(b.base_crc,b.base)                   ELSE 0 END),0) AS bien_base_2,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='bien' AND b.tarifa =  2.00 THEN COALESCE(b.impuesto_neto_crc,b.impuesto_neto) ELSE 0 END),0) AS bien_iva_2,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='bien' AND b.tarifa =  4.00 THEN COALESCE(b.base_crc,b.base)                   ELSE 0 END),0) AS bien_base_4,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='bien' AND b.tarifa =  4.00 THEN COALESCE(b.impuesto_neto_crc,b.impuesto_neto) ELSE 0 END),0) AS bien_iva_4,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='bien' AND b.tarifa = 13.00 THEN COALESCE(b.base_crc,b.base)                   ELSE 0 END),0) AS bien_base_13,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='bien' AND b.tarifa = 13.00 THEN COALESCE(b.impuesto_neto_crc,b.impuesto_neto) ELSE 0 END),0) AS bien_iva_13,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='bien' AND b.tarifa =  0.00 THEN COALESCE(b.base_crc,b.base)                   ELSE 0 END),0) AS bien_no_sujeto,

            -- ── SERVICIOS ───────────────────────────────────────────
            COALESCE(SUM(CASE WHEN b.tipo_gasto='servicio' AND b.tarifa =  1.00 THEN COALESCE(b.base_crc,b.base)                   ELSE 0 END),0) AS srv_base_1,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='servicio' AND b.tarifa =  1.00 THEN COALESCE(b.impuesto_neto_crc,b.impuesto_neto) ELSE 0 END),0) AS srv_iva_1,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='servicio' AND b.tarifa =  4.00 THEN COALESCE(b.base_crc,b.base)                   ELSE 0 END),0) AS srv_base_4,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='servicio' AND b.tarifa =  4.00 THEN COALESCE(b.impuesto_neto_crc,b.impuesto_neto) ELSE 0 END),0) AS srv_iva_4,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='servicio' AND b.tarifa = 10.00 THEN COALESCE(b.base_crc,b.base)                   ELSE 0 END),0) AS srv_base_10,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='servicio' AND b.tarifa = 10.00 THEN COALESCE(b.impuesto_neto_crc,b.impuesto_neto) ELSE 0 END),0) AS srv_iva_10,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='servicio' AND b.tarifa = 13.00 THEN COALESCE(b.base_crc,b.base)                   ELSE 0 END),0) AS srv_base_13,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='servicio' AND b.tarifa = 13.00 THEN COALESCE(b.impuesto_neto_crc,b.impuesto_neto) ELSE 0 END),0) AS srv_iva_13,
            COALESCE(SUM(CASE WHEN b.tipo_gasto='servicio' AND b.tarifa =  0.00 THEN COALESCE(b.base_crc,b.base)                   ELSE 0 END),0) AS srv_no_sujeto

        FROM invoices i
        LEFT JOIN invoice_tax_breakdown b ON b.invoice_id = i.id
        WHERE i.fecha_emision >= :from AND i.fecha_emision <= :to
          AND i.tipo_documento IN ('FE','FEE','FEC','NC','ND')
        GROUP BY i.id
        ORDER BY i.fecha_emision ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':from' => $from . ' 00:00:00',
        ':to'   => $to   . ' 23:59:59',
    ]);
    $allRows = $stmt->fetchAll();

    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    $bienTotals = ['iva_1'=>0,'iva_2'=>0,'iva_4'=>0,'iva_13'=>0,'no_sujeto'=>0,'base_gravada'=>0];
    $srvTotals  = ['iva_1'=>0,'iva_4'=>0,'iva_10'=>0,'iva_13'=>0,'no_sujeto'=>0,'base_gravada'=>0];

    $bienesRows    = [];
    $serviciosRows = [];

    foreach ($allRows as $row) {
        $signo = (int)$row['signo'];                 // +1 o -1 segun tipo_documento
        $mes   = $meses[(int)$row['mes_num']] ?? '';
        $tipo  = $row['tipo_documento'];

        $bienIva1  = $signo * (float)$row['bien_iva_1'];
        $bienIva2  = $signo * (float)$row['bien_iva_2'];
        $bienIva4  = $signo * (float)$row['bien_iva_4'];
        $bienIva13 = $signo * (float)$row['bien_iva_13'];
        $bienBase1  = $signo * (float)$row['bien_base_1'];
        $bienBase2  = $signo * (float)$row['bien_base_2'];
        $bienBase4  = $signo * (float)$row['bien_base_4'];
        $bienBase13 = $signo * (float)$row['bien_base_13'];
        $bienNoSuj = $signo * (float)$row['bien_no_sujeto'];
        $bienIvaTotal  = $bienIva1 + $bienIva2 + $bienIva4 + $bienIva13;
        $bienBaseTotal = $bienBase1 + $bienBase2 + $bienBase4 + $bienBase13;

        $srvIva1  = $signo * (float)$row['srv_iva_1'];
        $srvIva4  = $signo * (float)$row['srv_iva_4'];
        $srvIva10 = $signo * (float)$row['srv_iva_10'];
        $srvIva13 = $signo * (float)$row['srv_iva_13'];
        $srvBase1  = $signo * (float)$row['srv_base_1'];
        $srvBase4  = $signo * (float)$row['srv_base_4'];
        $srvBase10 = $signo * (float)$row['srv_base_10'];
        $srvBase13 = $signo * (float)$row['srv_base_13'];
        $srvNoSuj = $signo * (float)$row['srv_no_sujeto'];
        $srvIvaTotal  = $srvIva1 + $srvIva4 + $srvIva10 + $srvIva13;
        $srvBaseTotal = $srvBase1 + $srvBase4 + $srvBase10 + $srvBase13;

        if (abs($bienBaseTotal) > 0.005 || abs($bienIvaTotal) > 0.005 || abs($bienNoSuj) > 0.005) {
            $bienesRows[] = [
                'tipo_doc'     => $tipo,
                'fecha'        => $row['fecha'],
                'mes'          => $mes,
                'emisor'       => $row['emisor'],
                'cedula'       => $row['cedula'],
                'moneda'       => $row['moneda'],
                'base_1'       => round($bienBase1,  2),
                'iva_1'        => round($bienIva1,   2),
                'base_2'       => round($bienBase2,  2),
                'iva_2'        => round($bienIva2,   2),
                'base_4'       => round($bienBase4,  2),
                'iva_4'        => round($bienIva4,   2),
                'base_13'      => round($bienBase13, 2),
                'iva_13'       => round($bienIva13,  2),
                'no_sujeto'    => round($bienNoSuj,  2),
                'iva_total'    => round($bienIvaTotal, 2),
                'total_comprobante' => round($signo * (float)$row['total_comprobante'], 2),
            ];
        }

        if (abs($srvBaseTotal) > 0.005 || abs($srvIvaTotal) > 0.005 || abs($srvNoSuj) > 0.005) {
            $serviciosRows[] = [
                'tipo_doc'     => $tipo,
                'fecha'        => $row['fecha'],
                'mes'          => $mes,
                'emisor'       => $row['emisor'],
                'cedula'       => $row['cedula'],
                'moneda'       => $row['moneda'],
                'base_1'       => round($srvBase1,  2),
                'iva_1'        => round($srvIva1,   2),
                'base_4'       => round($srvBase4,  2),
                'iva_4'        => round($srvIva4,   2),
                'base_10'      => round($srvBase10, 2),
                'iva_10'       => round($srvIva10,  2),
                'base_13'      => round($srvBase13, 2),
                'iva_13'       => round($srvIva13,  2),
                'no_sujeto'    => round($srvNoSuj,  2),
                'iva_total'    => round($srvIvaTotal, 2),
                'total_comprobante' => round($signo * (float)$row['total_comprobante'], 2),
            ];
        }

        $bienTotals['iva_1']        += $bienIva1;
        $bienTotals['iva_2']        += $bienIva2;
        $bienTotals['iva_4']        += $bienIva4;
        $bienTotals['iva_13']       += $bienIva13;
        $bienTotals['no_sujeto']    += $bienNoSuj;
        $bienTotals['base_gravada'] += $bienBaseTotal;

        $srvTotals['iva_1']        += $srvIva1;
        $srvTotals['iva_4']        += $srvIva4;
        $srvTotals['iva_10']       += $srvIva10;
        $srvTotals['iva_13']       += $srvIva13;
        $srvTotals['no_sujeto']    += $srvNoSuj;
        $srvTotals['base_gravada'] += $srvBaseTotal;
    }

    foreach ($bienTotals as $k => $v) $bienTotals[$k] = round($v, 2);
    foreach ($srvTotals  as $k => $v) $srvTotals[$k]  = round($v, 2);

    $bienTotals['iva_total']     = round($bienTotals['iva_1'] + $bienTotals['iva_2'] + $bienTotals['iva_4'] + $bienTotals['iva_13'], 2);
    $bienTotals['proporcion_1']  = $bienTotals['iva_1']  > 0 ? round($bienTotals['iva_1']  / 0.01, 2) : 0;
    $bienTotals['proporcion_2']  = $bienTotals['iva_2']  > 0 ? round($bienTotals['iva_2']  / 0.02, 2) : 0;
    $bienTotals['proporcion_4']  = $bienTotals['iva_4']  > 0 ? round($bienTotals['iva_4']  / 0.04, 2) : 0;
    $bienTotals['proporcion_13'] = $bienTotals['iva_13'] > 0 ? round($bienTotals['iva_13'] / 0.13, 2) : 0;

    $srvTotals['iva_total']      = round($srvTotals['iva_1'] + $srvTotals['iva_4'] + $srvTotals['iva_10'] + $srvTotals['iva_13'], 2);
    $srvTotals['proporcion_1']   = $srvTotals['iva_1']  > 0 ? round($srvTotals['iva_1']  / 0.01, 2) : 0;
    $srvTotals['proporcion_4']   = $srvTotals['iva_4']  > 0 ? round($srvTotals['iva_4']  / 0.04, 2) : 0;
    $srvTotals['proporcion_10']  = $srvTotals['iva_10'] > 0 ? round($srvTotals['iva_10'] / 0.10, 2) : 0;
    $srvTotals['proporcion_13']  = $srvTotals['iva_13'] > 0 ? round($srvTotals['iva_13'] / 0.13, 2) : 0;

    // ── Resumen Combinado ──────────────────────────────────────────────────
    $iva13Combinado        = round($bienTotals['iva_13'] + $srvTotals['iva_13'], 2);
    $totalIvaGastos        = round($bienTotals['iva_total'] + $srvTotals['iva_total'], 2);
    $proporcion13Combinada = $iva13Combinado > 0 ? round($iva13Combinado / 0.13, 2) : 0;
    $proporcionBienesOtras = $bienTotals['proporcion_1'] + $bienTotals['proporcion_2'] + $bienTotals['proporcion_4'];
    $proporcionTotal       = round($proporcionBienesOtras + $proporcion13Combinada, 2);

    $combinado = [
        'iva_13_combinado'       => $iva13Combinado,
        'proporcion_13_combinado'=> $proporcion13Combinada,
        'total_iva_gastos'       => $totalIvaGastos,
        'proporcion_bienes_otras'=> round($proporcionBienesOtras, 2),
        'proporcion_total'       => $proporcionTotal,
    ];

    jsonOut([
        'ok'        => true,
        'count'     => count($allRows),
        'bienes'    => ['rows' => $bienesRows,    'totals' => $bienTotals],
        'servicios' => ['rows' => $serviciosRows, 'totals' => $srvTotals],
        'combinado' => $combinado,
    ]);

} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
