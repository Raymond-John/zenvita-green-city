<?php
/**
 * Creates registration_payments table for Paystack pay-before-register.
 * Run: php database/migrations/migrate_payments.php
 */
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS registration_payments (
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

fwrite(STDOUT, "registration_payments table ready.\n");

$pdo->prepare(
    "INSERT INTO settings (setting_key, setting_value)
     VALUES ('paystack_public_key', ''), ('paystack_secret_key', '')
     ON DUPLICATE KEY UPDATE setting_key = setting_key"
)->execute();

fwrite(STDOUT, "Paystack settings keys ensured.\n");
fwrite(STDOUT, "Migration complete.\n");
