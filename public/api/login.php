<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$body = request_body();
$identity = trim((string) ($body['identity'] ?? ''));
$password = (string) ($body['password'] ?? '');
$requested = strtolower(trim((string) ($body['role'] ?? 'member')));
$allowedRoles = ['member', 'admin', 'store'];
$role = in_array($requested, $allowedRoles, true) ? $requested : 'member';

if ($identity === '' || strlen($password) < 8) {
    json_out(['ok' => false, 'error' => 'Enter your username and a password of at least 8 characters.'], 422);
}

$stmt = db()->prepare(
    'SELECT id, password_hash, role, status, email, username, first_name, last_name, member_code
     FROM users
     WHERE username = ? OR email = ? OR phone = ?
     LIMIT 1'
);
$stmt->execute([$identity, $identity, $identity]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_out(['ok' => false, 'error' => 'Those sign-in details do not match an account.'], 401);
}
if ($user['status'] === 'suspended') {
    json_out(['ok' => false, 'error' => 'This account is suspended.'], 403);
}
if ($user['role'] !== $role) {
    $hint = [
        'admin' => 'Use the portal sign-in for this account.',
        'store' => 'Use the store sign-in for this account.',
        'member' => 'Use the member sign-in for this account.',
    ];
    json_out(['ok' => false, 'error' => $hint[$user['role']] ?? 'Use the correct sign-in page for this account.'], 403);
}

start_session();
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['role'] = $user['role'];

$loginAlert = ['ok' => false, 'sent' => 0];
try {
    ensure_smtp_settings();
    $loginAlert = notify_login_alerts($user);
} catch (Throwable $e) {
    $loginAlert = ['ok' => false, 'sent' => 0, 'error' => 'Login alert email failed.'];
}

json_out([
    'ok' => true,
    'role' => $user['role'],
    'loginAlertSent' => !empty($loginAlert['ok']),
    'loginAlertCount' => (int) ($loginAlert['sent'] ?? 0),
]);
