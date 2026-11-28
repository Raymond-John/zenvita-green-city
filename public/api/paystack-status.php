<?php
require __DIR__ . '/bootstrap.php';

ensure_registration_payments_table();
start_session();

$reference = trim((string) ($_GET['reference'] ?? $_GET['ref'] ?? ''));
if ($reference === '') {
    payment_audit_log('paystack.status.failed', [
        'outcome' => 'missing_reference',
        'purpose' => 'registration',
    ]);
    json_out(['ok' => false, 'error' => 'Missing payment reference.'], 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM registration_payments WHERE reference = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$reference]);
    $payment = $stmt->fetch();
    if (!$payment) {
        $pdo->rollBack();
        payment_audit_log('paystack.status.failed', [
            'outcome' => 'payment_missing',
            'reference' => $reference,
            'purpose' => 'registration',
        ]);
        json_out(['ok' => false, 'error' => 'Payment not found.'], 404);
    }

    if (in_array($payment['status'], ['paid', 'registered', 'funded'], true)
        && !($payment['status'] === 'pending')) {
        // Already past pending — treat as duplicate status poll when paid+.
        if ($payment['status'] === 'paid') {
            payment_audit_log('paystack.status.duplicate', [
                'outcome' => 'already_paid',
                'reference' => $reference,
                'related_id' => (int) ($payment['id'] ?? 0),
                'purpose' => 'registration',
            ]);
        }
    }

    if ($payment['status'] === 'pending' && ($payment['payment_method'] ?? 'paystack') === 'paystack') {
        $verified = verify_paystack_reference($reference, [
            'reference' => $reference,
            'amount_kobo' => naira_to_kobo($payment['amount']),
            'currency' => 'NGN',
            'purpose' => 'registration',
        ]);
        if (!empty($verified['ok'])) {
            $upd = $pdo->prepare(
                "UPDATE registration_payments
                 SET status = 'paid', paystack_reference = ?, updated_at = NOW()
                 WHERE reference = ? AND status = 'pending'"
            );
            $upd->execute([(string) ($verified['reference'] ?? $reference), $reference]);
            if ($upd->rowCount() === 1) {
                $payment['status'] = 'paid';
                $payment['paystack_reference'] = (string) ($verified['reference'] ?? $reference);
                payment_audit_log('paystack.status.paid', [
                    'outcome' => 'paid',
                    'reference' => $reference,
                    'related_id' => (int) ($payment['id'] ?? 0),
                    'purpose' => 'registration',
                ]);
            } else {
                $stmt->execute([$reference]);
                $payment = $stmt->fetch() ?: $payment;
                payment_audit_log('paystack.status.duplicate', [
                    'outcome' => 'update_race',
                    'reference' => $reference,
                    'related_id' => (int) ($payment['id'] ?? 0),
                    'purpose' => 'registration',
                ]);
            }
        } else {
            payment_audit_log('paystack.status.failed', [
                'outcome' => 'verify_rejected',
                'reference' => $reference,
                'related_id' => (int) ($payment['id'] ?? 0),
                'purpose' => 'registration',
                'reason' => (string) ($verified['error'] ?? 'Verification failed.'),
            ]);
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    payment_audit_log('payment.processing.failed', [
        'outcome' => 'status_db_failed',
        'reference' => $reference,
        'purpose' => 'registration',
    ]);
    json_out(['ok' => false, 'error' => 'Could not verify payment status.'], 500);
}

if (!in_array($payment['status'], ['paid', 'registered', 'funded'], true)) {
    json_out(['ok' => false, 'paid' => false, 'error' => 'Payment is not completed yet.']);
}

if ($payment['status'] === 'registered' || $payment['status'] === 'funded') {
    json_out(['ok' => false, 'paid' => false, 'error' => 'This payment was already used to create an account.']);
}

$_SESSION['reg_payment_ref'] = $reference;
$sponsor = trim((string) ($payment['sponsor_code'] ?? ''));
$side = strtolower(trim((string) ($payment['placement_side'] ?? '')));
if ($sponsor !== '') {
    $_SESSION['reg_sponsor'] = $sponsor;
}
if (in_array($side, ['left', 'right'], true)) {
    $_SESSION['reg_side'] = $side;
}

json_out([
    'ok' => true,
    'paid' => true,
    'reference' => $payment['reference'],
    'email' => $payment['email'],
    'amount' => (float) $payment['amount'],
    'package' => $payment['package_name'],
    'sponsor' => $sponsor,
    'side' => in_array($side, ['left', 'right'], true) ? $side : '',
]);
