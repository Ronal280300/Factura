<?php
// Buffer todo el output para que ningun warning/notice de PHP o IMAP
// corrompa la respuesta JSON (XAMPP tiene display_errors=On por defecto)
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require __DIR__ . '/../app/Db.php';
require __DIR__ . '/../app/ImapClient.php';

/**
 * Descarta cualquier output acumulado en el buffer (warnings, notices, etc.)
 * y envia JSON limpio al cliente.
 */
function jsonOut(array $data): void {
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $from  = trim($input['from'] ?? '');
  $to    = trim($input['to']   ?? '');

  if (!$from || !$to) throw new Exception('Faltan fechas.');

  // Validar formato antes de pasarlo a DateTime
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ||
      !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    throw new Exception('Formato de fecha inválido (esperado YYYY-MM-DD).');
  }
  if ($from > $to) throw new Exception('La fecha "Desde" no puede ser mayor que "Hasta".');

  $fromDate = new DateTime($from . ' 00:00:00');
  $toDate   = new DateTime($to   . ' 23:59:59');

  $cfg        = require __DIR__ . '/../config/config.php';
  $bufferDays = (int)($cfg['search_buffer_days'] ?? 5);

  // Rango IMAP con buffer (BEFORE es exclusivo → +1 día extra)
  $imapFrom        = (clone $fromDate)->modify("-{$bufferDays} days");
  $imapToExclusive = (clone $toDate)->modify("+{$bufferDays} days")->modify('+1 day');

  $pdo = Db::pdo();

  // Crear sync run
  $stmt = $pdo->prepare(
    "INSERT INTO sync_runs (status, from_date, to_date, started_at)
     VALUES ('running', ?, ?, NOW())"
  );
  $stmt->execute([$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);
  $syncRunId = (int)$pdo->lastInsertId();

  // Conectar IMAP y buscar UIDs por fecha de recepción
  $imap = new ImapClient($cfg['imap']);
  $imap->connect();
  $uids = $imap->searchByReceivedDateRange($imapFrom, $imapToExclusive);
  $imap->close();            // Cerrar IMAP antes del loop pesado de BD

  // ── Caso: ningún correo en el rango ────────────────────────────
  if (empty($uids)) {
    $pdo->prepare(
      "UPDATE sync_runs
       SET status='done', finished_at=NOW(), total_messages=0, processed_messages=0
       WHERE id=?"
    )->execute([$syncRunId]);

    $state = $pdo->query("SELECT * FROM sync_runs WHERE id = {$syncRunId}")->fetch();
    jsonOut([
      'ok'             => true,
      'sync_run_id'    => $syncRunId,
      'total_messages' => 0,
      'zero_emails'    => true,   // señal explícita para el JS
      'state'          => $state,
    ]);
  }

  // ── Clasificar UIDs: pending vs skipped ────────────────────────
  $insItem      = $pdo->prepare(
    "INSERT IGNORE INTO sync_run_items
     (sync_run_id, message_uid, email_date, from_email, subject, status)
     VALUES (?, ?, ?, ?, ?, 'pending')"
  );
  $insSkipped   = $pdo->prepare(
    "INSERT IGNORE INTO sync_run_items
     (sync_run_id, message_uid, email_date, from_email, subject, status)
     VALUES (?, ?, ?, ?, ?, 'skipped')"
  );
  $chkProcessed = $pdo->prepare(
    "SELECT 1 FROM processed_emails WHERE message_uid = ? LIMIT 1"
  );

  // Reconectar IMAP solo para leer headers (una sola conexión)
  $imap->connect();
  $total   = 0;
  $skipped = 0;
  foreach ($uids as $uid) {
    $chkProcessed->execute([(string)$uid]);
    $already = (bool)$chkProcessed->fetchColumn();

    $h = $imap->getHeaderInfoByUid((int)$uid);

    if ($already) {
      $insSkipped->execute([$syncRunId, (string)$uid, $h['email_date'], $h['from_email'], $h['subject']]);
      $skipped++;
    } else {
      $insItem->execute([$syncRunId, (string)$uid, $h['email_date'], $h['from_email'], $h['subject']]);
    }
    $total++;
  }
  $imap->close();

  // Guardar totales (skipped ya cuentan como procesados para la barra)
  $pdo->prepare(
    "UPDATE sync_runs
     SET total_messages = ?, processed_messages = ?
     WHERE id = ?"
  )->execute([$total, $skipped, $syncRunId]);

  $state = $pdo->query("SELECT * FROM sync_runs WHERE id = {$syncRunId}")->fetch();

  jsonOut([
    'ok'             => true,
    'sync_run_id'    => $syncRunId,
    'total_messages' => $total,
    'zero_emails'    => false,
    'state'          => $state,
  ]);

} catch (Throwable $e) {
  jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
