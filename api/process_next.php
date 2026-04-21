<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../app/bootstrap.php';
Auth::requireAuth('/login.php', ['admin','accountant']);

require __DIR__ . '/../app/Db.php';
require __DIR__ . '/../app/ImapClient.php';
require __DIR__ . '/../app/InvoiceXmlParser.php';
require __DIR__ . '/../app/InvoiceIngestor.php';

function jsonOut(array $data): void {
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

/** Normaliza cedula: solo digitos. */
function normCedula($s): string {
  return preg_replace('/\D+/', '', (string)$s);
}

try {
  $input     = json_decode(file_get_contents('php://input'), true) ?? [];
  $syncRunId = (int)($input['sync_run_id'] ?? 0);
  if (!$syncRunId) throw new Exception('sync_run_id requerido');

  $cfg             = require __DIR__ . '/../config/config.php';
  $batchSize       = (int)($cfg['batch_size_default'] ?? 10);
  $receptorCedulas = array_map('normCedula', $cfg['receptor_cedulas'] ?? []);
  $receptorCedulas = array_values(array_filter($receptorCedulas));
  $strictReceptor  = (bool)($cfg['strict_receptor'] ?? true);

  $pdo = Db::pdo();

  // ── Estado del run ─────────────────────────────────────────────
  $run = $pdo->prepare("SELECT * FROM sync_runs WHERE id = ?");
  $run->execute([$syncRunId]);
  $runRow = $run->fetch();
  if (!$runRow) throw new Exception('Sync run no existe');

  if (in_array($runRow['status'], ['done','failed','cancelled'], true)) {
    jsonOut(['ok' => true, 'state' => $runRow, 'last_items' => []]);
  }

  // ── Seleccionar siguiente lote de pendientes ───────────────────
  $pdo->beginTransaction();

  $items = $pdo->prepare(
    "SELECT * FROM sync_run_items
     WHERE sync_run_id = ? AND status = 'pending'
     ORDER BY id ASC
     LIMIT {$batchSize} FOR UPDATE"
  );
  $items->execute([$syncRunId]);
  $rows = $items->fetchAll();

  if (!$rows) {
    // Sin más pendientes → marcar done y forzar progreso al 100%
    $pdo->prepare(
      "UPDATE sync_runs
       SET status='done', finished_at=NOW(), processed_messages=total_messages
       WHERE id=?"
    )->execute([$syncRunId]);
    $pdo->commit();

    $state = $pdo->query("SELECT * FROM sync_runs WHERE id = {$syncRunId}")->fetch();
    jsonOut(['ok' => true, 'state' => $state, 'last_items' => []]);
  }

  // Marcar como processing
  $upd = $pdo->prepare("UPDATE sync_run_items SET status='processing' WHERE id=?");
  foreach ($rows as $r) $upd->execute([$r['id']]);
  $pdo->commit();

  // ── Conectar IMAP una sola vez para el lote ────────────────────
  $imap = new ImapClient($cfg['imap']);
  $imap->connect();

  $lastItemsLog = [];
  $fromDate     = new DateTime($runRow['from_date'] . ' 00:00:00');
  $toDate       = new DateTime($runRow['to_date']   . ' 23:59:59');

  $xmlDir = __DIR__ . '/../storage/xml/';
  if (!is_dir($xmlDir)) mkdir($xmlDir, 0755, true);

  foreach ($rows as $r) {
    $uid = (int)$r['message_uid'];

    try {
      // Doble check: si ya fue procesado en otra ejecución, skip
      $chk = $pdo->prepare("SELECT 1 FROM processed_emails WHERE message_uid = ? LIMIT 1");
      $chk->execute([(string)$uid]);
      if ($chk->fetchColumn()) {
        $pdo->prepare("UPDATE sync_run_items SET status='skipped' WHERE id=?")->execute([$r['id']]);
        $pdo->prepare("UPDATE sync_runs SET processed_messages = processed_messages + 1 WHERE id=?")->execute([$syncRunId]);
        $lastItemsLog[] = "SKIP UID {$uid} (ya procesado)";
        continue;
      }

      $attachments = $imap->fetchXmlAttachmentsByUid($uid);

      if (!$attachments) {
        $pdo->prepare("UPDATE sync_run_items SET status='done' WHERE id=?")->execute([$r['id']]);
        $pdo->prepare("INSERT IGNORE INTO processed_emails (message_uid, processed_at, status) VALUES (?, NOW(), 'done')")->execute([(string)$uid]);
        $pdo->prepare("UPDATE sync_runs SET processed_messages = processed_messages + 1 WHERE id=?")->execute([$syncRunId]);
        $lastItemsLog[] = "UID {$uid}: sin XML adjunto";
        continue;
      }

      $pdo->prepare("UPDATE sync_runs SET found_xml = found_xml + ? WHERE id=?")->execute([count($attachments), $syncRunId]);

      foreach ($attachments as $att) {
        $xmlContent = $att['content'];

        // Guardar copia del XML para auditoría (5 años minimo por ley)
        $safeName   = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $att['filename'] ?? ('uid_' . $uid . '.xml'));
        $storePath  = $xmlDir . time() . '_' . $uid . '_' . $safeName;
        file_put_contents($storePath, $xmlContent);

        // Ignorar Mensaje Receptor (respuestas de aceptacion que no son FE)
        if (stripos($xmlContent, '<MensajeReceptor') !== false ||
            stripos($xmlContent, '<MensajeHacienda') !== false) {
          $lastItemsLog[] = "UID {$uid}: mensaje Hacienda/Receptor (ignorado)";
          continue;
        }

        // ── Parsear XML ─────────────────────────────────────────
        try {
          $parsed = InvoiceXmlParser::parse($xmlContent);
        } catch (Throwable $ePar) {
          $lastItemsLog[] = "UID {$uid}: XML no parseable — " . $ePar->getMessage();
          continue;
        }

        $xmlDate = !empty($parsed['fecha_emision']) ? new DateTime($parsed['fecha_emision']) : null;
        $relPath = str_replace(__DIR__ . '/../', '', $storePath);

        $res = InvoiceIngestor::ingest(
          $pdo, $parsed, 'received', $relPath,
          $fromDate, $toDate, $receptorCedulas, $strictReceptor,
          $xmlContent
        );

        if ($res['status'] === 'saved') {
          $pdo->prepare("UPDATE sync_runs SET new_invoices = new_invoices + 1 WHERE id=?")->execute([$syncRunId]);
          $emisor = $parsed['emisor_nombre_comercial'] ?: $parsed['emisor_nombre'];
          $fstr = $xmlDate ? $xmlDate->format('Y-m-d') : '?';
          $lastItemsLog[] = "OK [{$parsed['tipo_documento']}] {$parsed['clave']} — {$emisor} ({$fstr})";
        } elseif ($res['status'] === 'duplicate') {
          $pdo->prepare("UPDATE sync_runs SET duplicates = duplicates + 1 WHERE id=?")->execute([$syncRunId]);
          $lastItemsLog[] = "DUP {$parsed['clave']} (ya existía)";
        } else {
          // skipped
          $reason = $res['reason'] ?? 'skipped';
          if ($reason === 'fuera_de_rango') {
            $pdo->prepare("UPDATE sync_runs SET out_of_range = out_of_range + 1 WHERE id=?")->execute([$syncRunId]);
            $lastItemsLog[] = "UID {$uid}: fuera de rango";
          } elseif ($reason === 'receptor_ajeno') {
            $pdo->prepare("UPDATE sync_runs SET wrong_receptor = wrong_receptor + 1 WHERE id=?")->execute([$syncRunId]);
            $lastItemsLog[] = "UID {$uid}: receptor ajeno — descartado";
          } else {
            $lastItemsLog[] = "UID {$uid}: {$reason}";
          }
        }
      } // foreach attachments

      // Marcar correo como procesado globalmente
      $pdo->prepare("INSERT IGNORE INTO processed_emails (message_uid, processed_at, status) VALUES (?, NOW(), 'done')")->execute([(string)$uid]);

      $pdo->prepare("UPDATE sync_run_items SET status='done' WHERE id=?")->execute([$r['id']]);
      $pdo->prepare("UPDATE sync_runs SET processed_messages = processed_messages + 1 WHERE id=?")->execute([$syncRunId]);

    } catch (Throwable $e) {
      $pdo->prepare("UPDATE sync_run_items SET status='error', error=? WHERE id=?")->execute([$e->getMessage(), $r['id']]);
      $pdo->prepare("UPDATE sync_runs SET errors = errors + 1, processed_messages = processed_messages + 1 WHERE id=?")->execute([$syncRunId]);
      $lastItemsLog[] = "ERR UID {$uid}: " . $e->getMessage();
    }
  } // foreach rows

  $imap->close();

  $state = $pdo->query("SELECT * FROM sync_runs WHERE id = {$syncRunId}")->fetch();

  jsonOut([
    'ok'         => true,
    'state'      => $state,
    'last_items' => array_slice($lastItemsLog, 0, 12),
  ]);

} catch (Throwable $e) {
  jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
