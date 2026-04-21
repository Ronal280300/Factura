<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../app/bootstrap.php';
Auth::requireAuth();

require __DIR__ . '/../app/Db.php';

function jsonOut(array $data): void {
  ob_end_clean();
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
    $pdo = Db::pdo();

    $rows = $pdo->query("
        SELECT
            id,
            status,
            from_date,
            to_date,
            total_messages,
            processed_messages,
            found_xml,
            new_invoices,
            duplicates,
            out_of_range,
            errors,
            started_at,
            finished_at,
            CASE
                WHEN finished_at IS NOT NULL AND started_at IS NOT NULL
                THEN TIMESTAMPDIFF(SECOND, started_at, finished_at)
                ELSE NULL
            END AS duracion_seg
        FROM sync_runs
        ORDER BY created_at DESC
        LIMIT 30
    ")->fetchAll();

    jsonOut(['ok' => true, 'runs' => $rows]);

} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
