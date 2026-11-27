<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$admin = require_user('admin');
ensure_registration_payments_table();
$body = request_body();
$reference = trim((string) ($body['reference'] ?? ''));
$action = strtolower(trim((string) ($body['action'] ?? '')));
$note = trim((string) ($body['note'] ?? ''));
if (strlen($note) > 255) {
    $note = substr($note, 0, 255);
}

if ($reference === '' || !in_array($action, ['approve', 'reject'], true)) {
    json_out(['ok' => false, 'error' => 'Choose a payment and approve or reject it.'], 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM registration_payments WHERE reference = ? LIMIT 1 FOR UPDATE');
$pdo->beginTransaction();
try {
    $stmt->execute([$reference]);
    $payment = $stmt->fetch();
    if (!$payment) {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => 'Payment record not found.'], 404);
    }
    if ($payment['status'] !== 'pending') {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => 'Only pending payments can be reviewed.'], 422);
    }
    if (($payment['payment_method'] ?? 'paystack') !== 'transfer' && empty($payment['proof_path'])) {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => 'This record is not a transfer receipt awaiting review.'], 422);
    }

    $newStatus = $action === 'approve' ? 'paid' : 'rejected';
    $pdo->prepare(
        'UPDATE registration_payments
         SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
         WHERE reference = ? AND status = \'pending\''
    )->execute([$newStatus, $note, (int) $admin['id'], $reference]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['ok' => false, 'error' => 'Could not update the payment.'], 500);
}

json_out([
    'ok' => true,
    'reference' => $reference,
    'status' => $newStatus,
    'message' => $action === 'approve'
        ? 'Payment approved. The member can now complete registration with this reference.'
        : 'Payment receipt rejected.',
]);
