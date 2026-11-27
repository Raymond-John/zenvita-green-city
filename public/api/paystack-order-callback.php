<?php
require __DIR__ . '/bootstrap.php';

ensure_order_payment_schema();
start_session();

$reference = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$orderUrl = app_root_url() . '/dashboard/order';

if ($reference === '') {
    header('Location: ' . $orderUrl . '?pay=failed&error=' . rawurlencode('Missing payment reference.'));
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM orders WHERE payment_reference = ? OR paystack_reference = ? LIMIT 1');
$stmt->execute([$reference, $reference]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ' . $orderUrl . '?pay=failed&error=' . rawurlencode('Order payment record not found.'));
    exit;
}

$orderRef = (string) $order['reference'];

if (($order['payment_status'] ?? '') === 'paid') {
    header('Location: ' . $orderUrl . '?pay=success&ref=' . rawurlencode($orderRef));
    exit;
}

$verified = verify_paystack_reference($reference);
if (empty($verified['ok'])) {
    $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'")
        ->execute([(int) $order['id']]);
    header('Location: ' . $orderUrl . '?pay=failed&error=' . rawurlencode($verified['error'] ?? 'Verification failed.') . '&ref=' . rawurlencode($orderRef));
    exit;
}

$data = $verified['data'];
$paidAmount = round(((float) ($data['amount'] ?? 0)) / 100, 2);
$expected = round(((float) $order['unit_price']) * (int) $order['quantity'], 2);
if ($paidAmount + 0.01 < $expected) {
    $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'")
        ->execute([(int) $order['id']]);
    header('Location: ' . $orderUrl . '?pay=failed&error=' . rawurlencode('Paid amount does not match the order total.') . '&ref=' . rawurlencode($orderRef));
    exit;
}

$paystackRef = (string) ($data['reference'] ?? $reference);

try {
    $pdo->beginTransaction();
    $upd = $pdo->prepare(
        "UPDATE orders
         SET payment_status = 'paid',
             paystack_reference = ?,
             paid_amount = ?,
             paid_at = NOW()
         WHERE id = ? AND payment_status <> 'paid'"
    );
    $upd->execute([$paystackRef, $paidAmount, (int) $order['id']]);

    if ($upd->rowCount() > 0) {
        $exists = $pdo->prepare('SELECT id FROM order_notifications WHERE order_id = ? LIMIT 1');
        $exists->execute([(int) $order['id']]);
        if (!$exists->fetch()) {
            $pdo->prepare(
                'INSERT INTO order_notifications (store_id, order_id, is_read) VALUES (?, ?, 0)'
            )->execute([(int) $order['store_id'], (int) $order['id']]);
        }

        // Record a member transaction for the paid product order.
        $txRef = 'TX-ORD-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $pdo->prepare(
            'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
             VALUES (?, ?, \'Order\', ?, ?, \'Approved\', CURDATE())'
        )->execute([
            (int) $order['member_id'],
            $txRef,
            'Product order ' . $orderRef . ' paid',
            -$paidAmount,
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . $orderUrl . '?pay=failed&error=' . rawurlencode('Payment verified but order could not be updated.') . '&ref=' . rawurlencode($orderRef));
    exit;
}

header('Location: ' . $orderUrl . '?pay=success&ref=' . rawurlencode($orderRef));
exit;
