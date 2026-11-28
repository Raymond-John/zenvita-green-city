<?php
/**
 * Admin member email compose / send API.
 * GET  — templates, SMTP status, recent log, optional member list
 * POST — send branded email to one member, many members, or all active members
 */
require __DIR__ . '/bootstrap.php';

$admin = require_user('admin');
ensure_smtp_settings();
ensure_admin_email_log_schema();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $templates = array_values(admin_email_templates());
    $members = $pdo->query(
        "SELECT id, member_code, username, email, first_name, last_name, status, role
         FROM users
         WHERE role = 'member'
           AND email IS NOT NULL AND TRIM(email) <> ''
         ORDER BY first_name ASC, last_name ASC, id ASC
         LIMIT 2000"
    )->fetchAll();

    $list = [];
    foreach ($members as $row) {
        $list[] = [
            'id' => (int) $row['id'],
            'memberCode' => (string) ($row['member_code'] ?? ''),
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'name' => trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? ''))),
            'status' => (string) ($row['status'] ?? ''),
            'firstName' => (string) ($row['first_name'] ?? ''),
            'lastName' => (string) ($row['last_name'] ?? ''),
        ];
    }

    $recent = [];
    try {
        $rows = $pdo->query(
            'SELECT id, user_id, template_code, recipient_email, subject, status, error_message, created_at
             FROM admin_email_log
             ORDER BY id DESC
             LIMIT 30'
        )->fetchAll();
        foreach ($rows as $row) {
            $recent[] = [
                'id' => (int) $row['id'],
                'userId' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
                'template' => (string) $row['template_code'],
                'email' => (string) $row['recipient_email'],
                'subject' => (string) $row['subject'],
                'status' => (string) $row['status'],
                'error' => (string) ($row['error_message'] ?? ''),
                'createdAt' => (string) $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        $recent = [];
    }

    json_out([
        'ok' => true,
        'smtpConfigured' => smtp_is_configured(),
        'templates' => $templates,
        'members' => $list,
        'recent' => $recent,
        'placeholders' => [
            '{{first_name}}',
            '{{last_name}}',
            '{{full_name}}',
            '{{username}}',
            '{{member_code}}',
            '{{email}}',
            '{{site_name}}',
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use GET or POST.'], 405);
}

if (!smtp_is_configured()) {
    json_out([
        'ok' => false,
        'error' => 'SMTP is not configured. Open Portal → Settings → Notifications / SMTP and save Hostinger mailbox details first.',
    ], 503);
}

$body = request_body();
$templateCode = trim((string) ($body['template'] ?? 'custom'));
$subject = trim((string) ($body['subject'] ?? ''));
$title = trim((string) ($body['title'] ?? ''));
$bodyText = (string) ($body['body'] ?? '');
$audience = strtolower(trim((string) ($body['audience'] ?? 'selected')));
$userIds = $body['userIds'] ?? [];
if (!is_array($userIds)) {
    $userIds = [];
}
$userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn ($id) => $id > 0)));

if ($subject === '') {
    json_out(['ok' => false, 'error' => 'Enter an email subject.'], 422);
}
if (trim($bodyText) === '') {
    json_out(['ok' => false, 'error' => 'Enter the email message body.'], 422);
}
if ($title === '') {
    $title = $subject;
}
if (!admin_email_template($templateCode) && $templateCode !== 'custom') {
    $templateCode = 'custom';
}

$recipients = [];
if ($audience === 'all_active') {
    $stmt = $pdo->query(
        "SELECT id, member_code, username, email, first_name, last_name, status
         FROM users
         WHERE role = 'member'
           AND status = 'active'
           AND email IS NOT NULL AND TRIM(email) <> ''
         ORDER BY id ASC
         LIMIT 2000"
    );
    $recipients = $stmt->fetchAll();
} else {
    if ($userIds === []) {
        json_out(['ok' => false, 'error' => 'Select at least one member, or choose all active members.'], 422);
    }
    if (count($userIds) > 200) {
        json_out(['ok' => false, 'error' => 'Select at most 200 members per send.'], 422);
    }
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, member_code, username, email, first_name, last_name, status
         FROM users
         WHERE role = 'member' AND id IN ($placeholders)"
    );
    $stmt->execute($userIds);
    $recipients = $stmt->fetchAll();
}

if ($recipients === []) {
    json_out(['ok' => false, 'error' => 'No matching members with email addresses were found.'], 422);
}

$sent = 0;
$failed = 0;
$errors = [];
$adminId = (int) $admin['id'];

foreach ($recipients as $user) {
    $result = send_admin_member_email($user, $subject, $title, $bodyText);
    $email = trim((string) ($user['email'] ?? ''));
    $ok = !empty($result['ok']);
    log_admin_email_send(
        $pdo,
        $adminId,
        (int) ($user['id'] ?? 0) ?: null,
        $templateCode,
        $email,
        apply_email_placeholders($subject, $user),
        apply_email_placeholders($bodyText, $user),
        $ok,
        (string) ($result['error'] ?? '')
    );
    if ($ok) {
        $sent++;
    } else {
        $failed++;
        if (count($errors) < 5) {
            $errors[] = ($user['username'] ?? ('#' . $user['id'])) . ': ' . ($result['error'] ?? 'Send failed');
        }
    }
}

json_out([
    'ok' => $sent > 0,
    'sent' => $sent,
    'failed' => $failed,
    'total' => count($recipients),
    'error' => $sent > 0 ? null : ($errors[0] ?? 'No emails were sent.'),
    'errors' => $errors,
    'message' => $sent > 0
        ? ('Sent ' . $sent . ' email' . ($sent === 1 ? '' : 's') . ($failed > 0 ? (' (' . $failed . ' failed)') : '') . '.')
        : 'No emails were sent.',
]);
