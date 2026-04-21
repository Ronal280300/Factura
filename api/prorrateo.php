<?php
/**
 * Calcula y persiste el ratio de prorrateo anual del IVA.
 *
 * Body JSON:
 *   GET  (sin body)                 → lista ratios guardados
 *   { anio: 2026, modo: 'provisional' | 'definitivo' }  → calcula y guarda
 *
 *   ratio = ventas_gravadas_con_derecho / total_ventas_del_ano
 *
 * En enero..noviembre se usa 'provisional' con datos del ano en curso
 * o del anterior. En diciembre se recalcula 'definitivo'.
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../app/bootstrap.php';
Auth::requireAuth('/login.php', ['admin','accountant']);

require_once __DIR__ . '/../app/Db.php';

function jsonOut(array $data): void {
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = Db::pdo();

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query("SELECT * FROM prorrateo_anual ORDER BY anio DESC")->fetchAll();
    jsonOut(['ok' => true, 'ratios' => $rows]);
  }

  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $anio  = (int)($input['anio'] ?? 0);
  $modo  = (string)($input['modo'] ?? 'provisional');
  if (!$anio || $anio < 2020 || $anio > 2100) throw new Exception('Anio invalido');
  if (!in_array($modo, ['provisional','definitivo'], true)) throw new Exception('modo invalido');

  // Agregamos ventas emitidas del ano
  $sql = "
    SELECT
      COALESCE(SUM(CASE WHEN i.tipo_documento='NC' THEN -1 ELSE 1 END
                   * COALESCE(i.total_gravado_crc, i.total_gravado, 0)),0)      AS gravadas,
      COALESCE(SUM(CASE WHEN i.tipo_documento='NC' THEN -1 ELSE 1 END
                   * COALESCE(i.total_exento_crc, i.total_exento, 0)),0)        AS exentas,
      -- no_sujeto: tarifa 0 en breakdown
      (SELECT COALESCE(SUM(CASE WHEN i2.tipo_documento='NC' THEN -1 ELSE 1 END
                              * COALESCE(b.base_crc, b.base)), 0)
         FROM invoices i2 JOIN invoice_tax_breakdown b ON b.invoice_id = i2.id
         WHERE i2.direction='issued' AND YEAR(i2.fecha_emision) = :anio2
           AND i2.tipo_documento IN ('FE','FEE','FEC','NC','ND') AND b.tarifa = 0) AS no_sujetas
    FROM invoices i
    WHERE i.direction = 'issued'
      AND YEAR(i.fecha_emision) = :anio
      AND i.tipo_documento IN ('FE','FEE','FEC','NC','ND')
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':anio' => $anio, ':anio2' => $anio]);
  $agg = $st->fetch();

  $gravadas   = (float)$agg['gravadas'];
  $exentas    = (float)$agg['exentas'];
  $noSujetas  = (float)$agg['no_sujetas'];
  $total      = $gravadas + $exentas + $noSujetas;

  $ratio = $total > 0 ? round($gravadas / $total, 6) : 1.0;

  // Upsert
  if ($modo === 'definitivo') {
    $sqlUp = "INSERT INTO prorrateo_anual
                (anio, ratio_provisional, ratio_definitivo,
                 ventas_gravadas, ventas_exentas, ventas_no_sujetas, total_ventas, calculado_en)
              VALUES (?,?,?,?,?,?,?,NOW())
              ON DUPLICATE KEY UPDATE
                ratio_definitivo=VALUES(ratio_definitivo),
                ventas_gravadas=VALUES(ventas_gravadas),
                ventas_exentas=VALUES(ventas_exentas),
                ventas_no_sujetas=VALUES(ventas_no_sujetas),
                total_ventas=VALUES(total_ventas),
                calculado_en=NOW()";
    $pdo->prepare($sqlUp)->execute([$anio, $ratio, $ratio, $gravadas, $exentas, $noSujetas, $total]);
  } else {
    $sqlUp = "INSERT INTO prorrateo_anual
                (anio, ratio_provisional,
                 ventas_gravadas, ventas_exentas, ventas_no_sujetas, total_ventas, calculado_en)
              VALUES (?,?,?,?,?,?,NOW())
              ON DUPLICATE KEY UPDATE
                ratio_provisional=VALUES(ratio_provisional),
                ventas_gravadas=VALUES(ventas_gravadas),
                ventas_exentas=VALUES(ventas_exentas),
                ventas_no_sujetas=VALUES(ventas_no_sujetas),
                total_ventas=VALUES(total_ventas),
                calculado_en=NOW()";
    $pdo->prepare($sqlUp)->execute([$anio, $ratio, $gravadas, $exentas, $noSujetas, $total]);
  }

  $row = $pdo->prepare("SELECT * FROM prorrateo_anual WHERE anio = ?");
  $row->execute([$anio]);

  jsonOut([
    'ok' => true,
    'anio' => $anio,
    'modo' => $modo,
    'ratio' => $ratio,
    'ventas_gravadas' => round($gravadas, 2),
    'ventas_exentas'  => round($exentas, 2),
    'ventas_no_sujetas' => round($noSujetas, 2),
    'total_ventas' => round($total, 2),
    'stored' => $row->fetch(),
  ]);

} catch (Throwable $e) {
  jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
