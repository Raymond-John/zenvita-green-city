<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

require_user('admin');
ensure_product_landing_columns();
$body = request_body();
$action = strtolower(trim((string) ($body['action'] ?? 'save')));
$pdo = db();

if ($action === 'delete') {
    $id = (int) ($body['id'] ?? 0);
    if ($id < 1) {
        json_out(['ok' => false, 'error' => 'Choose a feature to delete.'], 422);
    }
    $pdo->prepare('DELETE FROM landing_features WHERE id = ?')->execute([$id]);
    json_out(['ok' => true]);
}

$id = (int) ($body['id'] ?? 0);
$title = trim((string) ($body['title'] ?? ''));
$sortOrder = (int) ($body['sortOrder'] ?? 0);
$status = strtolower(trim((string) ($body['status'] ?? 'active')));

if ($title === '' || mb_strlen($title) > 160) {
    json_out(['ok' => false, 'error' => 'Enter a feature title (max 160 characters).'], 422);
}
if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

if ($id > 0) {
    $stmt = $pdo->prepare(
        'UPDATE landing_features SET title = ?, sort_order = ?, status = ? WHERE id = ?'
    );
    $stmt->execute([$title, $sortOrder, $status, $id]);
    json_out(['ok' => true, 'id' => $id, 'created' => false]);
}

$stmt = $pdo->prepare(
    'INSERT INTO landing_features (title, sort_order, status) VALUES (?, ?, ?)'
);
$stmt->execute([$title, $sortOrder, $status]);
json_out(['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'created' => true]);
