<?php
/**
 * Envia Mensaje Receptor en lote para todas las FE/FEE/FEC del periodo que
 * aun no tienen MR enviado/aceptado.
 *
 * Body JSON: { from: YYYY-MM-DD, to: YYYY-MM-DD, mensaje?: '1'|'2'|'3' (def '1'),
 *              condicion_impuesto?: '01'..'05', limit?: int }
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../../app/ReceptorMessageSender.php';

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) require_once $autoload;

function jsonOut(array $data): void {
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $from  = (string)($input['from'] ?? '');
  $to    = (string)($input['to']   ?? '');
  $msg   = (string)($input['mensaje'] ?? '1');
  $cond  = $input['condicion_impuesto'] ?? null;
  $limit = min(200, max(1, (int)($input['limit'] ?? 100)));

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    throw new Exception('Rango de fechas invalido');
  }
  if (!in_array($msg, ['1','2','3'], true)) throw new Exception('mensaje invalido');

  $cfg    = require __DIR__ . '/../../config/config.php';
  $pdo    = Db::pdo();
  $sender = new ReceptorMessageSender($pdo, $cfg);

  $sql = "
    SELECT i.id
    FROM invoices i
    LEFT JOIN receptor_messages rm
      ON rm.invoice_id = i.id AND rm.estado_hacienda IN ('enviado','aceptado','firmado')
    WHERE i.fecha_emision >= :from AND i.fecha_emision <= :to
      AND i.tipo_documento IN ('FE','FEE','FEC')
      AND rm.id IS NULL
    ORDER BY i.fecha_emision ASC
    LIMIT {$limit}
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
  $ids = array_column($st->fetchAll(), 'id');

  $ok = 0; $fail = 0; $details = [];
  foreach ($ids as $invId) {
    try {
      $sender->send((int)$invId, $msg, ['condicion_impuesto' => $cond]);
      $ok++;
    } catch (Throwable $e) {
      $fail++;
      $details[] = ['invoice_id' => $invId, 'error' => $e->getMessage()];
    }
  }

  jsonOut(['ok' => true, 'total' => count($ids), 'sent' => $ok, 'failed' => $fail, 'errors' => $details]);

} catch (Throwable $e) {
  jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
