<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$user = require_user('store');
$body = request_body();
$pdo = db();

$storeStmt = $pdo->prepare('SELECT id FROM stores WHERE owner_user_id = ? LIMIT 1');
$storeStmt->execute([(int) $user['id']]);
$store = $storeStmt->fetch();
if (!$store) {
    json_out(['ok' => false, 'error' => 'No store is linked to this account.'], 404);
}
$storeId = (int) $store['id'];

$action = trim((string) ($body['action'] ?? 'status'));

if ($action === 'mark_read') {
    $pdo->prepare('UPDATE order_notifications SET is_read = 1 WHERE store_id = ? AND is_read = 0')
        ->execute([$storeId]);
    json_out(['ok' => true]);
}

$orderId = (int) ($body['orderId'] ?? 0);
$status = trim((string) ($body['status'] ?? ''));
$allowed = ['Accepted', 'Ready', 'Completed', 'Cancelled'];
if ($orderId < 1 || !in_array($status, $allowed, true)) {
    json_out(['ok' => false, 'error' => 'Choose a valid order status.'], 422);
}

$order = $pdo->prepare('SELECT id, status FROM orders WHERE id = ? AND store_id = ? LIMIT 1');
$order->execute([$orderId, $storeId]);
$row = $order->fetch();
if (!$row) {
    json_out(['ok' => false, 'error' => 'That order was not found for this store.'], 404);
}

$pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND store_id = ?')
    ->execute([$status, $orderId, $storeId]);

json_out(['ok' => true, 'status' => $status]);
