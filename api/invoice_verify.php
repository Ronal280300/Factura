<?php
/**
 * Verifica la integridad de los XMLs almacenados contra el hash guardado.
 * Body JSON opcional: { invoice_id?: int, limit?: int (default 500) }
 * Si invoice_id se especifica, verifica solo esa factura.
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
  $input    = json_decode(file_get_contents('php://input'), true) ?? [];
  $invoiceId= isset($input['invoice_id']) ? (int)$input['invoice_id'] : null;
  $limit    = min(2000, max(1, (int)($input['limit'] ?? 500)));

  $pdo = Db::pdo();
  if ($invoiceId) {
    $st = $pdo->prepare("SELECT id, clave, xml_path, xml_sha256 FROM invoices WHERE id = ?");
    $st->execute([$invoiceId]);
  } else {
    $st = $pdo->prepare("SELECT id, clave, xml_path, xml_sha256 FROM invoices WHERE xml_sha256 IS NOT NULL AND xml_path IS NOT NULL ORDER BY id DESC LIMIT {$limit}");
    $st->execute();
  }
  $rows = $st->fetchAll();

  $ok = 0; $bad = 0; $missing = 0;
  $issues = [];
  foreach ($rows as $r) {
    $abs = __DIR__ . '/../' . $r['xml_path'];
    if (!is_file($abs)) { $missing++; $issues[] = ['id'=>$r['id'],'clave'=>$r['clave'],'status'=>'missing']; continue; }
    $h = hash_file('sha256', $abs);
    if (strcasecmp($h, $r['xml_sha256']) === 0) {
      $ok++;
    } else {
      $bad++;
      $issues[] = ['id'=>$r['id'],'clave'=>$r['clave'],'status'=>'tampered','expected'=>$r['xml_sha256'],'actual'=>$h];
    }
  }

  Auth::audit(Auth::userId(), 'invoice.verify', null, ['checked'=>count($rows),'ok'=>$ok,'bad'=>$bad,'missing'=>$missing]);

  jsonOut(['ok'=>true, 'checked'=>count($rows), 'valid'=>$ok, 'tampered'=>$bad, 'missing'=>$missing, 'issues'=>$issues]);

} catch (Throwable $e) {
  jsonOut(['ok'=>false,'error'=>$e->getMessage()]);
}
