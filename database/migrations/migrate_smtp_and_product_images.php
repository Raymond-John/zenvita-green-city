<?php
/**
 * Adds SMTP settings keys and products.image_path.
 * Run: C:\xampp\php\php.exe database/migrate_smtp_and_product_images.php
 */
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();

try {
    $pdo->exec('ALTER TABLE settings MODIFY setting_value TEXT NOT NULL');
    fwrite(STDOUT, "settings.setting_value widened to TEXT.\n");
} catch (Throwable $e) {
    fwrite(STDOUT, "settings.setting_value already compatible.\n");
}

$keys = [
    'smtp_provider' => 'hostinger',
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => '465',
    'smtp_encryption' => 'ssl',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_from_email' => '',
    'smtp_from_name' => 'Cellvigor Biorevive LTD',
];
$stmt = $pdo->prepare(
    'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_key = setting_key'
);
foreach ($keys as $key => $value) {
    $stmt->execute([$key, $value]);
}
fwrite(STDOUT, "SMTP settings keys ensured.\n");

ensure_product_landing_columns($pdo);
fwrite(STDOUT, "Product image_path column ensured.\n");
fwrite(STDOUT, "Migration complete.\n");
