<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$user = require_user('member');
$body = request_body();
$first = trim((string) ($body['firstName'] ?? ''));
$last = trim((string) ($body['lastName'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$city = trim((string) ($body['city'] ?? ''));
$country = trim((string) ($body['country'] ?? ''));
$bank = trim((string) ($body['bank'] ?? ''));
$accountName = trim((string) ($body['accountName'] ?? ''));
$accountNumber = preg_replace('/\D+/', '', (string) ($body['accountNumber'] ?? ''));

if ($first === '' || $last === '') {
    json_out(['ok' => false, 'error' => 'Enter your first and last name.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
}
if (strlen(preg_replace('/\D+/', '', $phone)) < 10) {
    json_out(['ok' => false, 'error' => 'Enter a phone number with at least 10 digits.'], 422);
}
if (strlen($accountNumber) !== 10) {
    json_out(['ok' => false, 'error' => 'Enter a 10-digit account number.'], 422);
}

$taken = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
$taken->execute([$email, $user['id']]);
if ($taken->fetch()) {
    json_out(['ok' => false, 'error' => 'That email is already used by another account.'], 422);
}

$stmt = db()->prepare(
    'UPDATE users
     SET first_name = ?, last_name = ?, email = ?, phone = ?, city = ?, country = ?,
         bank_name = ?, account_name = ?, account_number = ?
     WHERE id = ?'
);
$stmt->execute([$first, $last, $email, $phone, $city, $country, $bank, $accountName, $accountNumber, $user['id']]);

json_out(['ok' => true, 'user' => current_user()]);

