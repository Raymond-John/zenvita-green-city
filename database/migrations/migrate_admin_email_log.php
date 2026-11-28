<?php
/**
 * Admin email send-log table (CLI only).
 *
 * Default: dry-run (no DDL).
 * Apply:   php database/migrations/migrate_admin_email_log.php --apply --i-understand-ddl
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$argvList = array_slice($argv ?? [], 1);
$apply = in_array('--apply', $argvList, true);
$confirm = in_array('--i-understand-ddl', $argvList, true);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

$pdo = db();
$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$expectDb = getenv('ZENVITA_MIGRATE_EXPECT_DB') !== false ? (string) getenv('ZENVITA_MIGRATE_EXPECT_DB') : '';

echo "=== migrate_admin_email_log ===\n";
echo 'Mode: ' . ($apply ? 'apply' : 'dry-run') . "\n";

if ($expectDb !== '' && !hash_equals($expectDb, $dbName)) {
    fwrite(STDERR, "REFUSED: database identity does not match ZENVITA_MIGRATE_EXPECT_DB.\n");
    exit(1);
}

$exists = (bool) $pdo->query("SHOW TABLES LIKE 'admin_email_log'")->fetch();
echo $exists ? "Stage A: admin_email_log present\n" : "Stage A: admin_email_log MISSING (would CREATE)\n";

if (!$apply) {
    echo "Dry-run complete. No DDL executed. Re-run with --apply --i-understand-ddl to apply.\n";
    exit(0);
}
if (!$confirm) {
    fwrite(STDERR, "REFUSED: apply requires --i-understand-ddl.\n");
    exit(1);
}

if ($exists) {
    fwrite(STDOUT, "admin_email_log already exists — no CREATE executed.\n");
    exit(0);
}

$pdo->exec(
    "CREATE TABLE admin_email_log (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        template_code VARCHAR(40) NOT NULL DEFAULT 'custom',
        recipient_email VARCHAR(120) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body_text MEDIUMTEXT NOT NULL,
        status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
        error_message VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_admin_email_log_admin (admin_id),
        KEY idx_admin_email_log_user (user_id),
        KEY idx_admin_email_log_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
fwrite(STDOUT, "Created admin_email_log.\n");
