<?php
header('Content-Type: application/json');

require __DIR__ . '/../app/Db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $from  = $input['from'] ?? null;
    $to    = $input['to']   ?? null;

    if (!$from || !$to) throw new Exception('Fechas requeridas.');

    $fromDt = DateTime::createFromFormat('Y-m-d', $from);
    $toDt   = DateTime::createFromFormat('Y-m-d', $to);
    if (!$fromDt || !$toDt) throw new Exception('Formato de fecha inválido.');

    $pdo = Db::pdo();

    // Pivot de IVA por tarifa usando conditional aggregation
    $sql = "
        SELECT
            i.id,
            DATE(i.fecha_emision)                                             AS fecha,
            MONTH(i.fecha_emision)                                            AS mes_num,
            COALESCE(NULLIF(i.emisor_nombre_comercial,''), i.emisor_nombre)   AS emisor,
            i.emisor_identificacion                                           AS cedula,
            i.clave,
            i.moneda,
            COALESCE(i.total_gravado,     0)                                  AS total_gravado,
            COALESCE(i.total_exento,      0)                                  AS total_exento,
            COALESCE(i.total_impuesto,    0)                                  AS total_impuesto,
            COALESCE(i.total_comprobante, 0)                                  AS total_comprobante,

            COALESCE(SUM(CASE WHEN b.tarifa =  1.00 THEN b.base     ELSE 0 END), 0) AS base_1,
            COALESCE(SUM(CASE WHEN b.tarifa =  1.00 THEN b.impuesto ELSE 0 END), 0) AS iva_1,

            COALESCE(SUM(CASE WHEN b.tarifa =  2.00 THEN b.base     ELSE 0 END), 0) AS base_2,
            COALESCE(SUM(CASE WHEN b.tarifa =  2.00 THEN b.impuesto ELSE 0 END), 0) AS iva_2,

            COALESCE(SUM(CASE WHEN b.tarifa =  4.00 THEN b.base     ELSE 0 END), 0) AS base_4,
            COALESCE(SUM(CASE WHEN b.tarifa =  4.00 THEN b.impuesto ELSE 0 END), 0) AS iva_4,

            COALESCE(SUM(CASE WHEN b.tarifa = 10.00 THEN b.base     ELSE 0 END), 0) AS base_10,
            COALESCE(SUM(CASE WHEN b.tarifa = 10.00 THEN b.impuesto ELSE 0 END), 0) AS iva_10,

            COALESCE(SUM(CASE WHEN b.tarifa = 13.00 THEN b.base     ELSE 0 END), 0) AS base_13,
            COALESCE(SUM(CASE WHEN b.tarifa = 13.00 THEN b.impuesto ELSE 0 END), 0) AS iva_13,

            -- Exento: tarifa=0 y hay campo total_exento en resumen (líneas exentas)
            COALESCE(SUM(CASE WHEN b.tarifa =  0.00 THEN b.base     ELSE 0 END), 0) AS no_sujeto_base

        FROM invoices i
        LEFT JOIN invoice_tax_breakdown b ON b.invoice_id = i.id
        WHERE i.fecha_emision >= :from AND i.fecha_emision <= :to
        GROUP BY i.id
        ORDER BY i.fecha_emision ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':from' => $from . ' 00:00:00',
        ':to'   => $to   . ' 23:59:59',
    ]);
    $rows = $stmt->fetchAll();

    $meses = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    $numCols = ['total_gravado','total_exento','total_impuesto','total_comprobante',
                'base_1','iva_1','base_2','iva_2','base_4','iva_4',
                'base_10','iva_10','base_13','iva_13','no_sujeto_base','diferencia'];

    foreach ($rows as &$row) {
        $row['mes'] = $meses[(int)$row['mes_num']] ?? '';

        // Diferencia de redondeo: suma IVA por líneas vs. TotalImpuesto del resumen XML
        $sumaIvaLineas = (float)$row['iva_1']  + (float)$row['iva_2']
                       + (float)$row['iva_4']  + (float)$row['iva_10']
                       + (float)$row['iva_13'];
        $row['diferencia'] = round($sumaIvaLineas - (float)$row['total_impuesto'], 4);

        foreach ($numCols as $k) {
            $row[$k] = round((float)($row[$k] ?? 0), 2);
        }
    }
    unset($row);

    // Totales globales (una sola pasada — sin doble conteo)
    $totals = [];
    foreach ($numCols as $k) {
        $totals[$k] = round(array_sum(array_column($rows, $k)), 2);
    }
    // IVA total a pagar del periodo
    $totals['iva_total'] = round(
        $totals['iva_1'] + $totals['iva_2'] + $totals['iva_4']
      + $totals['iva_10'] + $totals['iva_13'],
        2
    );

    echo json_encode([
        'ok'     => true,
        'count'  => count($rows),
        'rows'   => $rows,
        'totals' => $totals,
    ]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
