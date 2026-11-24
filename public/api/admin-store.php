<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

require_user('admin');
$body = request_body();
$action = trim((string) ($body['action'] ?? 'create'));
$pdo = db();

if ($action === 'update') {
    $storeId = (int) ($body['storeId'] ?? 0);
    $name = trim((string) ($body['storeName'] ?? ''));
    $address = trim((string) ($body['address'] ?? ''));
    $city = trim((string) ($body['city'] ?? ''));
    $phone = trim((string) ($body['phone'] ?? ''));
    $status = trim((string) ($body['status'] ?? 'active'));
    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }
    if ($storeId < 1 || $name === '') {
        json_out(['ok' => false, 'error' => 'Enter the store name.'], 422);
    }
    $stmt = $pdo->prepare('UPDATE stores SET name = ?, address = ?, city = ?, phone = ?, status = ? WHERE id = ?');
    $stmt->execute([$name, $address, $city, $phone, $status, $storeId]);
    if ($stmt->rowCount() < 1) {
        $check = $pdo->prepare('SELECT id FROM stores WHERE id = ?');
        $check->execute([$storeId]);
        if (!$check->fetch()) {
            json_out(['ok' => false, 'error' => 'That store was not found.'], 404);
        }
    }
    json_out(['ok' => true]);
}

if ($action === 'status') {
    $storeId = (int) ($body['storeId'] ?? 0);
    $status = trim((string) ($body['status'] ?? ''));
    if ($storeId < 1 || !in_array($status, ['active', 'inactive'], true)) {
        json_out(['ok' => false, 'error' => 'Choose a valid store status.'], 422);
    }
    $stmt = $pdo->prepare('UPDATE stores SET status = ? WHERE id = ?');
    $stmt->execute([$status, $storeId]);
    json_out(['ok' => true]);
}

// create
$firstName = trim((string) ($body['firstName'] ?? ''));
$lastName = trim((string) ($body['lastName'] ?? ''));
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');
$email = trim((string) ($body['email'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$storeName = trim((string) ($body['storeName'] ?? ''));
$address = trim((string) ($body['address'] ?? ''));
$city = trim((string) ($body['city'] ?? ''));

if ($firstName === '' || $storeName === '') {
    json_out(['ok' => false, 'error' => 'Enter the owner first name and store name.'], 422);
}
if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
    json_out(['ok' => false, 'error' => 'Use a username with at least 3 letters or numbers.'], 422);
}
if (strlen($password) < 8) {
    json_out(['ok' => false, 'error' => 'Use a password with at least 8 characters.'], 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
}

$exists = $pdo->prepare('SELECT id FROM users WHERE username = ? OR (? <> \'\' AND email = ?)');
$exists->execute([$username, $email, $email]);
if ($exists->fetch()) {
    json_out(['ok' => false, 'error' => 'That username or email is already in use.'], 422);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO users (member_code, username, email, phone, password_hash, first_name, last_name, role, status, city, country)
         VALUES (NULL, ?, ?, ?, ?, ?, ?, \'store\', \'active\', ?, ?)'
    )->execute([
        $username,
        $email !== '' ? $email : null,
        $phone !== '' ? $phone : null,
        $hash,
        $firstName,
        $lastName,
        $city,
        'Nigeria',
    ]);
    $ownerId = (int) $pdo->lastInsertId();
    $storeCode = 'ZGS' . str_pad((string) $ownerId, 5, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE users SET member_code = ? WHERE id = ?')->execute([$storeCode, $ownerId]);
    $pdo->prepare(
        'INSERT INTO stores (owner_user_id, name, address, city, phone, status)
         VALUES (?, ?, ?, ?, ?, \'active\')'
    )->execute([$ownerId, $storeName, $address, $city, $phone]);
    $storeId = (int) $pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['ok' => false, 'error' => 'The store could not be created.'], 500);
}

json_out([
    'ok' => true,
    'storeId' => $storeId,
    'ownerId' => $ownerId,
    'storeCode' => $storeCode,
]);
