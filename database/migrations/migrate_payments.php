<?php
/**
 * Legacy registration_payments migrator (CLI only).
 *
 * Default: dry-run inspection (no DDL).
 * Apply:   php database/migrations/migrate_payments.php --apply --i-understand-ddl
 *
 * Prefer database/migrations/2026_phase1d_schema_readiness.php for full schema readiness.
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

echo "=== migrate_payments (legacy) ===\n";
echo 'Mode: ' . ($apply ? 'apply' : 'dry-run') . "\n";
echo "Note: connected database name is not printed.\n";

if ($expectDb !== '' && !hash_equals($expectDb, $dbName)) {
    fwrite(STDERR, "REFUSED: database identity does not match ZENVITA_MIGRATE_EXPECT_DB.\n");
    exit(1);
}

$exists = (bool) $pdo->query("SHOW TABLES LIKE 'registration_payments'")->fetch();
echo $exists
    ? "Stage A: registration_payments present\n"
    : "Stage A: registration_payments MISSING (would CREATE)\n";

if (!$apply) {
    echo "Dry-run complete. No DDL executed. Re-run with --apply --i-understand-ddl to apply.\n";
    exit(0);
}

if (!$confirm) {
    fwrite(STDERR, "REFUSED: apply requires --i-understand-ddl.\n");
    exit(1);
}

if ($exists) {
    fwrite(STDOUT, "registration_payments already exists — no CREATE executed.\n");
} else {
    $pdo->exec(
        "CREATE TABLE registration_payments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(64) NOT NULL,
            paystack_reference VARCHAR(64) NULL,
            email VARCHAR(120) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            package_id INT UNSIGNED NOT NULL,
            package_name VARCHAR(80) NOT NULL,
            status ENUM('pending','paid','registered','funded') NOT NULL DEFAULT 'pending',
            user_id INT UNSIGNED NULL,
            funded_by INT UNSIGNED NULL,
            funded_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_reg_pay_reference (reference),
            KEY idx_reg_pay_status (status),
            KEY idx_reg_pay_email (email),
            KEY idx_reg_pay_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    fwrite(STDOUT, "Created registration_payments.\n");
}

$pdo->prepare(
    "INSERT INTO settings (setting_key, setting_value)
     VALUES ('paystack_public_key', ''), ('paystack_secret_key', '')
     ON DUPLICATE KEY UPDATE setting_key = setting_key"
)->execute();

fwrite(STDOUT, "Paystack settings keys ensured.\n");
fwrite(STDOUT, "Migration complete.\n");
