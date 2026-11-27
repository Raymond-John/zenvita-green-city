<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$user = require_user('member');
$body = request_body();
$pdo = db();
ensure_order_payment_schema($pdo);

$productKey = trim((string) ($body['product'] ?? $body['package'] ?? ''));
$storeId = (int) ($body['storeId'] ?? 0);
$quantity = max(1, (int) ($body['quantity'] ?? 1));
$fulfillment = trim((string) ($body['fulfillment'] ?? ''));
$fullName = trim((string) ($body['fullName'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$email = trim((string) ($body['email'] ?? ($user['email'] ?? '')));
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
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Enter a valid email for Paystack payment.'], 422);
}
if (paystack_secret_key() === '' || paystack_public_key() === '') {
    json_out(['ok' => false, 'error' => 'Paystack keys are not configured. Ask an admin to add them in Settings → Payment.'], 503);
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

$unitPrice = round((float) $productRow['price'], 2);
$totalAmount = round($unitPrice * $quantity, 2);
if ($totalAmount < 100) {
    json_out(['ok' => false, 'error' => 'Order amount is too low for Paystack (minimum ₦100).'], 422);
}

$reference = 'ORD' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
$paymentReference = 'ZGV-ORD-' . date('ymdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
$callback = app_root_url() . '/api/paystack-order-callback';

try {
    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO orders
          (reference, member_id, store_id, product_id, quantity, unit_price, fulfillment, full_name, phone, email,
           delivery_address, delivery_city, delivery_notes, preferred_pickup_at, status,
           payment_status, payment_reference, paid_amount)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'Pending\', \'pending\', ?, 0)'
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
        $paymentReference,
    ]);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['ok' => false, 'error' => 'The order could not be saved.'], 500);
}

$init = paystack_request('POST', '/transaction/initialize', [
    'email' => $email,
    'amount' => (int) round($totalAmount * 100),
    'currency' => 'NGN',
    'reference' => $paymentReference,
    'callback_url' => $callback,
    'metadata' => [
        'purpose' => 'product_order',
        'order_id' => $orderId,
        'order_reference' => $reference,
        'product' => $productRow['name'],
        'quantity' => $quantity,
        'store_id' => $storeId,
        'member_id' => (int) $user['id'],
    ],
]);

if (empty($init['ok']) || empty($init['data']['authorization_url'])) {
    $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([$orderId]);
    json_out([
        'ok' => false,
        'error' => (string) ($init['message'] ?? $init['error'] ?? 'Could not start Paystack payment.'),
        'order' => [
            'id' => $orderId,
            'reference' => $reference,
            'paymentStatus' => 'failed',
        ],
    ], 502);
}

json_out([
    'ok' => true,
    'pay' => true,
    'authorizationUrl' => $init['data']['authorization_url'],
    'paymentReference' => $paymentReference,
    'publicKey' => paystack_public_key(),
    'amount' => $totalAmount,
    'order' => [
        'id' => $orderId,
        'reference' => $reference,
        'product' => $productRow['name'],
        'package' => $productRow['name'],
        'quantity' => $quantity,
        'unitPrice' => $unitPrice,
        'amount' => $totalAmount,
        'fulfillment' => $fulfillment,
        'store' => $storeRow['name'],
        'status' => 'Pending',
        'paymentStatus' => 'pending',
    ],
]);
