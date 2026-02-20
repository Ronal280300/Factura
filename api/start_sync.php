<?php
header('Content-Type: application/json');

require __DIR__ . '/../app/Db.php';
require __DIR__ . '/../app/ImapClient.php';

try {
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $from = $input['from'] ?? null;
  $to = $input['to'] ?? null;

  if (!$from || !$to) throw new Exception('Faltan fechas.');

  $fromDate = new DateTime($from . ' 00:00:00');
  $toDate = new DateTime($to . ' 23:59:59');

  $cfg = require __DIR__ . '/../config/config.php';
  $bufferDays = (int)($cfg['search_buffer_days'] ?? 5);

  // Para IMAP: usamos rango de RECEPCIÓN con buffer y to+1 día por BEFORE exclusivo
  $imapFrom = (clone $fromDate)->modify("-{$bufferDays} days");
  $imapToExclusive = (clone $toDate)->modify("+{$bufferDays} days")->modify('+1 day');

  $pdo = Db::pdo();

  // Crear sync run
  $stmt = $pdo->prepare("INSERT INTO sync_runs (status, from_date, to_date, started_at) VALUES ('running', ?, ?, NOW())");
  $stmt->execute([$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);
  $syncRunId = (int)$pdo->lastInsertId();

  // Conectar IMAP y buscar uids
  $imap = new ImapClient($cfg['imap']);
  $imap->connect();
  $uids = $imap->searchByReceivedDateRange($imapFrom, $imapToExclusive);

  // Insertar items (y evitar reprocesar por UID global)
  $insItem = $pdo->prepare("INSERT IGNORE INTO sync_run_items (sync_run_id, message_uid, email_date, from_email, subject, status) VALUES (?, ?, ?, ?, ?, 'pending')");
  $chkProcessed = $pdo->prepare("SELECT 1 FROM processed_emails WHERE message_uid = ? LIMIT 1");

  $total = 0;
  foreach ($uids as $uid) {
    // Si ya está procesado globalmente, lo marcamos skipped (pero igual lo metemos para progreso si quieres)
    $chkProcessed->execute([(string)$uid]);
    $already = (bool)$chkProcessed->fetchColumn();

    $h = $imap->getHeaderInfoByUid((int)$uid);

    if ($already) {
      // Insertamos como skipped para que cuente “encontrados pero ya leídos”
      $pdo->prepare("INSERT IGNORE INTO sync_run_items (sync_run_id, message_uid, email_date, from_email, subject, status) VALUES (?, ?, ?, ?, ?, 'skipped')")
          ->execute([$syncRunId, (string)$uid, $h['email_date'], $h['from_email'], $h['subject']]);
    } else {
      $insItem->execute([$syncRunId, (string)$uid, $h['email_date'], $h['from_email'], $h['subject']]);
    }
    $total++;
  }

  // Total messages = todos los items (incluye skipped)
  $pdo->prepare("UPDATE sync_runs SET total_messages = ? WHERE id = ?")->execute([$total, $syncRunId]);

  $imap->close();

  // Traer estado
  $state = $pdo->query("SELECT * FROM sync_runs WHERE id = {$syncRunId}")->fetch();

  echo json_encode([
    'ok' => true,
    'sync_run_id' => $syncRunId,
    'total_messages' => $total,
    'state' => $state
  ]);

} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}