<?php
/**
 * Assigns a store ID (member_code) to every store user.
 * Format: ZGS00001, ZGS00002, ...
 * Run: php database/migrations/migrate_store_ids.php
 */
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();
$rows = $pdo->query(
    "SELECT id, member_code, username
     FROM users
     WHERE role = 'store'
     ORDER BY id ASC"
)->fetchAll();

$update = $pdo->prepare('UPDATE users SET member_code = ? WHERE id = ?');
$assigned = 0;

foreach ($rows as $row) {
    $code = trim((string) ($row['member_code'] ?? ''));
    if ($code !== '' && preg_match('/^ZGS\d+$/', $code)) {
        fwrite(STDOUT, "Store user {$row['username']} already has {$code}.\n");
        continue;
    }
    $code = 'ZGS' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT);
    $update->execute([$code, (int) $row['id']]);
    $assigned++;
    fwrite(STDOUT, "Assigned {$code} to store user {$row['username']}.\n");
}

fwrite(STDOUT, "Store ID migration complete. Assigned {$assigned} ID(s).\n");
