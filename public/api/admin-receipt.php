<?php
require __DIR__ . '/bootstrap.php';

require_user('admin');
ensure_registration_payments_table();

$reference = trim((string) ($_GET['reference'] ?? ''));
if ($reference === '') {
    json_out(['ok' => false, 'error' => 'Missing payment reference.'], 422);
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT reference, payment_method, proof_path, status
     FROM registration_payments
     WHERE reference = ?
     LIMIT 1'
);
$stmt->execute([$reference]);
$payment = $stmt->fetch();
if (!$payment) {
    json_out(['ok' => false, 'error' => 'Payment record not found.'], 404);
}

$proofPath = trim((string) ($payment['proof_path'] ?? ''));
if ($proofPath === '') {
    json_out(['ok' => false, 'error' => 'No receipt is attached to this payment.'], 404);
}

$absolute = resolve_registration_receipt_path($proofPath);
if ($absolute === null) {
    json_out(['ok' => false, 'error' => 'Receipt file could not be found securely.'], 404);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($absolute) ?: 'application/octet-stream';
$allowed = [
    'image/jpeg' => true,
    'image/png' => true,
    'image/webp' => true,
    'application/pdf' => true,
];
if (empty($allowed[$mime])) {
    json_out(['ok' => false, 'error' => 'Unsupported receipt file type.'], 415);
}

$downloadName = basename($absolute);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($absolute));
header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($absolute);
exit;
