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
$stmt = $pdo->prepare('SELECT * FROM registration_payments WHERE reference = ? LIMIT 1');
$stmt->execute([$reference]);
$payment = $stmt->fetch();

if (!$payment) {
    header('Location: ' . $registerUrl . '?pay=failed&error=' . rawurlencode('Payment record not found.'));
    exit;
}

if (in_array($payment['status'], ['paid', 'registered', 'funded'], true)) {
    $_SESSION['reg_payment_ref'] = $reference;
    $qs = 'paid=1&ref=' . rawurlencode($reference);
    if (!empty($_SESSION['reg_sponsor'])) {
        $qs .= '&sponsor=' . rawurlencode((string) $_SESSION['reg_sponsor']);
    }
    if (!empty($_SESSION['reg_side'])) {
        $qs .= '&side=' . rawurlencode((string) $_SESSION['reg_side']);
    }
    header('Location: ' . $registerUrl . '?' . $qs);
    exit;
}

$verified = verify_paystack_reference($reference);
if (empty($verified['ok'])) {
    header('Location: ' . $registerUrl . '?pay=failed&error=' . rawurlencode($verified['error'] ?? 'Verification failed.'));
    exit;
}

$data = $verified['data'];
$paidAmount = round(((float) ($data['amount'] ?? 0)) / 100, 2);
$expected = round((float) $payment['amount'], 2);
if ($paidAmount + 0.01 < $expected) {
    header('Location: ' . $registerUrl . '?pay=failed&error=' . rawurlencode('Paid amount does not match the package price.'));
    exit;
}

$paystackRef = (string) ($data['reference'] ?? $reference);
$pdo->prepare(
    "UPDATE registration_payments
     SET status = 'paid', paystack_reference = ?, updated_at = NOW()
     WHERE reference = ? AND status = 'pending'"
)->execute([$paystackRef, $reference]);

$_SESSION['reg_payment_ref'] = $reference;
$qs = 'paid=1&ref=' . rawurlencode($reference);
if (!empty($_SESSION['reg_sponsor'])) {
    $qs .= '&sponsor=' . rawurlencode((string) $_SESSION['reg_sponsor']);
}
if (!empty($_SESSION['reg_side'])) {
    $qs .= '&side=' . rawurlencode((string) $_SESSION['reg_side']);
}
header('Location: ' . $registerUrl . '?' . $qs);
exit;
