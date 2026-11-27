<?php
require __DIR__ . '/bootstrap.php';

ensure_registration_payments_table();
start_session();

$reference = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$registerUrl = app_root_url() . '/register';

if ($reference === '') {
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
        header('Location: ' . $registerUrl . '?pay=failed&error=' . rawurlencode('Payment was already processed.'));
        exit;
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
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
