<?php
header('Content-Type: application/json');

require __DIR__ . '/../app/Db.php';

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

    echo json_encode(['ok' => true, 'runs' => $rows]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
