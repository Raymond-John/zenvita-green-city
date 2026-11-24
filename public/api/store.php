<?php
require __DIR__ . '/bootstrap.php';

$user = require_user('store');
$pdo = db();
ensure_store_sales_bonus_schema($pdo);

$storeStmt = $pdo->prepare('SELECT * FROM stores WHERE owner_user_id = ? LIMIT 1');
$storeStmt->execute([(int) $user['id']]);
$store = $storeStmt->fetch();
if (!$store) {
    json_out(['ok' => false, 'error' => 'No store is linked to this account.'], 404);
}

$storeId = (int) $store['id'];
// Catch up any missed awards if sales already crossed a threshold.
maybe_award_store_sales_bonuses($storeId, $pdo);
$salesBonus = store_sales_bonus_summary($storeId, (int) $user['id'], $pdo);

$ordersStmt = $pdo->prepare(
    'SELECT o.id, o.reference, o.quantity, o.unit_price, o.fulfillment, o.full_name, o.phone, o.email,
            o.delivery_address, o.delivery_city, o.delivery_notes, o.preferred_pickup_at,
            o.status, o.created_at, p.name AS product_name,
            u.member_code, u.username
     FROM orders o
     JOIN products p ON p.id = o.product_id
     JOIN users u ON u.id = o.member_id
     WHERE o.store_id = ?
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 100'
);
$ordersStmt->execute([$storeId]);
$orders = [];
$pending = 0;
$accepted = 0;
$ready = 0;
$completed = 0;
foreach ($ordersStmt as $row) {
    if ($row['status'] === 'Pending') {
        $pending++;
    } elseif ($row['status'] === 'Accepted') {
        $accepted++;
    } elseif ($row['status'] === 'Ready') {
        $ready++;
    } elseif ($row['status'] === 'Completed') {
        $completed++;
    }
    $orders[] = [
        'id' => (int) $row['id'],
        'reference' => $row['reference'],
        'member' => trim($row['full_name']),
        'memberCode' => $row['member_code'] ?: $row['username'],
        'product' => $row['product_name'],
        'package' => $row['product_name'],
        'quantity' => (int) $row['quantity'],
        'unitPrice' => (float) $row['unit_price'],
        'fulfillment' => $row['fulfillment'],
        'fulfillmentLabel' => $row['fulfillment'] === 'home_delivery' ? 'Home delivery' : 'Pickup',
        'phone' => $row['phone'],
        'email' => $row['email'],
        'deliveryAddress' => $row['delivery_address'],
        'deliveryCity' => $row['delivery_city'],
        'deliveryNotes' => $row['delivery_notes'],
        'preferredPickupAt' => $row['preferred_pickup_at'],
        'status' => $row['status'],
        'date' => date('d M Y H:i', strtotime($row['created_at'])),
    ];
}

$notifStmt = $pdo->prepare(
    'SELECT n.id, n.is_read, n.created_at, o.reference, o.status, o.fulfillment, p.name AS product_name
     FROM order_notifications n
     JOIN orders o ON o.id = n.order_id
     JOIN products p ON p.id = o.product_id
     WHERE n.store_id = ?
     ORDER BY n.created_at DESC, n.id DESC
     LIMIT 20'
);
$notifStmt->execute([$storeId]);
$notifications = [];
$unread = 0;
foreach ($notifStmt as $row) {
    if (!(int) $row['is_read']) {
        $unread++;
    }
    $notifications[] = [
        'id' => (int) $row['id'],
        'isRead' => (bool) ((int) $row['is_read']),
        'reference' => $row['reference'],
        'product' => $row['product_name'],
        'package' => $row['product_name'],
        'status' => $row['status'],
        'fulfillmentLabel' => $row['fulfillment'] === 'home_delivery' ? 'Home delivery' : 'Pickup',
        'date' => date('d M Y H:i', strtotime($row['created_at'])),
        'text' => 'New booking ' . $row['reference'] . ' · ' . $row['product_name'],
    ];
}

json_out([
    'ok' => true,
    'store' => [
        'id' => $storeId,
        'name' => $store['name'],
        'address' => $store['address'],
        'city' => $store['city'],
        'phone' => $store['phone'],
        'status' => $store['status'],
    ],
    'owner' => [
        'name' => trim($user['first_name'] . ' ' . $user['last_name']),
        'username' => $user['username'],
        'storeCode' => $user['member_code'] ?? '',
    ],
    'stats' => [
        'pending' => $pending,
        'accepted' => $accepted,
        'ready' => $ready,
        'completed' => $completed,
        'total' => count($orders),
        'unread' => $unread,
    ],
    'salesBonus' => $salesBonus,
    'orders' => $orders,
    'notifications' => $notifications,
]);
