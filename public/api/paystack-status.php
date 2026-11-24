<?php
require __DIR__ . '/bootstrap.php';

ensure_registration_payments_table();
start_session();

$reference = trim((string) ($_GET['reference'] ?? $_GET['ref'] ?? ''));
if ($reference === '') {
    json_out(['ok' => false, 'error' => 'Missing payment reference.'], 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM registration_payments WHERE reference = ? LIMIT 1');
$stmt->execute([$reference]);
$payment = $stmt->fetch();
if (!$payment) {
    json_out(['ok' => false, 'error' => 'Payment not found.'], 404);
}

if ($payment['status'] === 'pending') {
    $verified = verify_paystack_reference($reference);
    if (!empty($verified['ok'])) {
        $data = $verified['data'];
        $paidAmount = round(((float) ($data['amount'] ?? 0)) / 100, 2);
        $expected = round((float) $payment['amount'], 2);
        if ($paidAmount + 0.01 >= $expected) {
            $pdo->prepare(
                "UPDATE registration_payments
                 SET status = 'paid', paystack_reference = ?, updated_at = NOW()
                 WHERE reference = ? AND status = 'pending'"
            )->execute([(string) ($data['reference'] ?? $reference), $reference]);
            $payment['status'] = 'paid';
            $payment['paystack_reference'] = (string) ($data['reference'] ?? $reference);
        }
    }
}

if (!in_array($payment['status'], ['paid', 'registered', 'funded'], true)) {
    json_out(['ok' => false, 'paid' => false, 'error' => 'Payment is not completed yet.']);
}

if ($payment['status'] === 'registered' || $payment['status'] === 'funded') {
    json_out(['ok' => false, 'paid' => false, 'error' => 'This payment was already used to create an account.']);
}

$_SESSION['reg_payment_ref'] = $reference;

json_out([
    'ok' => true,
    'paid' => true,
    'reference' => $payment['reference'],
    'email' => $payment['email'],
    'amount' => (float) $payment['amount'],
    'package' => $payment['package_name'],
]);
