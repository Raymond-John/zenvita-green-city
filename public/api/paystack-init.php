<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

ensure_registration_payments_table();
start_session();

$body = request_body();
$email = trim((string) ($body['email'] ?? ''));
$packageName = trim((string) ($body['package'] ?? ''));
$sponsorHint = trim((string) ($body['sponsor'] ?? ''));
$sideHint = strtolower(trim((string) ($body['side'] ?? 'left')));
if (!in_array($sideHint, ['left', 'right'], true)) {
    $sideHint = 'left';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
    json_out(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
}
if ($packageName === '') {
    json_out(['ok' => false, 'error' => 'Choose a package.'], 422);
}
if (paystack_secret_key() === '' || paystack_public_key() === '') {
    json_out(['ok' => false, 'error' => 'Paystack keys are not configured. Ask an admin to add them in Settings → Payment.'], 503);
}

$pdo = db();
$emailTaken = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$emailTaken->execute([$email]);
if ($emailTaken->fetch()) {
    json_out(['ok' => false, 'error' => 'That email already has an account. Sign in instead.'], 422);
}

$package = $pdo->prepare('SELECT id, name, price FROM packages WHERE name = ? OR code = ? LIMIT 1');
$package->execute([$packageName, strtolower($packageName)]);
$packageRow = $package->fetch();
if (!$packageRow) {
    json_out(['ok' => false, 'error' => 'Choose a valid package.'], 422);
}

$amount = round((float) $packageRow['price'], 2);
if ($amount < 100) {
    json_out(['ok' => false, 'error' => 'Package amount is too low for Paystack.'], 422);
}

$reference = 'ZGV-REG-' . date('ymdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
$callback = app_root_url() . '/api/paystack-callback';

$pdo->prepare(
    'INSERT INTO registration_payments
        (reference, email, amount, package_id, package_name, payment_method, status)
     VALUES (?, ?, ?, ?, ?, \'paystack\', \'pending\')'
)->execute([
    $reference,
    $email,
    $amount,
    (int) $packageRow['id'],
    $packageRow['name'],
]);

$_SESSION['reg_sponsor'] = $sponsorHint;
$_SESSION['reg_side'] = $sideHint;

$init = paystack_request('POST', '/transaction/initialize', [
    'email' => $email,
    'amount' => (int) round($amount * 100),
    'currency' => 'NGN',
    'reference' => $reference,
    'callback_url' => $callback,
    'metadata' => [
        'package' => $packageRow['name'],
        'package_id' => (int) $packageRow['id'],
        'purpose' => 'registration',
        'sponsor' => $sponsorHint,
        'side' => $sideHint,
    ],
]);

if (empty($init['ok']) || empty($init['data']['authorization_url'])) {
    $pdo->prepare("UPDATE registration_payments SET status = 'pending' WHERE reference = ?")->execute([$reference]);
    json_out([
        'ok' => false,
        'error' => (string) ($init['message'] ?? $init['error'] ?? 'Could not start Paystack payment.'),
    ], 502);
}

json_out([
    'ok' => true,
    'authorizationUrl' => $init['data']['authorization_url'],
    'reference' => $reference,
    'publicKey' => paystack_public_key(),
    'amount' => $amount,
    'package' => $packageRow['name'],
]);
