<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$user = require_user('member');
$body = request_body();
$current = (string) ($body['currentPassword'] ?? '');
$next = (string) ($body['newPassword'] ?? '');

if (strlen($current) < 8 || strlen($next) < 8) {
    json_out(['ok' => false, 'error' => 'Use at least 8 characters.'], 422);
}

$stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$hash = (string) $stmt->fetchColumn();
if (!password_verify($current, $hash)) {
    json_out(['ok' => false, 'error' => 'The current password does not match.'], 422);
}

$update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$update->execute([password_hash($next, PASSWORD_DEFAULT), $user['id']]);
json_out(['ok' => true]);
