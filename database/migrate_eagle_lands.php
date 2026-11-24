<?php
/**
 * Adds Eagle Lands & Properties reserve wallet + withdrawal reserve tracking.
 * Run: php database/migrate_eagle_lands.php
 */
require_once __DIR__ . '/../public/api/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();

$walletCols = $pdo->query('SHOW COLUMNS FROM wallets')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('eagle_lands_balance', $walletCols, true)) {
    $pdo->exec('ALTER TABLE wallets ADD COLUMN eagle_lands_balance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER available_balance');
    fwrite(STDOUT, "Added wallets.eagle_lands_balance.\n");
} else {
    fwrite(STDOUT, "wallets.eagle_lands_balance already exists.\n");
}

$wdCols = $pdo->query('SHOW COLUMNS FROM withdrawals')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('reserved_amount', $wdCols, true)) {
    $pdo->exec('ALTER TABLE withdrawals ADD COLUMN reserved_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount');
    fwrite(STDOUT, "Added withdrawals.reserved_amount.\n");
} else {
    fwrite(STDOUT, "withdrawals.reserved_amount already exists.\n");
}

$pdo->prepare(
    "INSERT INTO settings (setting_key, setting_value)
     VALUES ('withdraw_day', 'saturday'), ('withdraw_cash_percent', '40'), ('withdraw_reserve_percent', '60')
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
)->execute();

fwrite(STDOUT, "Eagle Lands withdrawal policy settings saved.\n");
fwrite(STDOUT, "Migration complete.\n");
