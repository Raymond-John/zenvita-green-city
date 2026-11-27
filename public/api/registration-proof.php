<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

ensure_registration_payments_table();
start_session();

$email = trim((string) ($_POST['email'] ?? ''));
$packageName = trim((string) ($_POST['package'] ?? ''));
$sponsorHint = trim((string) ($_POST['sponsor'] ?? ''));
$sideHint = strtolower(trim((string) ($_POST['side'] ?? 'left')));
$notes = trim((string) ($_POST['notes'] ?? ''));
if (!in_array($sideHint, ['left', 'right'], true)) {
    $sideHint = 'left';
}
if (strlen($notes) > 255) {
    $notes = substr($notes, 0, 255);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
    json_out(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
}
if ($packageName === '') {
    json_out(['ok' => false, 'error' => 'Choose a package.'], 422);
}
if ($sponsorHint === '') {
    json_out(['ok' => false, 'error' => 'Enter your sponsor referral code or username.'], 422);
}
if (empty($_FILES['receipt']) || !is_array($_FILES['receipt'])) {
    json_out(['ok' => false, 'error' => 'Upload a payment receipt (image or PDF).'], 422);
}

$pdo = db();
$emailTaken = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$emailTaken->execute([$email]);
if ($emailTaken->fetch()) {
    json_out(['ok' => false, 'error' => 'That email already has an account. Sign in instead.'], 422);
}

$sponsor = $pdo->prepare("SELECT id, status FROM users WHERE (username = ? OR member_code = ?) AND role = 'member' LIMIT 1");
$sponsor->execute([$sponsorHint, $sponsorHint]);
$sponsorRow = $sponsor->fetch();
if (!$sponsorRow) {
    json_out(['ok' => false, 'error' => 'That sponsor code or username was not found.'], 422);
}
if ($sponsorRow['status'] !== 'active') {
    json_out(['ok' => false, 'error' => 'That sponsor account is currently inactive.'], 422);
}

$package = $pdo->prepare('SELECT id, name, price FROM packages WHERE name = ? OR code = ? LIMIT 1');
$package->execute([$packageName, strtolower($packageName)]);
$packageRow = $package->fetch();
if (!$packageRow) {
    json_out(['ok' => false, 'error' => 'Choose a valid package.'], 422);
}

$amount = round((float) $packageRow['price'], 2);
if ($amount < 1) {
    json_out(['ok' => false, 'error' => 'Package amount is invalid.'], 422);
}

try {
    $proofPath = store_registration_receipt_upload($_FILES['receipt']);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 422);
}

$reference = 'ZGV-TRF-' . date('ymdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

$pdo->prepare(
    'INSERT INTO registration_payments
        (reference, email, amount, package_id, package_name, payment_method, proof_path, notes, status)
     VALUES (?, ?, ?, ?, ?, \'transfer\', ?, ?, \'pending\')'
)->execute([
    $reference,
    $email,
    $amount,
    (int) $packageRow['id'],
    $packageRow['name'],
    $proofPath,
    $notes,
]);

$_SESSION['reg_sponsor'] = $sponsorHint;
$_SESSION['reg_side'] = $sideHint;
$_SESSION['reg_payment_ref'] = $reference;

json_out([
    'ok' => true,
    'pending' => true,
    'reference' => $reference,
    'email' => $email,
    'amount' => $amount,
    'package' => $packageRow['name'],
    'message' => 'Receipt submitted. An admin must approve it before you can complete registration.',
]);
