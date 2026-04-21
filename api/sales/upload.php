<?php
/**
 * Ingesta de FE/FEE/FEC/NC/ND emitidas por el contribuyente.
 *
 * Acepta:
 *  - multipart/form-data con uno o varios files[] (cada uno un .xml)
 *  - application/json con { xmls: [ { filename, content_base64 }, ... ] }
 *
 * Las facturas se guardan con direction='issued' y pasan por el mismo parser
 * que las recibidas. El emisor debe coincidir con receptor_cedulas de
 * config.php (strict_receptor).
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireAuth('/login.php', ['admin','accountant']);

require_once __DIR__ . '/../../app/Db.php';
require_once __DIR__ . '/../../app/InvoiceXmlParser.php';
require_once __DIR__ . '/../../app/InvoiceIngestor.php';

function jsonOut(array $data): void {
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $cfg             = require __DIR__ . '/../../config/config.php';
  $receptorCedulas = array_map(fn($c) => preg_replace('/\D+/', '', (string)$c), $cfg['receptor_cedulas'] ?? []);
  $receptorCedulas = array_values(array_filter($receptorCedulas));
  $strict          = (bool)($cfg['strict_receptor'] ?? true);

  $items = [];  // [['filename'=>, 'content'=>], ...]

  // multipart uploads
  if (!empty($_FILES)) {
    $all = $_FILES['files'] ?? $_FILES['file'] ?? null;
    if ($all && is_array($all['name'])) {
      for ($i = 0, $n = count($all['name']); $i < $n; $i++) {
        if (($all['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $items[] = ['filename' => $all['name'][$i], 'content' => file_get_contents($all['tmp_name'][$i])];
      }
    } elseif ($all && !is_array($all['name']) && ($all['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
      $items[] = ['filename' => $all['name'], 'content' => file_get_contents($all['tmp_name'])];
    }
  }

  // JSON body fallback
  if (!$items) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    foreach (($input['xmls'] ?? []) as $x) {
      $items[] = [
        'filename' => $x['filename'] ?? 'xml',
        'content'  => base64_decode($x['content_base64'] ?? ''),
      ];
    }
  }

  if (!$items) throw new Exception('Sin XMLs para procesar (usar multipart files[] o JSON {xmls:[{content_base64}]}).');

  $pdo     = Db::pdo();
  $xmlDir  = __DIR__ . '/../../storage/xml_issued/';
  if (!is_dir($xmlDir)) mkdir($xmlDir, 0755, true);

  $stats = ['saved'=>0,'duplicate'=>0,'skipped'=>0,'error'=>0,'details'=>[]];

  foreach ($items as $it) {
    $fn = $it['filename'] ?: 'xml';
    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fn);
    $path = $xmlDir . date('Ymd_His') . '_' . substr(md5($it['content']), 0, 8) . '_' . $safe;
    file_put_contents($path, $it['content']);

    try {
      $parsed = InvoiceXmlParser::parse($it['content']);
    } catch (Throwable $e) {
      $stats['error']++;
      $stats['details'][] = ['file'=>$fn,'error'=>'parse: '.$e->getMessage()];
      continue;
    }

    $rel = str_replace(__DIR__ . '/../../', '', $path);
    try {
      $res = InvoiceIngestor::ingest($pdo, $parsed, 'issued', $rel, null, null, $receptorCedulas, $strict, $it['content']);
      $stats[$res['status']]++;
      $stats['details'][] = [
        'file'       => $fn,
        'status'     => $res['status'],
        'invoice_id' => $res['invoice_id'] ?? null,
        'clave'      => $parsed['clave'] ?? null,
        'tipo'       => $parsed['tipo_documento'] ?? null,
        'reason'     => $res['reason'] ?? null,
      ];
    } catch (Throwable $e) {
      $stats['error']++;
      $stats['details'][] = ['file'=>$fn,'error'=>$e->getMessage()];
    }
  }

  jsonOut(['ok' => true, 'stats' => $stats]);

} catch (Throwable $e) {
  jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
