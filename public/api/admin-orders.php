<?php
require __DIR__ . '/bootstrap.php';

require_user('admin');
$pdo = db();

$stores = $pdo->query(
    'SELECT s.id, s.name, s.address, s.city, s.phone, s.status, s.created_at,
            u.member_code, u.username, u.first_name, u.last_name, u.email, u.phone AS owner_phone
     FROM stores s
     JOIN users u ON u.id = s.owner_user_id
     ORDER BY s.created_at DESC, s.id DESC'
)->fetchAll();

$storeList = [];
foreach ($stores as $row) {
    $storeList[] = [
        'id' => (int) $row['id'],
        'storeCode' => $row['member_code'] ?: '',
        'name' => $row['name'],
        'address' => $row['address'],
        'city' => $row['city'],
        'phone' => $row['phone'],
        'status' => $row['status'],
        'owner' => trim($row['first_name'] . ' ' . $row['last_name']),
        'username' => $row['username'],
        'email' => $row['email'] ?? '',
        'ownerPhone' => $row['owner_phone'] ?? '',
        'created' => date('d M Y', strtotime($row['created_at'])),
    ];
}

$orders = $pdo->query(
    'SELECT o.id, o.reference, o.quantity, o.unit_price, o.fulfillment, o.full_name, o.phone, o.status, o.created_at,
            p.name AS product_name, s.name AS store_name, u.member_code
     FROM orders o
     JOIN products p ON p.id = o.product_id
     JOIN stores s ON s.id = o.store_id
     JOIN users u ON u.id = o.member_id
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 200'
)->fetchAll();

$orderList = [];
foreach ($orders as $row) {
    $orderList[] = [
        'id' => (int) $row['id'],
        'reference' => $row['reference'],
        'member' => $row['full_name'],
        'memberCode' => $row['member_code'] ?? '',
        'product' => $row['product_name'],
        'package' => $row['product_name'],
        'quantity' => (int) $row['quantity'],
        'unitPrice' => (float) $row['unit_price'],
        'fulfillmentLabel' => $row['fulfillment'] === 'home_delivery' ? 'Home delivery' : 'Pickup',
        'store' => $row['store_name'],
        'phone' => $row['phone'],
        'status' => $row['status'],
        'date' => date('d M Y H:i', strtotime($row['created_at'])),
    ];
}

json_out([
    'ok' => true,
    'stores' => $storeList,
    'orders' => $orderList,
]);
