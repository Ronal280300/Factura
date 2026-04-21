<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json');

require __DIR__ . '/../app/Db.php';

function jsonOut(array $data): void {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id    = (int)($input['id']    ?? 0);
    $field = $input['field'] ?? '';
    $value = $input['value'] ?? null;

    if (!$id) throw new Exception('ID inválido.');
    if (!in_array($field, ['excluida', 'override_tipo'])) throw new Exception('Campo inválido.');

    $pdo = Db::pdo();

    if ($field === 'excluida') {
        $v = $value ? 1 : 0;
        $pdo->prepare("UPDATE invoices SET excluida = ? WHERE id = ?")->execute([$v, $id]);
    } else {
        $allowed = ['bien', 'servicio', null, ''];
        if (!in_array($value, $allowed, true)) throw new Exception('Valor inválido para override_tipo.');
        $v = ($value === '' || $value === null) ? null : $value;
        $pdo->prepare("UPDATE invoices SET override_tipo = ? WHERE id = ?")->execute([$v, $id]);
    }

    jsonOut(['ok' => true]);

} catch (Throwable $e) {
    jsonOut(['ok' => false, 'error' => $e->getMessage()]);
}
