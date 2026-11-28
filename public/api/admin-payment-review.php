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
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM registration_payments WHERE reference = ? LIMIT 1 FOR UPDATE');
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

    $method = strtolower(trim((string) ($payment['payment_method'] ?? 'paystack')));
    if ($method !== 'transfer') {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => 'Only bank-transfer receipt payments can be approved here. Paystack payments are verified automatically.'], 422);
    }

    $proofPath = trim((string) ($payment['proof_path'] ?? ''));
    if ($proofPath === '' || resolve_registration_receipt_path($proofPath) === null) {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => 'A valid payment receipt is required before approving a transfer.'], 422);
    }

    $newStatus = $action === 'approve' ? 'paid' : 'rejected';
    $upd = $pdo->prepare(
        'UPDATE registration_payments
         SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
         WHERE reference = ? AND status = \'pending\''
    );
    $upd->execute([$newStatus, $note, (int) $admin['id'], $reference]);
    if ($upd->rowCount() !== 1) {
        $pdo->rollBack();
        payment_audit_log('transfer.review.failed', [
            'outcome' => 'already_reviewed',
            'reference' => $reference,
            'related_id' => (int) ($payment['id'] ?? 0),
            'admin_id' => (int) $admin['id'],
            'action' => $action,
        ]);
        json_out(['ok' => false, 'error' => 'Payment was already reviewed.'], 409);
    }

    $pdo->commit();
    payment_audit_log(
        $action === 'approve' ? 'transfer.approved' : 'transfer.rejected',
        [
            'outcome' => $newStatus,
            'reference' => $reference,
            'related_id' => (int) ($payment['id'] ?? 0),
            'admin_id' => (int) $admin['id'],
        ]
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    payment_audit_log('payment.processing.failed', [
        'outcome' => 'transfer_review_failed',
        'reference' => $reference,
        'admin_id' => (int) ($admin['id'] ?? 0),
    ]);
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
