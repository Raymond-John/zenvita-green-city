<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

require_user('admin');
ensure_smtp_settings();
$body = request_body();
$values = [
    'site_name' => trim((string) ($body['siteName'] ?? 'Cellvigor Biorevive LTD')),
    'currency' => trim((string) ($body['currency'] ?? 'NGN')),
    'support_email' => trim((string) ($body['supportEmail'] ?? '')),
    'direct_rate' => (string) max(0, (float) ($body['directRate'] ?? 30)),
    'pair_pv' => (string) max(0, (int) ($body['pairPv'] ?? 60)),
    'min_withdrawal' => (string) max(0, (float) ($body['minWithdrawal'] ?? 1000)),
    'store_sales_target' => (string) max(1, (float) ($body['storeSalesTarget'] ?? 3000000)),
    'store_sales_bonus' => (string) max(0, (float) ($body['storeSalesBonus'] ?? 0)),
    'binary_qualification' => !empty($body['binaryQualification']) ? '1' : '0',
    'email_alerts' => !empty($body['emailAlerts']) ? '1' : '0',
    'sms_alerts' => !empty($body['smsAlerts']) ? '1' : '0',
    'paystack_public_key' => trim((string) ($body['paystackPublicKey'] ?? '')),
    'smtp_provider' => 'hostinger',
    'smtp_host' => trim((string) ($body['smtpHost'] ?? 'smtp.hostinger.com')) ?: 'smtp.hostinger.com',
    'smtp_port' => (string) max(1, (int) ($body['smtpPort'] ?? 465)),
    'smtp_encryption' => strtolower(trim((string) ($body['smtpEncryption'] ?? 'ssl'))),
    'smtp_username' => trim((string) ($body['smtpUsername'] ?? '')),
    'smtp_from_email' => trim((string) ($body['smtpFromEmail'] ?? '')),
    'smtp_from_name' => trim((string) ($body['smtpFromName'] ?? 'Cellvigor Biorevive LTD')),
];

if (!in_array($values['smtp_encryption'], ['tls', 'ssl', 'none'], true)) {
    $values['smtp_encryption'] = 'ssl';
}
if ($values['smtp_from_email'] !== '' && !filter_var($values['smtp_from_email'], FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Enter a valid SMTP from email.'], 422);
}

$secretIncoming = trim((string) ($body['paystackSecretKey'] ?? ''));
if ($secretIncoming !== '') {
    $values['paystack_secret_key'] = $secretIncoming;
}

$smtpPassword = (string) ($body['smtpPassword'] ?? '');
if ($smtpPassword !== '') {
    $values['smtp_password'] = $smtpPassword;
}

$stmt = db()->prepare(
    'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
);
foreach ($values as $key => $value) {
    $stmt->execute([$key, $value]);
}

json_out(['ok' => true, 'smtpConfigured' => smtp_is_configured()]);
