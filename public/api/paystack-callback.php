<?php
require __DIR__ . '/bootstrap.php';

ensure_registration_payments_table();
start_session();

$reference = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$registerUrl = app_root_url() . '/register';

if ($reference === '') {
    payment_audit_log('paystack.verify.failed', [
        'outcome' => 'missing_reference',
        'purpose' => 'registration',
    ]);
    header('Location: ' . $registerUrl . '?pay=failed&error=' . rawurlencode('Missing payment reference.'));
    exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM registration_payments WHERE reference = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$reference]);
    $payment = $stmt->fetch();

    if (!$payment) {
        $pdo->rollBack();
        payment_audit_log('paystack.verify.failed', [
            'outcome' => 'payment_missing',
            'reference' => $reference,
            'purpose' => 'registration',
        ]);
        header('Location: ' . $registerUrl . '?pay=failed&error=' . rawurlencode('Payment record not found.'));
        exit;
    }

    $sponsorQs = trim((string) ($payment['sponsor_code'] ?? ''));
    $sideQs = strtolower(trim((string) ($payment['placement_side'] ?? '')));
    if ($sponsorQs === '' && !empty($_SESSION['reg_sponsor'])) {
        $sponsorQs = (string) $_SESSION['reg_sponsor'];
    }
    if (!in_array($sideQs, ['left', 'right'], true) && !empty($_SESSION['reg_side'])) {
        $sideQs = (string) $_SESSION['reg_side'];
    }

    if (in_array($payment['status'], ['paid', 'registered', 'funded'], true)) {
        $pdo->commit();
        payment_audit_log('paystack.callback.duplicate', [
            'outcome' => 'already_' . (string) $payment['status'],
            'reference' => $reference,
            'related_id' => (int) ($payment['id'] ?? 0),
            'purpose' => 'registration',
        ]);
        $_SESSION['reg_payment_ref'] = $reference;
        if ($sponsorQs !== '') {
            $_SESSION['reg_sponsor'] = $sponsorQs;
        }
        if (in_array($sideQs, ['left', 'right'], true)) {
            $_SESSION['reg_side'] = $sideQs;
        }
        $qs = 'paid=1&ref=' . rawurlencode($reference);
        if ($sponsorQs !== '') {
            $qs .= '&sponsor=' . rawurlencode($sponsorQs);
        }
        if (in_array($sideQs, ['left', 'right'], true)) {
            $qs .= '&side=' . rawurlencode($sideQs);
        }
        header('Location: ' . $registerUrl . '?' . $qs);
        exit;
    }

    if (($payment['status'] ?? '') !== 'pending') {
        $pdo->rollBack();
        payment_audit_log('paystack.verify.failed', [
            'outcome' => 'invalid_status',
            'reference' => $reference,
            'related_id' => (int) ($payment['id'] ?? 0),
            'status' => (string) ($payment['status'] ?? ''),
            'purpose' => 'registration',
        ]);
        header('Location: ' . $registerUrl . '?pay=failed&error=' . rawurlencode('This payment cannot be completed.'));
        exit;
    }

    $verified = verify_paystack_reference($reference, [
        'reference' => $reference,
        'amount_kobo' => naira_to_kobo($payment['amount']),
        'currency' => 'NGN',
        'purpose' => 'registration',
    ]);
    if (empty($verified['ok'])) {
        $pdo->rollBack();
        payment_audit_log('paystack.verify.failed', [
            'outcome' => 'verify_rejected',
            'reference' => $reference,
            'related_id' => (int) ($payment['id'] ?? 0),
            'purpose' => 'registration',
            'reason' => (string) ($verified['error'] ?? 'Verification failed.'),
        ]);
        header('Location: ' . $registerUrl . '?pay=failed&error=' . rawurlencode($verified['error'] ?? 'Verification failed.'));
        exit;
    }

    $paystackRef = (string) ($verified['reference'] ?? $reference);
    $upd = $pdo->prepare(
        "UPDATE registration_payments
         SET status = 'paid', paystack_reference = ?, updated_at = NOW()
         WHERE reference = ? AND status = 'pending'"
    );
    $upd->execute([$paystackRef, $reference]);
    if ($upd->rowCount() !== 1) {
        $pdo->rollBack();
        payment_audit_log('paystack.callback.duplicate', [
            'outcome' => 'update_race',
            'reference' => $reference,
            'related_id' => (int) ($payment['id'] ?? 0),
            'purpose' => 'registration',
        ]);
        header('Location: ' . $registerUrl . '?pay=failed&error=' . rawurlencode('Payment was already processed.'));
        exit;
    }
    $pdo->commit();
    payment_audit_log('paystack.verify.success', [
        'outcome' => 'paid',
        'reference' => $reference,
        'related_id' => (int) ($payment['id'] ?? 0),
        'purpose' => 'registration',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    payment_audit_log('payment.processing.failed', [
        'outcome' => 'exception_rolled_back',
        'reference' => $reference,
        'purpose' => 'registration',
    ]);
    header('Location: ' . $registerUrl . '?pay=failed&error=' . rawurlencode('Payment verification failed.'));
    exit;
}

$_SESSION['reg_payment_ref'] = $reference;
if ($sponsorQs !== '') {
    $_SESSION['reg_sponsor'] = $sponsorQs;
}
if (in_array($sideQs, ['left', 'right'], true)) {
    $_SESSION['reg_side'] = $sideQs;
}
$qs = 'paid=1&ref=' . rawurlencode($reference);
if ($sponsorQs !== '') {
    $qs .= '&sponsor=' . rawurlencode($sponsorQs);
}
if (in_array($sideQs, ['left', 'right'], true)) {
    $qs .= '&side=' . rawurlencode($sideQs);
}
header('Location: ' . $registerUrl . '?' . $qs);
exit;
