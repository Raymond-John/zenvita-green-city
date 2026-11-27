<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$user = require_user('member');
$body = request_body();
$pdo = db();
ensure_order_payment_schema($pdo);

$orderRef = trim((string) ($body['reference'] ?? ''));
if ($orderRef === '') {
    json_out(['ok' => false, 'error' => 'Missing order reference.'], 422);
}
if (paystack_secret_key() === '' || paystack_public_key() === '') {
    json_out(['ok' => false, 'error' => 'Paystack keys are not configured. Ask an admin to add them in Settings → Payment.'], 503);
}

$stmt = $pdo->prepare(
    'SELECT o.*, p.name AS product_name
     FROM orders o
     JOIN products p ON p.id = o.product_id
     WHERE o.reference = ? AND o.member_id = ?
     LIMIT 1'
);
$stmt->execute([$orderRef, (int) $user['id']]);
$order = $stmt->fetch();
if (!$order) {
    json_out(['ok' => false, 'error' => 'Order not found.'], 404);
}
if (($order['payment_status'] ?? '') === 'paid') {
    json_out(['ok' => true, 'alreadyPaid' => true, 'order' => ['reference' => $order['reference'], 'paymentStatus' => 'paid']]);
}

$email = trim((string) ($order['email'] ?: ($user['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Add a valid email on the order or your profile before paying.'], 422);
}

$totalAmount = round(((float) $order['unit_price']) * (int) $order['quantity'], 2);
if ($totalAmount < 100) {
    json_out(['ok' => false, 'error' => 'Order amount is too low for Paystack.'], 422);
}

$paymentReference = 'ZGV-ORD-' . date('ymdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
$callback = app_root_url() . '/api/paystack-order-callback';

$pdo->prepare(
    "UPDATE orders
     SET payment_status = 'pending', payment_reference = ?, paystack_reference = NULL
     WHERE id = ?"
)->execute([$paymentReference, (int) $order['id']]);

$init = paystack_request('POST', '/transaction/initialize', [
    'email' => $email,
    'amount' => (int) round($totalAmount * 100),
    'currency' => 'NGN',
    'reference' => $paymentReference,
    'callback_url' => $callback,
    'metadata' => [
        'purpose' => 'product_order',
        'order_id' => (int) $order['id'],
        'order_reference' => $order['reference'],
        'product' => $order['product_name'],
        'quantity' => (int) $order['quantity'],
        'member_id' => (int) $user['id'],
    ],
]);

if (empty($init['ok']) || empty($init['data']['authorization_url'])) {
    $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'")
        ->execute([(int) $order['id']]);
    json_out([
        'ok' => false,
        'error' => (string) ($init['message'] ?? $init['error'] ?? 'Could not start Paystack payment.'),
    ], 502);
}

json_out([
    'ok' => true,
    'pay' => true,
    'authorizationUrl' => $init['data']['authorization_url'],
    'paymentReference' => $paymentReference,
    'amount' => $totalAmount,
    'order' => [
        'reference' => $order['reference'],
        'paymentStatus' => 'pending',
    ],
]);
