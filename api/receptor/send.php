<?php
/**
 * Envia un Mensaje Receptor para una factura ya ingresada.
 * Body JSON: { invoice_id, mensaje: '1'|'2'|'3', detalle?, condicion_impuesto?,
 *              monto_acreditar?, monto_gasto? }
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireAuth('/login.php', ['admin','accountant']);

require_once __DIR__ . '/../../app/ReceptorMessageSender.php';

// Autoload composer (xmlseclibs)
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) require_once $autoload;

function jsonOut(array $data): void {
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $invId   = (int)($input['invoice_id'] ?? 0);
  $mensaje = (string)($input['mensaje']  ?? '1');

  if (!$invId) throw new Exception('invoice_id requerido');

  $cfg    = require __DIR__ . '/../../config/config.php';
  $sender = new ReceptorMessageSender(Db::pdo(), $cfg);

  $row = $sender->send($invId, $mensaje, [
    'detalle'           => $input['detalle']            ?? null,
    'condicion_impuesto'=> $input['condicion_impuesto'] ?? null,
    'monto_acreditar'   => $input['monto_acreditar']    ?? null,
    'monto_gasto'       => $input['monto_gasto']        ?? null,
  ]);

  jsonOut(['ok' => true, 'message' => $row]);

} catch (Throwable $e) {
  jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
