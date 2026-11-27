<?php
require __DIR__ . '/bootstrap.php';

$user = require_user('member');
$pdo = db();
ensure_order_payment_schema($pdo);

$stores = $pdo->query(
    "SELECT s.id, s.name, s.address, s.city, s.phone, u.member_code AS store_code
     FROM stores s
     JOIN users u ON u.id = s.owner_user_id
     WHERE s.status = 'active'
     ORDER BY s.name"
)->fetchAll();

ensure_product_landing_columns();
$products = $pdo->query(
    "SELECT id, code, name, price, unit_label, description, image_path
     FROM products
     WHERE status = 'active'
     ORDER BY name"
)->fetchAll();

$stmt = $pdo->prepare(
    'SELECT o.reference, o.quantity, o.unit_price, o.fulfillment, o.full_name, o.phone, o.email,
            o.delivery_address, o.delivery_city, o.delivery_notes, o.preferred_pickup_at,
            o.status, o.payment_status, o.paid_amount, o.created_at, p.name AS product_name, s.name AS store_name
     FROM orders o
     JOIN products p ON p.id = o.product_id
     JOIN stores s ON s.id = o.store_id
     WHERE o.member_id = ?
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 50'
);
$stmt->execute([(int) $user['id']]);
$orders = [];
foreach ($stmt as $row) {
    $qty = (int) $row['quantity'];
    $unit = (float) $row['unit_price'];
    $paymentStatus = (string) ($row['payment_status'] ?? 'unpaid');
    $orders[] = [
        'reference' => $row['reference'],
        'product' => $row['product_name'],
        'package' => $row['product_name'], // backward-compatible label for older UI
        'quantity' => $qty,
        'unitPrice' => $unit,
        'amount' => round($unit * $qty, 2),
        'fulfillment' => $row['fulfillment'],
        'fulfillmentLabel' => $row['fulfillment'] === 'home_delivery' ? 'Home delivery' : 'Pickup',
        'store' => $row['store_name'],
        'status' => $row['status'],
        'paymentStatus' => $paymentStatus,
        'paymentLabel' => $paymentStatus === 'paid' ? 'Paid' : ($paymentStatus === 'pending' ? 'Awaiting payment' : ucfirst($paymentStatus)),
        'canPay' => !in_array($paymentStatus, ['paid'], true),
        'date' => date('d M Y H:i', strtotime($row['created_at'])),
        'deliveryAddress' => $row['delivery_address'],
        'deliveryCity' => $row['delivery_city'],
        'preferredPickupAt' => $row['preferred_pickup_at'],
    ];
}

json_out([
    'ok' => true,
    'user' => [
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'] ?? '',
        'phone' => $user['phone'] ?? '',
        'city' => $user['city'] ?? '',
    ],
    'stores' => array_map(static function ($row) {
        return [
            'id' => (int) $row['id'],
            'storeCode' => $row['store_code'] ?: '',
            'name' => $row['name'],
            'address' => $row['address'],
            'city' => $row['city'],
            'phone' => $row['phone'],
        ];
    }, $stores),
    'products' => array_map(static function ($row) {
        return [
            'id' => (int) $row['id'],
            'key' => $row['code'],
            'name' => $row['name'],
            'price' => (float) $row['price'],
            'unitLabel' => (string) $row['unit_label'],
            'description' => (string) ($row['description'] ?? ''),
            'imageUrl' => product_image_public_url($row['image_path'] ?? ''),
        ];
    }, $products),
    'orders' => $orders,
]);
