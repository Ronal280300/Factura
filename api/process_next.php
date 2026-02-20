<?php
header('Content-Type: application/json');

require __DIR__ . '/../app/Db.php';
require __DIR__ . '/../app/ImapClient.php';
require __DIR__ . '/../app/InvoiceXmlParser.php';

try {
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $syncRunId = (int)($input['sync_run_id'] ?? 0);
  if (!$syncRunId) throw new Exception('sync_run_id requerido');

  $cfg = require __DIR__ . '/../config/config.php';
  $batchSize = (int)($cfg['batch_size_default'] ?? 10);

  $pdo = Db::pdo();

  // Estado del run
  $run = $pdo->prepare("SELECT * FROM sync_runs WHERE id = ?");
  $run->execute([$syncRunId]);
  $runRow = $run->fetch();
  if (!$runRow) throw new Exception('Sync run no existe');
  if (in_array($runRow['status'], ['done','failed','cancelled'], true)) {
    echo json_encode(['ok'=>true, 'state'=>$runRow, 'last_items'=>[]]);
    exit;
  }

  // Seleccionar siguientes pendientes (excluye skipped)
  $pdo->beginTransaction();

  $items = $pdo->prepare("SELECT * FROM sync_run_items WHERE sync_run_id = ? AND status = 'pending' ORDER BY id ASC LIMIT {$batchSize} FOR UPDATE");
  $items->execute([$syncRunId]);
  $rows = $items->fetchAll();

  if (!$rows) {
    // No hay más pendientes -> terminar
    $pdo->prepare("UPDATE sync_runs SET status='done', finished_at=NOW() WHERE id=?")->execute([$syncRunId]);
    $pdo->commit();

    $state = $pdo->query("SELECT * FROM sync_runs WHERE id = {$syncRunId}")->fetch();
    echo json_encode(['ok'=>true, 'state'=>$state, 'last_items'=>[]]);
    exit;
  }

  // Marcar processing
  $upd = $pdo->prepare("UPDATE sync_run_items SET status='processing' WHERE id=?");
  foreach ($rows as $r) $upd->execute([$r['id']]);

  $pdo->commit();

  // Conectar IMAP una sola vez por batch
  $imap = new ImapClient($cfg['imap']);
  $imap->connect();

  $lastItemsLog = [];

  $fromDate = new DateTime($runRow['from_date'].' 00:00:00');
  $toDate = new DateTime($runRow['to_date'].' 23:59:59');

  foreach ($rows as $r) {
    $uid = (int)$r['message_uid'];

    try {
      // Doble check: si ya procesado global, skip
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
        // No XML
        $pdo->prepare("UPDATE sync_run_items SET status='done' WHERE id=?")->execute([$r['id']]);
        $pdo->prepare("INSERT INTO processed_emails (message_uid, processed_at, status) VALUES (?, NOW(), 'done')")->execute([(string)$uid]);
        $pdo->prepare("UPDATE sync_runs SET processed_messages = processed_messages + 1 WHERE id=?")->execute([$syncRunId]);
        $lastItemsLog[] = "UID {$uid}: sin XML adjunto";
        continue;
      }

      $pdo->prepare("UPDATE sync_runs SET found_xml = found_xml + ? WHERE id=?")->execute([count($attachments), $syncRunId]);

      foreach ($attachments as $att) {
        $xmlContent = $att['content'];

        // Guardar copia del XML (para auditoría)
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $att['filename'] ?? ('uid_'.$uid.'.xml'));
        $storePath = __DIR__ . '/../storage/xml/' . time() . '_' . $uid . '_' . $safeName;
        file_put_contents($storePath, $xmlContent);

        $parsed = InvoiceXmlParser::parse($xmlContent);

        if (empty($parsed['clave']) || empty($parsed['fecha_emision'])) {
          $lastItemsLog[] = "UID {$uid}: XML inválido (sin clave/fecha)";
          continue;
        }

        // Validar rango por fecha del XML (obligatorio)
        $xmlDate = new DateTime($parsed['fecha_emision']);
        if ($xmlDate < $fromDate || $xmlDate > $toDate) {
          $pdo->prepare("UPDATE sync_runs SET out_of_range = out_of_range + 1 WHERE id=?")->execute([$syncRunId]);
          $lastItemsLog[] = "UID {$uid}: fuera de rango XML ({$xmlDate->format('Y-m-d')})";
          continue;
        }

        // Insert invoice (dedupe por clave)
        $pdo->beginTransaction();

        $insInv = $pdo->prepare("
          INSERT INTO invoices
          (clave, numero_consecutivo, fecha_emision, emisor_nombre, emisor_nombre_comercial, emisor_identificacion,
           moneda, tipo_cambio, total_gravado, total_exento, total_impuesto, total_comprobante, xml_path)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        try {
          $insInv->execute([
            $parsed['clave'],
            null,
            $xmlDate->format('Y-m-d H:i:s'),
            $parsed['emisor_nombre'],
            $parsed['emisor_nombre_comercial'],
            $parsed['emisor_identificacion'],
            $parsed['moneda'],
            $parsed['tipo_cambio'],
            $parsed['total_gravado'],
            $parsed['total_exento'],
            $parsed['total_impuesto'],
            $parsed['total_comprobante'],
            str_replace(__DIR__ . '/../', '', $storePath),
          ]);

          $invoiceId = (int)$pdo->lastInsertId();

          $insBr = $pdo->prepare("INSERT INTO invoice_tax_breakdown (invoice_id, tarifa, base, impuesto) VALUES (?, ?, ?, ?)");
          foreach ($parsed['breakdown'] as $b) {
            $insBr->execute([$invoiceId, $b['tarifa'], $b['base'], $b['impuesto']]);
          }

          $pdo->prepare("UPDATE sync_runs SET new_invoices = new_invoices + 1 WHERE id=?")->execute([$syncRunId]);

          $pdo->commit();
          $lastItemsLog[] = "OK {$parsed['clave']} - " . ($parsed['emisor_nombre_comercial'] ?: $parsed['emisor_nombre']) . " - " . $xmlDate->format('Y-m-d');

        } catch (Throwable $e) {
          // Duplicate key (clave)
          $pdo->rollBack();
          $pdo->prepare("UPDATE sync_runs SET duplicates = duplicates + 1 WHERE id=?")->execute([$syncRunId]);
          $lastItemsLog[] = "DUP {$parsed['clave']} (ya existía)";
        }
      }

      // Marcar email como procesado global
      $pdo->prepare("INSERT INTO processed_emails (message_uid, processed_at, status) VALUES (?, NOW(), 'done')")->execute([(string)$uid]);

      // Terminar item
      $pdo->prepare("UPDATE sync_run_items SET status='done' WHERE id=?")->execute([$r['id']]);
      $pdo->prepare("UPDATE sync_runs SET processed_messages = processed_messages + 1 WHERE id=?")->execute([$syncRunId]);

    } catch (Throwable $e) {
      $pdo->prepare("UPDATE sync_run_items SET status='error', error=? WHERE id=?")->execute([$e->getMessage(), $r['id']]);
      $pdo->prepare("UPDATE sync_runs SET errors = errors + 1, processed_messages = processed_messages + 1 WHERE id=?")->execute([$syncRunId]);
      $lastItemsLog[] = "ERR UID {$uid}: " . $e->getMessage();
    }
  }
  

  $imap->close();

  // Refrescar estado
  $state = $pdo->query("SELECT * FROM sync_runs WHERE id = {$syncRunId}")->fetch();

  echo json_encode([
    'ok' => true,
    'state' => $state,
    'last_items' => array_slice($lastItemsLog, 0, 12)
  ]);

} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}