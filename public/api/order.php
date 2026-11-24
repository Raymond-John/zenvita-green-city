<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$user = require_user('member');
$body = request_body();
$pdo = db();

$productKey = trim((string) ($body['product'] ?? $body['package'] ?? ''));
$storeId = (int) ($body['storeId'] ?? 0);
$quantity = max(1, (int) ($body['quantity'] ?? 1));
$fulfillment = trim((string) ($body['fulfillment'] ?? ''));
$fullName = trim((string) ($body['fullName'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$deliveryAddress = trim((string) ($body['deliveryAddress'] ?? ''));
$deliveryCity = trim((string) ($body['deliveryCity'] ?? ''));
$deliveryNotes = trim((string) ($body['deliveryNotes'] ?? ''));
$preferredPickup = trim((string) ($body['preferredPickupAt'] ?? ''));

if (!in_array($fulfillment, ['home_delivery', 'pickup'], true)) {
    json_out(['ok' => false, 'error' => 'Choose home delivery or pickup.'], 422);
}
if ($fullName === '' || $phone === '') {
    json_out(['ok' => false, 'error' => 'Enter your full name and phone number.'], 422);
}
if ($fulfillment === 'home_delivery' && ($deliveryAddress === '' || $deliveryCity === '')) {
    json_out(['ok' => false, 'error' => 'Enter the delivery address and city.'], 422);
}

$product = $pdo->prepare(
    "SELECT id, name, price FROM products
     WHERE status = 'active' AND (code = ? OR name = ?)
     LIMIT 1"
);
$product->execute([$productKey, $productKey]);
$productRow = $product->fetch();
if (!$productRow) {
    json_out(['ok' => false, 'error' => 'Choose a product.'], 422);
}

$store = $pdo->prepare("SELECT id, name FROM stores WHERE id = ? AND status = 'active' LIMIT 1");
$store->execute([$storeId]);
$storeRow = $store->fetch();
if (!$storeRow) {
    json_out(['ok' => false, 'error' => 'Choose an available store.'], 422);
}

$reference = 'ORD' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
$unitPrice = (float) $productRow['price'];

try {
    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO orders
          (reference, member_id, store_id, product_id, quantity, unit_price, fulfillment, full_name, phone, email,
           delivery_address, delivery_city, delivery_notes, preferred_pickup_at, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'Pending\')'
    )->execute([
        $reference,
        (int) $user['id'],
        $storeId,
        (int) $productRow['id'],
        $quantity,
        $unitPrice,
        $fulfillment,
        $fullName,
        $phone,
        $email,
        $fulfillment === 'home_delivery' ? $deliveryAddress : '',
        $fulfillment === 'home_delivery' ? $deliveryCity : '',
        $deliveryNotes,
        $fulfillment === 'pickup' ? $preferredPickup : '',
    ]);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO order_notifications (store_id, order_id, is_read) VALUES (?, ?, 0)'
    )->execute([$storeId, $orderId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['ok' => false, 'error' => 'The order could not be saved.'], 500);
}

json_out([
    'ok' => true,
    'order' => [
        'id' => $orderId,
        'reference' => $reference,
        'product' => $productRow['name'],
        'package' => $productRow['name'],
        'quantity' => $quantity,
        'unitPrice' => $unitPrice,
        'fulfillment' => $fulfillment,
        'store' => $storeRow['name'],
        'status' => 'Pending',
    ],
]);
