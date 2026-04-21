<?php
/**
 * Consulta en Hacienda el estado de mensajes ya enviados.
 * Actualiza receptor_messages con 'aceptado' | 'rechazado' | 'error'.
 *
 * Diseñado para ejecutarse desde un cron (o llamarse desde la UI).
 * Body JSON opcional: { limit?: int (default 50) }
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireAuth();

require __DIR__ . '/../../app/Db.php';
require __DIR__ . '/../../app/HaciendaClient.php';

function jsonOut(array $data): void {
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $limit = min(500, max(1, (int)($input['limit'] ?? 50)));

  $cfg    = require __DIR__ . '/../../config/config.php';
  $client = new HaciendaClient($cfg['hacienda']);
  $pdo    = Db::pdo();

  $rows = $pdo->query("
    SELECT id, clave FROM receptor_messages
    WHERE estado_hacienda = 'enviado'
    ORDER BY fecha_envio ASC
    LIMIT {$limit}
  ")->fetchAll();

  $updated = ['aceptado' => 0, 'rechazado' => 0, 'pendiente' => 0, 'error' => 0];

  foreach ($rows as $r) {
    try {
      $st = $client->status($r['clave']);
      $ind = strtolower((string)($st['ind-estado'] ?? $st['estado'] ?? ''));
      $respXml = isset($st['respuesta-xml']) ? base64_decode((string)$st['respuesta-xml']) : null;

      $map = [
        'aceptado'   => 'aceptado',
        'accepted'   => 'aceptado',
        'rechazado'  => 'rechazado',
        'rejected'   => 'rechazado',
        'procesando' => null,
        'recibido'   => null,
      ];
      $target = $map[$ind] ?? null;

      if ($target === 'aceptado' || $target === 'rechazado') {
        $pdo->prepare("UPDATE receptor_messages
                       SET estado_hacienda = ?, xml_respuesta = ?, fecha_respuesta = NOW()
                       WHERE id = ?")
            ->execute([$target, $respXml, $r['id']]);
        $updated[$target]++;
      } else {
        $updated['pendiente']++;
      }
    } catch (Throwable $e) {
      $pdo->prepare("UPDATE receptor_messages SET error = ? WHERE id = ?")
          ->execute([$e->getMessage(), $r['id']]);
      $updated['error']++;
    }
  }

  jsonOut(['ok' => true, 'checked' => count($rows), 'updated' => $updated]);

} catch (Throwable $e) {
  jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
