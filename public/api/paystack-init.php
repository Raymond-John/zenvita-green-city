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
if ($sponsorHint === '') {
    json_out(['ok' => false, 'error' => 'Enter your sponsor referral code or username.'], 422);
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
$amountKobo = naira_to_kobo($amount);
if ($amountKobo < 10000) {
    json_out(['ok' => false, 'error' => 'Package amount is too low for Paystack.'], 422);
}

$reference = 'ZGV-REG-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(6)));
$callback = app_root_url() . '/api/paystack-callback';

$pdo->prepare(
    'INSERT INTO registration_payments
        (reference, email, amount, package_id, package_name, sponsor_code, placement_side, payment_method, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, \'paystack\', \'pending\')'
)->execute([
    $reference,
    $email,
    $amount,
    (int) $packageRow['id'],
    $packageRow['name'],
    $sponsorHint,
    $sideHint,
]);
$paymentId = (int) $pdo->lastInsertId();

$_SESSION['reg_sponsor'] = $sponsorHint;
$_SESSION['reg_side'] = $sideHint;

$init = paystack_request('POST', '/transaction/initialize', [
    'email' => $email,
    'amount' => $amountKobo,
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
    // Remove the unfinished pending row so failed init does not look like a live attempt.
    $pdo->prepare(
        "DELETE FROM registration_payments
         WHERE id = ? AND status = 'pending' AND (paystack_reference IS NULL OR paystack_reference = '')"
    )->execute([$paymentId]);
    payment_audit_log('paystack.init.failed', [
        'outcome' => 'init_failed',
        'reference' => $reference,
        'related_id' => $paymentId,
        'purpose' => 'registration',
    ]);
    json_out([
        'ok' => false,
        'error' => (string) ($init['message'] ?? $init['error'] ?? 'Could not start Paystack payment.'),
    ], 502);
}

payment_audit_log('paystack.init', [
    'outcome' => 'initialized',
    'reference' => $reference,
    'related_id' => $paymentId,
    'purpose' => 'registration',
    'amount_kobo' => $amountKobo,
]);

json_out([
    'ok' => true,
    'authorizationUrl' => $init['data']['authorization_url'],
    'reference' => $reference,
    'publicKey' => paystack_public_key(),
    'amount' => $amount,
    'package' => $packageRow['name'],
]);
