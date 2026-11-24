<?php
/**
 * Ensures store sales bonus settings + awards table.
 * Run: C:\xampp\php\php.exe database/migrations/migrate_store_sales_bonus.php
 */
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ensure_store_sales_bonus_schema();
$config = store_sales_bonus_config();
fwrite(STDOUT, "Store sales bonus schema ready.\n");
fwrite(STDOUT, "Target: " . $config['target'] . "\n");
fwrite(STDOUT, "Bonus: " . $config['bonus'] . "\n");
fwrite(STDOUT, "Migration complete.\n");
