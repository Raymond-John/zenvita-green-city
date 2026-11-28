<?php
require __DIR__ . '/bootstrap.php';

ensure_order_payment_schema();
start_session();

$reference = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$orderUrl = app_root_url() . '/dashboard/order';

if ($reference === '') {
    payment_audit_log('paystack.verify.failed', [
        'outcome' => 'missing_reference',
        'purpose' => 'product_order',
    ]);
    header('Location: ' . $orderUrl . '?pay=failed&error=' . rawurlencode('Missing payment reference.'));
    exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'SELECT * FROM orders
         WHERE payment_reference = ?
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([$reference]);
    $order = $stmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        payment_audit_log('paystack.verify.failed', [
            'outcome' => 'order_missing',
            'reference' => $reference,
            'purpose' => 'product_order',
        ]);
        header('Location: ' . $orderUrl . '?pay=failed&error=' . rawurlencode('Order payment record not found.'));
        exit;
    }

    $orderRef = (string) $order['reference'];
    $expectedKobo = naira_to_kobo(((float) $order['unit_price']) * (int) $order['quantity']);

    if (($order['payment_status'] ?? '') === 'paid') {
        $pdo->commit();
        payment_audit_log('paystack.callback.duplicate', [
            'outcome' => 'already_paid',
            'reference' => $reference,
            'related_id' => (int) $order['id'],
            'purpose' => 'product_order',
        ]);
        header('Location: ' . $orderUrl . '?pay=success&ref=' . rawurlencode($orderRef));
        exit;
    }

    $verified = verify_paystack_reference($reference, [
        'reference' => (string) $order['payment_reference'],
        'amount_kobo' => $expectedKobo,
        'currency' => 'NGN',
        'purpose' => 'product_order',
    ]);
    if (empty($verified['ok'])) {
        $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'")
            ->execute([(int) $order['id']]);
        $pdo->commit();
        payment_audit_log('paystack.verify.failed', [
            'outcome' => 'verify_rejected',
            'reference' => $reference,
            'related_id' => (int) $order['id'],
            'purpose' => 'product_order',
            'reason' => (string) ($verified['error'] ?? 'Verification failed.'),
        ]);
        header('Location: ' . $orderUrl . '?pay=failed&error=' . rawurlencode($verified['error'] ?? 'Verification failed.') . '&ref=' . rawurlencode($orderRef));
        exit;
    }

    $paystackRef = (string) ($verified['reference'] ?? $reference);
    $paidAmount = round($expectedKobo / 100, 2);

    $upd = $pdo->prepare(
        "UPDATE orders
         SET payment_status = 'paid',
             paystack_reference = ?,
             paid_amount = ?,
             paid_at = NOW()
         WHERE id = ? AND payment_status <> 'paid'"
    );
    $upd->execute([$paystackRef, $paidAmount, (int) $order['id']]);

    if ($upd->rowCount() === 1) {
        $pdo->prepare(
            'INSERT IGNORE INTO order_notifications (store_id, order_id, is_read) VALUES (?, ?, 0)'
        )->execute([(int) $order['store_id'], (int) $order['id']]);

        $txRef = 'TX-ORD-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(8)));
        $pdo->prepare(
            'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
             VALUES (?, ?, \'Order\', ?, ?, \'Approved\', CURDATE())'
        )->execute([
            (int) $order['member_id'],
            $txRef,
            'Product order ' . $orderRef . ' paid',
            -$paidAmount,
        ]);
        payment_audit_log('order.payment.confirmed', [
            'outcome' => 'paid',
            'reference' => $reference,
            'related_id' => (int) $order['id'],
            'purpose' => 'product_order',
            'amount_kobo' => $expectedKobo,
        ]);
    } else {
        payment_audit_log('paystack.callback.duplicate', [
            'outcome' => 'update_race',
            'reference' => $reference,
            'related_id' => (int) $order['id'],
            'purpose' => 'product_order',
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    payment_audit_log('payment.processing.failed', [
        'outcome' => 'db_update_failed',
        'reference' => $reference,
        'purpose' => 'product_order',
    ]);
    header('Location: ' . $orderUrl . '?pay=failed&error=' . rawurlencode('Payment verified but order could not be updated.'));
    exit;
}

header('Location: ' . $orderUrl . '?pay=success&ref=' . rawurlencode($orderRef));
exit;
