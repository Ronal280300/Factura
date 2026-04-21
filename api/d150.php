<?php
/**
 * Devuelve el detalle del formulario D-150 para un periodo.
 * Body JSON: { from: YYYY-MM-DD, to: YYYY-MM-DD, require_mr_accepted?: bool (def true) }
 *
 * Si require_mr_accepted=true (default), solo se cuentan como credito fiscal
 * las compras con MensajeReceptor aceptado. Para el borrador del mes conviene
 * ponerlo en false y verlo antes de aceptar.
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../app/Db.php';
require_once __DIR__ . '/../app/D150Calculator.php';

function jsonOut(array $data): void {
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $from  = (string)($input['from'] ?? '');
  $to    = (string)($input['to']   ?? '');
  $req   = array_key_exists('require_mr_accepted', $input) ? (bool)$input['require_mr_accepted'] : true;

  if (!$from || !$to) throw new Exception('Rango from/to requerido');

  $d150 = D150Calculator::compute(Db::pdo(), $from, $to, $req);
  jsonOut(['ok' => true, 'd150' => $d150]);

} catch (Throwable $e) {
  jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
