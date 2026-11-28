<?php

/**
 * Core PHP application helpers (not Laravel).
 * Loaded via bootstrap/app.php and public/api/bootstrap.php.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

$config = require BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Services/Mailer.php';

/**
 * Environments that may enable debug / runtime DDL when explicitly configured.
 */
function app_dev_like_environments(): array
{
    return ['local', 'development', 'dev', 'testing'];
}

function normalize_app_env(string $env): string
{
    $env = strtolower(trim($env));
    if ($env === '') {
        return 'production';
    }
    $allowed = ['local', 'development', 'dev', 'testing', 'staging', 'production'];
    if (!in_array($env, $allowed, true)) {
        return 'production';
    }
    return $env;
}

function app_env_is_production_like(string $env): bool
{
    return !in_array(normalize_app_env($env), app_dev_like_environments(), true);
}

/**
 * Pure config resolver (unit-testable). Does not read files or the process environment itself.
 *
 * @param array<string,mixed> $fileConfig   From config/app.php
 * @param array<string,mixed>|null $localConfig From config/app.local.php when present
 * @param array<string,string|bool|null> $envVars Keys: ZENVITA_APP_ENV, ZENVITA_APP_DEBUG, ZENVITA_ALLOW_RUNTIME_DDL
 * @return array<string,mixed>
 */
function resolve_app_config(array $fileConfig, ?array $localConfig, array $envVars = []): array
{
    $defaults = [
        'name' => 'Zenvita Green City',
        'env' => 'production',
        'debug' => false,
        'url' => '',
        'allow_runtime_ddl' => false,
    ];
    $merged = array_merge($defaults, $fileConfig);
    if (is_array($localConfig)) {
        $merged = array_merge($merged, $localConfig);
    }

    if (array_key_exists('ZENVITA_APP_ENV', $envVars) && $envVars['ZENVITA_APP_ENV'] !== null && $envVars['ZENVITA_APP_ENV'] !== '') {
        $merged['env'] = (string) $envVars['ZENVITA_APP_ENV'];
    }
    if (array_key_exists('ZENVITA_APP_DEBUG', $envVars) && $envVars['ZENVITA_APP_DEBUG'] !== null && $envVars['ZENVITA_APP_DEBUG'] !== '') {
        $merged['debug'] = filter_var($envVars['ZENVITA_APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists('ZENVITA_ALLOW_RUNTIME_DDL', $envVars) && $envVars['ZENVITA_ALLOW_RUNTIME_DDL'] !== null && $envVars['ZENVITA_ALLOW_RUNTIME_DDL'] !== '') {
        $merged['allow_runtime_ddl'] = filter_var($envVars['ZENVITA_ALLOW_RUNTIME_DDL'], FILTER_VALIDATE_BOOLEAN);
    }

    $merged['env'] = normalize_app_env((string) ($merged['env'] ?? 'production'));
    $merged['debug'] = (bool) ($merged['debug'] ?? false);
    $merged['allow_runtime_ddl'] = (bool) ($merged['allow_runtime_ddl'] ?? false);

    // Hard locks: production-like environments never get runtime DDL or debug,
    // even if an env var or local file mistakenly requests them.
    if (app_env_is_production_like((string) $merged['env'])) {
        $merged['allow_runtime_ddl'] = false;
        $merged['debug'] = false;
    }

    return $merged;
}

function load_app_config_from_disk(): array
{
    $file = [];
    $path = BASE_PATH . '/config/app.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $file = $loaded;
        }
    }
    $local = null;
    $localPath = BASE_PATH . '/config/app.local.php';
    if (is_file($localPath)) {
        $loadedLocal = require $localPath;
        if (is_array($loadedLocal)) {
            $local = $loadedLocal;
        }
    }
    $envVars = [];
    foreach (['ZENVITA_APP_ENV', 'ZENVITA_APP_DEBUG', 'ZENVITA_ALLOW_RUNTIME_DDL'] as $key) {
        $val = getenv($key);
        if ($val !== false) {
            $envVars[$key] = $val;
        }
    }
    return resolve_app_config($file, $local, $envVars);
}

function app_settings(bool $reload = false): array
{
    static $app = null;
    if (isset($GLOBALS['__zenvita_app_settings_override']) && is_array($GLOBALS['__zenvita_app_settings_override'])) {
        return $GLOBALS['__zenvita_app_settings_override'];
    }
    if ($reload) {
        $app = null;
    }
    if (!is_array($app)) {
        $app = load_app_config_from_disk();
    }
    return $app;
}

/** @internal tests only ? bypasses disk/env loader. */
function app_settings_set_for_tests(array $config): void
{
    $GLOBALS['__zenvita_app_settings_override'] = resolve_app_config($config, null, []);
}

function app_settings_clear_test_override(): void
{
    unset($GLOBALS['__zenvita_app_settings_override']);
    app_settings(true);
}

/**
 * Runtime CREATE/ALTER is allowed only for explicit local/dev/testing config.
 * Production-like environments NEVER allow runtime DDL.
 */
function app_allows_runtime_ddl(): bool
{
    $app = app_settings();
    $env = normalize_app_env((string) ($app['env'] ?? 'production'));
    if (app_env_is_production_like($env)) {
        return false;
    }
    return !empty($app['allow_runtime_ddl']);
}

function app_debug(): bool
{
    $app = app_settings();
    $env = normalize_app_env((string) ($app['env'] ?? 'production'));
    if (app_env_is_production_like($env)) {
        return false;
    }
    return !empty($app['debug']);
}

function app_env(): string
{
    return normalize_app_env((string) (app_settings()['env'] ?? 'production'));
}

function schema_missing(string $code, string $detail = ''): void
{
    payment_audit_log('schema.missing', [
        'outcome' => 'missing',
        'error_code' => $code,
        'detail' => $detail !== '' ? $detail : null,
    ]);
    throw new RuntimeException($code);
}

/**
 * Run DDL only when local runtime DDL is enabled; otherwise fail safely.
 */
function apply_runtime_ddl(string $missingCode, callable $ddl): void
{
    if (!app_allows_runtime_ddl()) {
        schema_missing($missingCode, 'Runtime DDL is disabled for this environment.');
    }
    $ddl();
}

function db(): PDO
{
    static $pdo = null;
    static $boundName = null;
    global $config;
    $name = getenv('ZENVITA_DB_NAME') ?: (string) ($config['name'] ?? '');
    if ($pdo instanceof PDO && $boundName === $name) {
        return $pdo;
    }
    $pdo = new PDO(
        'mysql:host=' . $config['host'] . ';dbname=' . $name . ';charset=utf8mb4',
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $boundName = $name;
    return $pdo;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = json_encode([
            'ok' => false,
            'error' => 'Response encoding failed.',
        ]);
    }
    echo $json;
    exit;
}

function request_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (is_array($data)) {
        return $data;
    }
    return $_POST;
}

function ensure_package_description_column(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();
    $col = $pdo->query("SHOW COLUMNS FROM packages LIKE 'description'")->fetch();
    if (!$col) {
        apply_runtime_ddl('SCHEMA_PACKAGE_DESCRIPTION_MISSING', static function () use ($pdo): void {
            $pdo->exec("ALTER TABLE packages ADD COLUMN description TEXT NOT NULL DEFAULT '' AFTER name");
        });
    }
    $done = true;
}

/**
 * Payment columns for member product orders (Paystack).
 * Existing bookings (pre-payment gate) are marked paid so stores keep them.
 */
function ensure_order_payment_schema(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();
    $exists = $pdo->query("SHOW TABLES LIKE 'orders'")->fetch();
    if (!$exists) {
        $done = true;
        return;
    }
    $cols = $pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN);
    $needCols = ['payment_status', 'payment_reference', 'paystack_reference', 'paid_amount', 'paid_at'];
    $missingCols = array_values(array_filter($needCols, static fn ($c) => !in_array($c, $cols, true)));
    $indexes = $pdo->query('SHOW INDEX FROM orders')->fetchAll(PDO::FETCH_ASSOC);
    $indexNames = [];
    foreach ($indexes as $idx) {
        $indexNames[(string) $idx['Key_name']] = true;
    }
    $missingIdx = [];
    if (empty($indexNames['uq_orders_payment_reference'])) {
        $missingIdx[] = 'uq_orders_payment_reference';
    }
    if (empty($indexNames['uq_orders_paystack_reference'])) {
        $missingIdx[] = 'uq_orders_paystack_reference';
    }

    if ($missingCols !== [] || $missingIdx !== []) {
        apply_runtime_ddl('SCHEMA_ORDER_PAYMENT_MISSING', static function () use ($pdo, $cols, $indexNames): void {
            if (!in_array('payment_status', $cols, true)) {
                $pdo->exec(
                    "ALTER TABLE orders
                     ADD COLUMN payment_status ENUM('unpaid','pending','paid','failed') NOT NULL DEFAULT 'unpaid'
                     AFTER status"
                );
                $cols[] = 'payment_status';
            }
            if (!in_array('payment_reference', $cols, true)) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN payment_reference VARCHAR(64) NULL DEFAULT NULL AFTER payment_status');
            }
            if (!in_array('paystack_reference', $cols, true)) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN paystack_reference VARCHAR(64) NULL DEFAULT NULL AFTER payment_reference');
            }
            if (!in_array('paid_amount', $cols, true)) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER paystack_reference');
            }
            if (!in_array('paid_at', $cols, true)) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN paid_at DATETIME NULL DEFAULT NULL AFTER paid_amount');
            }
            // Never silently convert unpaid orders to paid when adding columns.
            if (empty($indexNames['uq_orders_payment_reference'])) {
                $pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_payment_reference (payment_reference)');
            }
            if (empty($indexNames['uq_orders_paystack_reference'])) {
                $pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_paystack_reference (paystack_reference)');
            }
        });
    }

    $notifExists = $pdo->query("SHOW TABLES LIKE 'order_notifications'")->fetch();
    if ($notifExists) {
        $nIndexes = $pdo->query('SHOW INDEX FROM order_notifications')->fetchAll(PDO::FETCH_ASSOC);
        $nNames = [];
        foreach ($nIndexes as $idx) {
            $nNames[(string) $idx['Key_name']] = true;
        }
        if (empty($nNames['uq_order_notifications_order'])) {
            $dup = (int) $pdo->query(
                'SELECT COUNT(*) FROM (
                    SELECT order_id FROM order_notifications GROUP BY order_id HAVING COUNT(*) > 1
                 ) d'
            )->fetchColumn();
            if ($dup === 0) {
                apply_runtime_ddl('SCHEMA_ORDER_NOTIFICATIONS_INDEX_MISSING', static function () use ($pdo): void {
                    $pdo->exec('ALTER TABLE order_notifications ADD UNIQUE KEY uq_order_notifications_order (order_id)');
                });
            }
        }
    }

    $done = true;
}

function ensure_product_landing_columns(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();
    $cols = $pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_COLUMN);
    $need = ['features', 'sort_order', 'show_on_landing', 'image_path'];
    $missing = array_values(array_filter($need, static fn ($c) => !in_array($c, $cols, true)));
    $landingExists = (bool) $pdo->query("SHOW TABLES LIKE 'landing_features'")->fetch();
    if ($missing !== [] || !$landingExists) {
        apply_runtime_ddl('SCHEMA_PRODUCT_LANDING_MISSING', static function () use ($pdo, $cols, $landingExists): void {
            if (!in_array('features', $cols, true)) {
                $pdo->exec("ALTER TABLE products ADD COLUMN features TEXT NOT NULL DEFAULT '[]' AFTER description");
            }
            if (!in_array('sort_order', $cols, true)) {
                $pdo->exec('ALTER TABLE products ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER status');
            }
            if (!in_array('show_on_landing', $cols, true)) {
                $pdo->exec("ALTER TABLE products ADD COLUMN show_on_landing TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order");
            }
            if (!in_array('image_path', $cols, true)) {
                $pdo->exec('ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL DEFAULT NULL AFTER description');
            }
            if (!$landingExists) {
                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS landing_features (
                        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(160) NOT NULL,
                        sort_order INT NOT NULL DEFAULT 0,
                        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            }
        });
    }
    $done = true;
}

function ensure_smtp_settings(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();
    if (app_allows_runtime_ddl()) {
        try {
            $pdo->exec('ALTER TABLE settings MODIFY setting_value TEXT NOT NULL');
        } catch (Throwable $e) {
            // Column may already be TEXT or migration may lack privilege; ignore.
        }
    }
    // Hostinger webmail SMTP defaults
    $defaults = [
        'smtp_host' => 'smtp.hostinger.com',
        'smtp_port' => '465',
        'smtp_encryption' => 'ssl',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => 'Cellvigor Biorevive LTD',
        'smtp_provider' => 'hostinger',
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_key = setting_key'
    );
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    // If host was never filled in, apply Hostinger defaults.
    $pdo->exec(
        "UPDATE settings SET setting_value = 'smtp.hostinger.com'
         WHERE setting_key = 'smtp_host' AND TRIM(setting_value) = ''"
    );
    $pdo->exec(
        "UPDATE settings SET setting_value = '465'
         WHERE setting_key = 'smtp_port' AND TRIM(setting_value) = ''"
    );
    $pdo->exec(
        "UPDATE settings SET setting_value = 'ssl'
         WHERE setting_key = 'smtp_encryption' AND TRIM(setting_value) = ''"
    );
    $done = true;
}

function product_image_public_url(?string $imagePath): string
{
    $imagePath = trim((string) $imagePath);
    if ($imagePath === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $imagePath)) {
        return $imagePath;
    }
    return 'assets/' . ltrim(str_replace('\\', '/', $imagePath), '/');
}

function product_uploads_dir(): string
{
    $dir = BASE_PATH . '/public/assets/uploads/products';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function store_product_image_upload(array $file, ?string $oldPath = null): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return (string) ($oldPath ?? '');
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded image.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 3 * 1024 * 1024) {
        throw new RuntimeException('Image must be between 1 byte and 3 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($map[$mime])) {
        throw new RuntimeException('Use a JPG, PNG, WEBP, or GIF image.');
    }
    $name = 'product_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
    $dest = product_uploads_dir() . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    if ($oldPath) {
        delete_product_image_file($oldPath);
    }
    return 'uploads/products/' . $name;
}

function delete_product_image_file(?string $imagePath): void
{
    $imagePath = trim((string) $imagePath);
    if ($imagePath === '' || preg_match('#^https?://#i', $imagePath)) {
        return;
    }
    $full = BASE_PATH . '/public/assets/' . ltrim(str_replace('\\', '/', $imagePath), '/');
    $root = realpath(BASE_PATH . '/public/assets/uploads/products');
    $real = realpath($full);
    if ($root && $real && str_starts_with($real, $root) && is_file($real)) {
        @unlink($real);
    }
}

function parse_feature_list($raw): array
{
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = preg_split('/\r\n|\r|\n/', $text) ?: [];
        }
    }
    $out = [];
    foreach ($items as $item) {
        $line = trim((string) $item);
        if ($line !== '') {
            $out[] = mb_substr($line, 0, 160);
        }
    }
    return array_values(array_unique($out));
}

function ensure_registration_payments_table(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();
    $tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'registration_payments'")->fetch();
    if (!$tableExists) {
        apply_runtime_ddl('SCHEMA_REGISTRATION_PAYMENTS_MISSING', static function () use ($pdo): void {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS registration_payments (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    reference VARCHAR(64) NOT NULL,
                    paystack_reference VARCHAR(64) NULL,
                    email VARCHAR(120) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL,
                    package_id INT UNSIGNED NOT NULL,
                    package_name VARCHAR(80) NOT NULL,
                    payment_method VARCHAR(20) NOT NULL DEFAULT 'paystack',
                    proof_path VARCHAR(255) NULL,
                    notes VARCHAR(255) NOT NULL DEFAULT '',
                    admin_note VARCHAR(255) NOT NULL DEFAULT '',
                    reviewed_by INT UNSIGNED NULL,
                    reviewed_at DATETIME NULL,
                    status ENUM('pending','paid','registered','funded','rejected') NOT NULL DEFAULT 'pending',
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
        });
    }

    $cols = $pdo->query('SHOW COLUMNS FROM registration_payments')->fetchAll(PDO::FETCH_COLUMN);
    $need = ['payment_method', 'proof_path', 'notes', 'admin_note', 'reviewed_by', 'reviewed_at', 'sponsor_code', 'placement_side'];
    $missing = array_values(array_filter($need, static fn ($c) => !in_array($c, $cols, true)));
    $statusCol = $pdo->query("SHOW COLUMNS FROM registration_payments LIKE 'status'")->fetch();
    $type = (string) ($statusCol['Type'] ?? '');
    $needsStatusEnum = $type !== '' && stripos($type, 'rejected') === false;

    if ($missing !== [] || $needsStatusEnum) {
        apply_runtime_ddl('SCHEMA_REGISTRATION_PAYMENTS_COLUMNS_MISSING', static function () use ($pdo, $cols, $needsStatusEnum): void {
            $add = static function (string $sql) use ($pdo): void {
                $pdo->exec($sql);
            };
            if (!in_array('payment_method', $cols, true)) {
                $add("ALTER TABLE registration_payments ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'paystack' AFTER package_name");
            }
            if (!in_array('proof_path', $cols, true)) {
                $add('ALTER TABLE registration_payments ADD COLUMN proof_path VARCHAR(255) NULL AFTER payment_method');
            }
            if (!in_array('notes', $cols, true)) {
                $add("ALTER TABLE registration_payments ADD COLUMN notes VARCHAR(255) NOT NULL DEFAULT '' AFTER proof_path");
            }
            if (!in_array('admin_note', $cols, true)) {
                $add("ALTER TABLE registration_payments ADD COLUMN admin_note VARCHAR(255) NOT NULL DEFAULT '' AFTER notes");
            }
            if (!in_array('reviewed_by', $cols, true)) {
                $add('ALTER TABLE registration_payments ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER admin_note');
            }
            if (!in_array('reviewed_at', $cols, true)) {
                $add('ALTER TABLE registration_payments ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by');
            }
            $cols2 = $pdo->query('SHOW COLUMNS FROM registration_payments')->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('sponsor_code', $cols2, true)) {
                $add("ALTER TABLE registration_payments ADD COLUMN sponsor_code VARCHAR(80) NOT NULL DEFAULT '' AFTER package_name");
            }
            if (!in_array('placement_side', $cols2, true)) {
                $add("ALTER TABLE registration_payments ADD COLUMN placement_side VARCHAR(10) NOT NULL DEFAULT '' AFTER sponsor_code");
            }
            if ($needsStatusEnum) {
                $pdo->exec(
                    "ALTER TABLE registration_payments
                     MODIFY COLUMN status ENUM('pending','paid','registered','funded','rejected') NOT NULL DEFAULT 'pending'"
                );
            }
        });
    }

    $done = true;
}

/**
 * Private receipt storage (outside public webroot).
 * Legacy public paths under public/assets/uploads/receipts remain readable for existing records.
 */
function private_receipt_uploads_dir(): string
{
    $dir = BASE_PATH . '/storage/receipts';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
    return $dir;
}

/** @deprecated Prefer private_receipt_uploads_dir(); kept for legacy path resolution. */
function receipt_uploads_dir(): string
{
    $dir = BASE_PATH . '/public/assets/uploads/receipts';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function store_registration_receipt_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Receipt upload failed. Please try again.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded receipt.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Receipt must be between 1 byte and 5 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    if (!isset($map[$mime])) {
        throw new RuntimeException('Use a JPG, PNG, WEBP, or PDF receipt.');
    }
    $name = 'receipt_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $map[$mime];
    $dest = private_receipt_uploads_dir() . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save the receipt.');
    }
    // Stored relative to BASE_PATH; never under public/.
    return 'storage/receipts/' . $name;
}

/**
 * Resolve a receipt path from DB to an absolute filesystem path.
 * Allows private storage paths and legacy public upload paths only.
 */
function resolve_registration_receipt_path(string $proofPath): ?string
{
    $proofPath = str_replace('\\', '/', trim($proofPath));
    if ($proofPath === '' || strpos($proofPath, "\0") !== false) {
        return null;
    }
    // Normalize accidental leading ../assets/ or assets/ prefixes from older API responses.
    $proofPath = preg_replace('#^(\.\./)+assets/#', 'uploads/', $proofPath) ?: $proofPath;
    $proofPath = ltrim($proofPath, '/');

    $candidates = [];
    if (str_starts_with($proofPath, 'storage/receipts/')) {
        $candidates[] = BASE_PATH . '/' . $proofPath;
    } elseif (str_starts_with($proofPath, 'uploads/receipts/')) {
        $candidates[] = BASE_PATH . '/public/assets/' . $proofPath;
        // Migration target if an admin later moves the file privately with the same basename.
        $candidates[] = BASE_PATH . '/storage/receipts/' . basename($proofPath);
    } elseif (str_starts_with($proofPath, 'receipts/')) {
        $candidates[] = BASE_PATH . '/public/assets/uploads/' . $proofPath;
        $candidates[] = BASE_PATH . '/storage/' . $proofPath;
    } else {
        // Basename-only legacy values.
        $base = basename($proofPath);
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }
        $candidates[] = private_receipt_uploads_dir() . DIRECTORY_SEPARATOR . $base;
        $candidates[] = receipt_uploads_dir() . DIRECTORY_SEPARATOR . $base;
    }

    $privateRoot = realpath(private_receipt_uploads_dir());
    $legacyRoot = realpath(receipt_uploads_dir());
    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            continue;
        }
        $okPrivate = $privateRoot && str_starts_with($real, $privateRoot . DIRECTORY_SEPARATOR);
        $okLegacy = $legacyRoot && str_starts_with($real, $legacyRoot . DIRECTORY_SEPARATOR);
        if ($okPrivate || $okLegacy) {
            return $real;
        }
    }
    return null;
}

function naira_to_kobo(float|string $naira): int
{
    return (int) round(((float) $naira) * 100);
}

/**
 * Require unique (parent_id, binary_side). Never creates the index at request time.
 * Apply explicitly via database/migrations/2026_phase1b_uq_users_parent_side.sql
 * or scripts/phase1b_apply_local_uq.php (local loopback only).
 */
function users_has_parent_side_unique(?PDO $pdo = null): bool
{
    $pdo = $pdo ?: db();
    $indexes = $pdo->query('SHOW INDEX FROM users')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($indexes as $idx) {
        if ((string) ($idx['Key_name'] ?? '') === 'uq_users_parent_side') {
            return true;
        }
    }
    return false;
}

/**
 * @deprecated Name retained for callers; check-only ? never runs ALTER/CREATE.
 */
function ensure_binary_placement_schema(?PDO $pdo = null): void
{
    require_binary_placement_schema($pdo);
}

function require_binary_placement_schema(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();
    if (!users_has_parent_side_unique($pdo)) {
        schema_missing(
            'SCHEMA_BINARY_PLACEMENT_MISSING',
            'uq_users_parent_side is required. Apply database/migrations/2026_phase1b_uq_users_parent_side.sql before registration.'
        );
    }
    $done = true;
}

/**
 * Migration-only helper to ADD UNIQUE KEY uq_users_parent_side.
 *
 * SAFETY GATE (Phase 1F):
 * - Must run under CLI (PHP_SAPI === 'cli'). Ordinary HTTP requests cannot invoke DDL via this helper.
 * - Must set ZENVITA_ALLOW_BINARY_INDEX_DDL=1 in the process environment (explicit opt-in).
 * - Does not change binary placement business logic; only schema DDL after a duplicate audit.
 *
 * Prefer the Phase 1D/1F schema readiness migrator for production-bound schema work.
 * Local loopback apply: scripts/phase1b_apply_local_uq.php (sets the env flag).
 */
function apply_binary_placement_unique_index(PDO $pdo): void
{
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException(
            'apply_binary_placement_unique_index is migration-only and cannot run under HTTP.'
        );
    }
    $allow = getenv('ZENVITA_ALLOW_BINARY_INDEX_DDL');
    if ($allow !== '1' && $allow !== 'true') {
        throw new RuntimeException(
            'apply_binary_placement_unique_index refused: set ZENVITA_ALLOW_BINARY_INDEX_DDL=1 for CLI migration only.'
        );
    }
    if (users_has_parent_side_unique($pdo)) {
        return;
    }
    $dup = (int) $pdo->query(
        "SELECT COUNT(*) FROM (
            SELECT parent_id, binary_side
            FROM users
            WHERE parent_id IS NOT NULL
              AND binary_side IN ('left', 'right')
            GROUP BY parent_id, binary_side
            HAVING COUNT(*) > 1
         ) d"
    )->fetchColumn();
    if ($dup > 0) {
        throw new RuntimeException(
            'Cannot add uq_users_parent_side: duplicate (parent_id, binary_side) rows exist. Resolve manually first.'
        );
    }
    $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_parent_side (parent_id, binary_side)');
}

/**
 * Spillover binary seat finder with row locks.
 * Must run inside an open transaction.
 *
 * Strategy:
 * 1. Lock the current candidate parent (`SELECT ... FOR UPDATE`).
 * 2. Re-check whether the requested side already has a child.
 * 3. If empty, return that seat while holding the parent lock until commit.
 * 4. If occupied, walk to the child and repeat.
 * Competing transactions block on the same parent lock, then re-check and spill deeper.
 * Unique index uq_users_parent_side is the final safety net on INSERT.
 */
function open_seat(PDO $pdo, int $startId, string $side): array
{
    $side = strtolower(trim($side));
    if (!in_array($side, ['left', 'right'], true)) {
        throw new RuntimeException('SIDE_REQUIRED');
    }
    if ($pdo->inTransaction() !== true) {
        throw new RuntimeException('PLACEMENT_REQUIRES_TRANSACTION');
    }

    $current = $startId;
    $guard = 0;
    $lockParent = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    $findChild = $pdo->prepare(
        "SELECT id FROM users WHERE parent_id = ? AND binary_side = ? LIMIT 1"
    );

    while ($guard < 50) {
        $lockParent->execute([$current]);
        $parentId = $lockParent->fetchColumn();
        if (!$parentId) {
            throw new RuntimeException('PLACEMENT_PARENT_MISSING');
        }

        $findChild->execute([$current, $side]);
        $child = $findChild->fetchColumn();
        if (!$child) {
            return ['parent_id' => (int) $current, 'side' => $side];
        }
        $current = (int) $child;
        $guard++;
    }

    throw new RuntimeException('That side has no open seat.');
}

/**
 * Roles that may register a member without a paid registration_payments row.
 * Documented policy ? do not expand without management approval.
 */
function registration_payment_bypass_role(?array $actor): ?string
{
    if (!$actor) {
        return null;
    }
    $role = (string) ($actor['role'] ?? '');
    if ($role === 'admin') {
        return 'admin';
    }
    if ($role === 'member') {
        return 'member_downline';
    }
    return null;
}

function registration_requires_payment(?array $actor): bool
{
    return registration_payment_bypass_role($actor) === null;
}

function payment_audit_logs_dir(): string
{
    $dir = BASE_PATH . '/storage/logs/payments';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
    return $dir;
}

/**
 * Append-only JSONL payment audit logger. Never throws to callers.
 * Strips secrets and dangerous keys; sanitizes scalars against log injection.
 */
function payment_audit_log(string $eventType, array $context = []): void
{
    try {
        $eventType = preg_replace('/[^a-z0-9._-]+/i', '_', strtolower(trim($eventType))) ?: 'event';
        $blocked = [
            'password', 'password_hash', 'secret', 'paystack_secret', 'paystack_secret_key',
            'authorization', 'authorization_url', 'card', 'cvv', 'pin', 'account_number',
            'tmp_name', 'proof_path', 'absolute_path', 'exception', 'trace', 'token',
        ];
        $clean = [];
        foreach ($context as $key => $value) {
            $k = strtolower((string) $key);
            foreach ($blocked as $needle) {
                if ($k === $needle || str_contains($k, $needle)) {
                    continue 2;
                }
            }
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $clean[(string) $key] = $value;
                continue;
            }
            if (is_scalar($value)) {
                $text = str_replace(["\r", "\n", "\t"], ' ', (string) $value);
                if (strlen($text) > 240) {
                    $text = substr($text, 0, 240) . '...';
                }
                $clean[(string) $key] = $text;
            }
        }
        $record = [
            'ts' => gmdate('c'),
            'event' => $eventType,
            'reference' => $clean['reference'] ?? ($clean['payment_reference'] ?? null),
            'related_id' => $clean['related_id'] ?? ($clean['order_id'] ?? ($clean['payment_id'] ?? null)),
            'outcome' => $clean['outcome'] ?? null,
            'admin_id' => $clean['admin_id'] ?? null,
            'context' => $clean,
        ];
        $line = json_encode($record, JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }
        $file = payment_audit_logs_dir() . DIRECTORY_SEPARATOR . gmdate('Y-m-d') . '.log';
        file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // Never break payment processing because of logging.
    }
}

function app_root_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    // When called from /api/*.php, project root is the parent of /api.
    if (substr($dir, -4) === '/api') {
        $dir = substr($dir, 0, -4);
    }
    return rtrim($scheme . '://' . $host . $dir, '/');
}

function paystack_public_key(): string
{
    global $config;
    $fromSettings = trim(setting('paystack_public_key', ''));
    if ($fromSettings !== '') {
        return $fromSettings;
    }
    return trim((string) ($config['paystack_public_key'] ?? ''));
}

function paystack_secret_key(): string
{
    global $config;
    $fromSettings = trim(setting('paystack_secret_key', ''));
    if ($fromSettings !== '') {
        return $fromSettings;
    }
    return trim((string) ($config['paystack_secret_key'] ?? ''));
}

function paystack_request(string $method, string $path, ?array $payload = null): array
{
    $secret = paystack_secret_key();
    if ($secret === '') {
        return ['ok' => false, 'error' => 'Paystack secret key is not configured.'];
    }
    $url = 'https://api.paystack.co' . $path;
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $secret,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 45,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno) {
        return ['ok' => false, 'error' => 'Could not reach Paystack: ' . $err];
    }
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid Paystack response.', 'http' => $code];
    }
    $data['ok'] = !empty($data['status']);
    $data['http'] = $code;
    return $data;
}

/**
 * Strict Paystack verification.
 *
 * $expects keys:
 * - amount_kobo (int, required for money checks)
 * - currency (default NGN)
 * - purpose (optional; compared to metadata.purpose)
 * - reference (optional; must match verified reference exactly)
 */
/**
 * Pure Paystack success-payload checks (no HTTP). Used by verify_paystack_reference and tests.
 */
function validate_paystack_success_payload(array $data, string $expectedReference, array $expects = []): array
{
    if (strtolower((string) ($data['status'] ?? '')) !== 'success') {
        return ['ok' => false, 'error' => 'Payment was not successful.'];
    }

    $verifiedRef = trim((string) ($data['reference'] ?? ''));
    $expectedRef = trim((string) ($expects['reference'] ?? $expectedReference));
    if ($verifiedRef === '' || !hash_equals($expectedRef, $verifiedRef)) {
        return ['ok' => false, 'error' => 'Paystack reference does not match the expected payment reference.'];
    }

    $currency = strtoupper(trim((string) ($expects['currency'] ?? 'NGN')));
    $verifiedCurrency = strtoupper(trim((string) ($data['currency'] ?? '')));
    if ($verifiedCurrency !== $currency) {
        return ['ok' => false, 'error' => 'Payment currency must be ' . $currency . '.'];
    }

    // amount_kobo is mandatory ? exact integer kobo comparison only (no floats).
    if (!array_key_exists('amount_kobo', $expects)) {
        return ['ok' => false, 'error' => 'Expected payment amount was not provided for verification.'];
    }
    $expectedKobo = (int) $expects['amount_kobo'];
    $verifiedKobo = (int) ($data['amount'] ?? -1);
    if ($expectedKobo < 1 || $verifiedKobo !== $expectedKobo) {
        return ['ok' => false, 'error' => 'Paid amount does not match the expected amount.'];
    }

    $expectedPurpose = trim((string) ($expects['purpose'] ?? ''));
    if ($expectedPurpose !== '') {
        $meta = $data['metadata'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        $purpose = trim((string) ($meta['purpose'] ?? ''));
        // Limitation: missing purpose is tolerated when the internal row is already bound by reference.
        // Wrong non-empty purpose is still rejected. Prefer requiring purpose after a future cutover.
        if ($purpose !== '' && !hash_equals($expectedPurpose, $purpose)) {
            return ['ok' => false, 'error' => 'Payment purpose does not match this transaction type.'];
        }
    }

    return [
        'ok' => true,
        'data' => $data,
        'reference' => $verifiedRef,
        'amount_kobo' => $verifiedKobo,
        'currency' => $verifiedCurrency,
    ];
}

/**
 * Parse MySQL 1062 duplicate-key name from a PDOException message.
 */
function mysql_duplicate_key_name(PDOException $error): string
{
    $msg = $error->getMessage();
    if (preg_match("/for key ['`](?:[\\w]+\\.)?([^'`]+)['`]/i", $msg, $m)) {
        return strtolower((string) $m[1]);
    }
    return '';
}

/**
 * Authorize and resolve a registration receipt for an admin actor.
 * Used by admin-receipt.php and tests (no filesystem path leaked to clients).
 *
 * @return array{ok:bool,code?:int,error?:string,absolute?:string,mime?:string,download_name?:string}
 */
function registration_receipt_access(?array $actor, string $reference): array
{
    if (!$actor) {
        return ['ok' => false, 'code' => 401, 'error' => 'Sign in required.'];
    }
    if (($actor['role'] ?? '') !== 'admin') {
        return ['ok' => false, 'code' => 403, 'error' => 'This account cannot open that area.'];
    }
    $reference = trim($reference);
    if ($reference === '') {
        return ['ok' => false, 'code' => 422, 'error' => 'Missing payment reference.'];
    }
    ensure_registration_payments_table();
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT reference, payment_method, proof_path, status
         FROM registration_payments
         WHERE reference = ?
         LIMIT 1'
    );
    $stmt->execute([$reference]);
    $payment = $stmt->fetch();
    if (!$payment) {
        return ['ok' => false, 'code' => 404, 'error' => 'Payment record not found.'];
    }
    $proofPath = trim((string) ($payment['proof_path'] ?? ''));
    if ($proofPath === '') {
        return ['ok' => false, 'code' => 404, 'error' => 'No receipt is attached to this payment.'];
    }
    $absolute = resolve_registration_receipt_path($proofPath);
    if ($absolute === null) {
        return ['ok' => false, 'code' => 404, 'error' => 'Receipt file could not be found securely.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($absolute) ?: 'application/octet-stream';
    $allowed = [
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
        'application/pdf' => true,
    ];
    if (empty($allowed[$mime])) {
        return ['ok' => false, 'code' => 415, 'error' => 'Unsupported receipt file type.'];
    }
    return [
        'ok' => true,
        'absolute' => $absolute,
        'mime' => $mime,
        'download_name' => basename($absolute),
    ];
}

/**
 * Shared registration transaction used by add-user.php and integration tests.
 *
 * @param array<string,mixed> $input
 * @return array{ok:true,username:string,memberCode:string,side:?string,userId:int}
 */
function process_member_registration(PDO $pdo, ?array $actor, array $input): array
{
    require_binary_placement_schema($pdo);
    ensure_award_rank_schema($pdo);

    $username = trim((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $first = trim((string) ($input['firstName'] ?? ''));
    $last = trim((string) ($input['lastName'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $packageName = trim((string) ($input['package'] ?? 'Vision'));
    $side = strtolower(trim((string) ($input['side'] ?? '')));
    $sponsorName = trim((string) ($input['sponsor'] ?? ''));
    $paymentReferenceToUse = trim((string) ($input['paymentReference'] ?? ''));

    $bypassKind = registration_payment_bypass_role($actor);
    $isAdminBypass = $bypassKind === 'admin';
    $isMemberDownlineBypass = $bypassKind === 'member_downline';
    $requiresPayment = registration_requires_payment($actor);
    $actorRole = $actor ? (string) ($actor['role'] ?? '') : '';

    if ($actorRole === 'store') {
        throw new RuntimeException('STORE_BLOCKED');
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
        throw new RuntimeException('USERNAME_INVALID');
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('PASSWORD_INVALID');
    }
    if ($first === '') {
        throw new RuntimeException('FIRST_NAME_REQUIRED');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        throw new RuntimeException('EMAIL_INVALID');
    }
    if (strlen(preg_replace('/\D+/', '', $phone)) < 10 || strlen($phone) > 30) {
        throw new RuntimeException('PHONE_INVALID');
    }

    $package = $pdo->prepare('SELECT id, name, price, pv, direct_bonus FROM packages WHERE name = ? OR code = ? LIMIT 1');
    $package->execute([$packageName, strtolower($packageName)]);
    $packageRow = $package->fetch();
    if (!$packageRow) {
        throw new RuntimeException('PACKAGE_MISSING');
    }

    if ($requiresPayment && $paymentReferenceToUse === '') {
        throw new RuntimeException('PAYMENT_REQUIRED');
    }

    $taken = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $taken->execute([$username]);
    if ($taken->fetch()) {
        throw new RuntimeException('USERNAME_TAKEN');
    }
    $emailTaken = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $emailTaken->execute([$email]);
    if ($emailTaken->fetch()) {
        throw new RuntimeException('EMAIL_TAKEN');
    }

    $sponsorId = null;
    $parentId = null;
    $binarySide = null;
    $code = '';
    $userId = 0;

    $pdo->beginTransaction();
    try {
        $paymentRow = null;
        if ($requiresPayment) {
            $payStmt = $pdo->prepare('SELECT * FROM registration_payments WHERE reference = ? LIMIT 1 FOR UPDATE');
            $payStmt->execute([$paymentReferenceToUse]);
            $paymentRow = $payStmt->fetch();
            if (!$paymentRow) {
                throw new RuntimeException('PAYMENT_MISSING');
            }
            if (($paymentRow['status'] ?? '') !== 'paid') {
                throw new RuntimeException('PAYMENT_NOT_PAID');
            }
            if (strcasecmp((string) $paymentRow['email'], $email) !== 0) {
                throw new RuntimeException('PAYMENT_EMAIL_MISMATCH');
            }
            assert_registration_payment_package_match($paymentRow, $packageRow);
            if (naira_to_kobo($paymentRow['amount']) !== naira_to_kobo($packageRow['price'])) {
                throw new RuntimeException('PAYMENT_AMOUNT_MISMATCH');
            }

            // Placement is bound to the payment record ? never trust later editable form values.
            $boundSponsor = trim((string) ($paymentRow['sponsor_code'] ?? ''));
            $boundSide = strtolower(trim((string) ($paymentRow['placement_side'] ?? '')));
            if ($boundSponsor === '' || !in_array($boundSide, ['left', 'right'], true)) {
                throw new RuntimeException('PAYMENT_PLACEMENT_INCOMPLETE');
            }
            if ($sponsorName !== '' && strcasecmp($sponsorName, $boundSponsor) !== 0) {
                throw new RuntimeException('PAYMENT_SPONSOR_TAMPER');
            }
            if ($side !== '' && $side !== $boundSide) {
                throw new RuntimeException('PAYMENT_SIDE_TAMPER');
            }
            $sponsorName = $boundSponsor;
            $side = $boundSide;
        }

        if ($isMemberDownlineBypass) {
            if (!in_array($side, ['left', 'right'], true)) {
                throw new RuntimeException('SIDE_REQUIRED');
            }
            $sponsorId = (int) $actor['id'];
            $placed = open_seat($pdo, $sponsorId, $side);
            $parentId = $placed['parent_id'];
            $binarySide = $placed['side'];
        } elseif ($isAdminBypass) {
            $root = $pdo->query("SELECT id FROM users WHERE role = 'member' AND parent_id IS NULL LIMIT 1")->fetch();
            if ($sponsorName === '') {
                if ($root) {
                    throw new RuntimeException('SPONSOR_REQUIRED_ROOT_EXISTS');
                }
            } else {
                if (!in_array($side, ['left', 'right'], true)) {
                    throw new RuntimeException('SIDE_REQUIRED');
                }
                $sponsor = $pdo->prepare("SELECT id, status FROM users WHERE (username = ? OR member_code = ?) AND role = 'member' LIMIT 1");
                $sponsor->execute([$sponsorName, $sponsorName]);
                $sponsorRow = $sponsor->fetch();
                if (!$sponsorRow) {
                    throw new RuntimeException('SPONSOR_NOT_FOUND');
                }
                if ($sponsorRow['status'] !== 'active') {
                    throw new RuntimeException('SPONSOR_INACTIVE');
                }
                $sponsorId = (int) $sponsorRow['id'];
                $placed = open_seat($pdo, $sponsorId, $side);
                $parentId = $placed['parent_id'];
                $binarySide = $placed['side'];
            }
        } else {
            if ($sponsorName === '') {
                throw new RuntimeException('SPONSOR_REQUIRED');
            }
            if (!in_array($side, ['left', 'right'], true)) {
                throw new RuntimeException('SIDE_REQUIRED');
            }
            $sponsor = $pdo->prepare("SELECT id, status FROM users WHERE (username = ? OR member_code = ?) AND role = 'member' LIMIT 1");
            $sponsor->execute([$sponsorName, $sponsorName]);
            $sponsorRow = $sponsor->fetch();
            if (!$sponsorRow) {
                throw new RuntimeException('SPONSOR_NOT_FOUND');
            }
            if ($sponsorRow['status'] !== 'active') {
                throw new RuntimeException('SPONSOR_INACTIVE');
            }
            $sponsorId = (int) $sponsorRow['id'];
            $placed = open_seat($pdo, $sponsorId, $side);
            $parentId = $placed['parent_id'];
            $binarySide = $placed['side'];
        }

        $generation = 0;
        if ($parentId) {
            $genStmt = $pdo->prepare('SELECT parent_id FROM users WHERE id = ?');
            $walk = (int) $parentId;
            $guard = 0;
            $generation = 1;
            while ($walk > 0 && $guard < 50) {
                $genStmt->execute([$walk]);
                $nextParent = $genStmt->fetchColumn();
                if ($nextParent === false || $nextParent === null || (int) $nextParent < 1) {
                    break;
                }
                $generation++;
                $walk = (int) $nextParent;
                $guard++;
            }
        }
        $awardRank = award_rank_for_generation($generation);

        $pdo->prepare(
            'INSERT INTO users (member_code, username, email, phone, password_hash, first_name, last_name, role, package_id, award_rank, sponsor_id, parent_id, binary_side, status, personal_pv, country)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, \'member\', ?, ?, ?, ?, ?, \'active\', ?, \'Nigeria\')'
        )->execute([
            $username,
            $email,
            $phone,
            password_hash($password, PASSWORD_DEFAULT),
            $first,
            $last,
            $packageRow['id'],
            $awardRank,
            $sponsorId,
            $parentId,
            $binarySide,
            (int) $packageRow['pv'],
        ]);
        $userId = (int) $pdo->lastInsertId();
        $code = 'ZGV' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE users SET member_code = ? WHERE id = ?')->execute([$code, $userId]);
        $pdo->prepare('INSERT INTO wallets (user_id, available_balance) VALUES (?, 0)')->execute([$userId]);

        if ($paymentRow) {
            $consume = $pdo->prepare(
                "UPDATE registration_payments
                 SET status = 'registered', user_id = ?, updated_at = NOW()
                 WHERE reference = ? AND status = 'paid'"
            );
            $consume->execute([$userId, $paymentRow['reference']]);
            if ($consume->rowCount() !== 1) {
                throw new RuntimeException('PAYMENT_CONSUME_FAILED');
            }
            payment_audit_log('registration.payment_consumed', [
                'outcome' => 'registered',
                'reference' => (string) $paymentRow['reference'],
                'related_id' => (int) ($paymentRow['id'] ?? 0),
                'user_id' => $userId,
            ]);
        }

        if ($sponsorId) {
            registration_apply_sponsor_rewards($pdo, $userId, $first, $last, $sponsorId, $parentId, $binarySide, $packageRow);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    return [
        'ok' => true,
        'username' => $username,
        'memberCode' => $code,
        'side' => $binarySide,
        'userId' => $userId,
    ];
}

/**
 * Package identity rules for registration payments.
 * Both package_id and package_name must match when both are present on the payment row.
 */
function assert_registration_payment_package_match(array $paymentRow, array $packageRow): void
{
    $payId = (int) ($paymentRow['package_id'] ?? 0);
    $payName = trim((string) ($paymentRow['package_name'] ?? ''));
    $pkgId = (int) $packageRow['id'];
    $pkgName = (string) $packageRow['name'];

    if ($payId > 0 && $payName !== '') {
        if ($payId !== $pkgId || strcasecmp($payName, $pkgName) !== 0) {
            throw new RuntimeException('PAYMENT_PACKAGE_MISMATCH');
        }
        return;
    }
    if ($payId > 0) {
        // Historical: id only.
        if ($payId !== $pkgId) {
            throw new RuntimeException('PAYMENT_PACKAGE_MISMATCH');
        }
        payment_audit_log('registration.package_legacy', [
            'outcome' => 'id_only',
            'related_id' => (int) ($paymentRow['id'] ?? 0),
        ]);
        return;
    }
    if ($payName !== '') {
        // Historical: name only.
        if (strcasecmp($payName, $pkgName) !== 0) {
            throw new RuntimeException('PAYMENT_PACKAGE_MISMATCH');
        }
        payment_audit_log('registration.package_legacy', [
            'outcome' => 'name_only',
            'related_id' => (int) ($paymentRow['id'] ?? 0),
        ]);
        return;
    }
    throw new RuntimeException('PAYMENT_PACKAGE_INCOMPLETE');
}

function registration_apply_sponsor_rewards(
    PDO $pdo,
    int $userId,
    string $first,
    string $last,
    int $sponsorId,
    $parentId,
    $binarySide,
    array $packageRow
): void {
    add_leg_pv($pdo, (int) $parentId, (string) $binarySide, (int) $packageRow['pv']);
    process_upline_pairing($pdo, (int) $parentId);
    $bonus = (float) $packageRow['direct_bonus'];
    $label = trim($first . ' ' . $last);

    // Lock sponsor wallet before any commission/transaction writes (caller owns the txn).
    $walletLock = $pdo->prepare('SELECT user_id, available_balance FROM wallets WHERE user_id = ? FOR UPDATE');
    $walletLock->execute([$sponsorId]);
    $walletRow = $walletLock->fetch(PDO::FETCH_ASSOC);
    if (!$walletRow) {
        throw new RuntimeException('SPONSOR_WALLET_MISSING');
    }

    $pdo->prepare(
        'INSERT INTO commissions (user_id, source_label, commission_type, package_name, pv, amount, status, earned_on)
         VALUES (?, ?, \'Direct Referral\', ?, ?, ?, \'Approved\', CURDATE())'
    )->execute([$sponsorId, $label, $packageRow['name'], (int) $packageRow['pv'], $bonus]);
    $reference = 'TX-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare(
        'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
         VALUES (?, ?, \'Commission\', ?, ?, \'Approved\', CURDATE())'
    )->execute([$sponsorId, $reference, 'Direct referral', $bonus]);
    if ($bonus > 0) {
        $walletUpd = $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?');
        $walletUpd->execute([$bonus, $sponsorId]);
        if ($walletUpd->rowCount() !== 1) {
            throw new RuntimeException('SPONSOR_WALLET_UPDATE_FAILED');
        }
    }
    registration_pay_indirect($pdo, $userId, $label, $sponsorId, $packageRow);
}

/**
 * Pay unilevel indirect commissions for generations 1-6 above the direct sponsor.
 * Rates come only from indirect_generation_rates() (authoritative).
 * Traversal: start at direct sponsor, walk sponsor_id chain; each hop is one generation.
 * Suspended ancestors are skipped (no pay) but the walk continues.
 */
function registration_pay_indirect(PDO $pdo, int $newUserId, string $newName, int $sponsorId, array $packageRow): void
{
    $rates = indirect_generation_rates();
    $upline = $pdo->prepare('SELECT sponsor_id FROM users WHERE id = ?');
    $status = $pdo->prepare('SELECT status FROM users WHERE id = ?');
    $walletLock = $pdo->prepare('SELECT user_id, available_balance FROM wallets WHERE user_id = ? FOR UPDATE');
    $walletUpd = $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?');
    $current = $sponsorId;
    $paidAncestors = [];
    foreach ($rates as $index => $rate) {
        $upline->execute([$current]);
        $ancestorId = (int) $upline->fetchColumn();
        if ($ancestorId < 1) {
            break;
        }
        if (isset($paidAncestors[$ancestorId])) {
            $current = $ancestorId;
            continue;
        }
        $status->execute([$ancestorId]);
        $ancestorStatus = (string) $status->fetchColumn();
        $amount = round(((float) $packageRow['price']) * ((float) $rate) / 100, 2);
        if ($ancestorStatus !== 'suspended' && $amount > 0) {
            $walletLock->execute([$ancestorId]);
            $walletRow = $walletLock->fetch(PDO::FETCH_ASSOC);
            if (!$walletRow) {
                throw new RuntimeException('INDIRECT_WALLET_MISSING');
            }

            $pdo->prepare(
                'INSERT INTO commissions (user_id, source_label, commission_type, package_name, pv, amount, status, earned_on)
                 VALUES (?, ?, \'Indirect Referral\', ?, ?, ?, \'Approved\', CURDATE())'
            )->execute([$ancestorId, $newName, $packageRow['name'], (int) $packageRow['pv'], $amount]);
            $reference = sprintf(
                'ZGV-I%d-%05d-%05d-%s',
                $index + 1,
                $newUserId,
                $ancestorId,
                strtoupper(bin2hex(random_bytes(4)))
            );
            $pdo->prepare(
                'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
                 VALUES (?, ?, \'Commission\', ?, ?, \'Approved\', CURDATE())'
            )->execute([$ancestorId, $reference, 'Indirect referral gen ' . ($index + 1), $amount]);
            $walletUpd->execute([$amount, $ancestorId]);
            if ($walletUpd->rowCount() !== 1) {
                throw new RuntimeException('INDIRECT_WALLET_UPDATE_FAILED');
            }
            $paidAncestors[$ancestorId] = true;
        }
        $current = $ancestorId;
    }
}

function verify_paystack_reference(string $reference, array $expects = []): array
{
    $reference = trim($reference);
    if ($reference === '') {
        return ['ok' => false, 'error' => 'Missing payment reference.'];
    }
    $result = paystack_request('GET', '/transaction/verify/' . rawurlencode($reference));
    if (empty($result['ok'])) {
        return [
            'ok' => false,
            'error' => (string) ($result['message'] ?? $result['error'] ?? 'Payment could not be verified.'),
        ];
    }
    $data = $result['data'] ?? [];
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Payment was not successful.'];
    }
    return validate_paystack_success_payload($data, $reference, $expects);
}

function ensure_award_rank_schema(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();
    $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('award_rank', $cols, true)) {
        apply_runtime_ddl('SCHEMA_AWARD_RANK_MISSING', static function () use ($pdo): void {
            $pdo->exec("ALTER TABLE users ADD COLUMN award_rank VARCHAR(60) NOT NULL DEFAULT 'Member' AFTER package_id");
        });
    }
    $done = true;
}

function award_rank_options(): array
{
    return [
        'Member',
        '1 Star',
        '2 Star',
        '3 Star',
        'Bronze',
        'Silver',
        'Gold',
        'Diamond',
        'Crown',
        'Ambassador',
    ];
}

/**
 * Indirect (unilevel) commission % for generations 1 through 6
 * above the direct sponsor. Combined total: 5.5%.
 */
function indirect_generation_rates(): array
{
    return [2, 1, 1, 0.5, 0.5, 0.5];
}

/**
 * Generation ladder from root (first) down to the deepest end.
 * Gen 0 = Ambassador ? later gens step down until Member.
 */
function award_rank_ladder(): array
{
    return [
        'Ambassador',
        'Crown',
        'Diamond',
        'Gold',
        'Silver',
        'Bronze',
        '3 Star',
        '2 Star',
        '1 Star',
        'Member',
    ];
}

function award_rank_for_generation(int $generation): string
{
    $ladder = award_rank_ladder();
    $generation = max(0, $generation);
    if ($generation >= count($ladder)) {
        return 'Member';
    }
    return $ladder[$generation];
}

/**
 * Recalculate every member's award_rank from binary-tree generation.
 * Returns number of rows updated.
 */
function sync_award_ranks_by_generation(?PDO $pdo = null): int
{
    $pdo = $pdo ?: db();
    ensure_award_rank_schema($pdo);

    $rows = $pdo->query(
        "SELECT id, parent_id, award_rank
         FROM users
         WHERE role = 'member'"
    )->fetchAll();
    if (!$rows) {
        return 0;
    }

    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = $row;
    }

    $generationOf = static function (array $row) use ($byId): int {
        $count = 0;
        $current = $row;
        $guard = 0;
        while (!empty($current['parent_id']) && $guard < 50) {
            $count++;
            $parent = $byId[(int) $current['parent_id']] ?? null;
            if (!$parent) {
                break;
            }
            $current = $parent;
            $guard++;
        }
        return $count;
    };

    $update = $pdo->prepare('UPDATE users SET award_rank = ? WHERE id = ? AND award_rank <> ?');
    $changed = 0;
    foreach ($rows as $row) {
        $rank = award_rank_for_generation($generationOf($row));
        $current = trim((string) ($row['award_rank'] ?? '')) ?: 'Member';
        if ($current === $rank) {
            continue;
        }
        // Only rewrite when the stored value differs from the target rank.
        $update->execute([$rank, (int) $row['id'], $rank]);
        $changed += $update->rowCount() > 0 ? 1 : 0;
    }
    return $changed;
}

function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    ensure_award_rank_schema();
    $stmt = db()->prepare(
        'SELECT u.id, u.member_code, u.username, u.email, u.phone, u.first_name, u.last_name,
                u.role, u.status, u.personal_pv, u.left_pv, u.right_pv, u.binary_side,
                u.city, u.country, u.bank_name, u.account_name, u.account_number,
                u.award_rank,
                p.code AS package_code, p.name AS package_name,
                s.member_code AS sponsor_code
         FROM users u
         LEFT JOIN packages p ON p.id = u.package_id
         LEFT JOIN users s ON s.id = u.sponsor_id
         WHERE u.id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function member_tree(int $userId): array
{
    $stmt = db()->query(
        'SELECT u.id, u.member_code, u.first_name, u.last_name, u.status, u.personal_pv,
                u.left_pv, u.right_pv, u.binary_side, u.parent_id,
                p.name AS package_name, s.member_code AS sponsor_code, par.member_code AS parent_code
         FROM users u
         LEFT JOIN packages p ON p.id = u.package_id
         LEFT JOIN users s ON s.id = u.sponsor_id
         LEFT JOIN users par ON par.id = u.parent_id
         WHERE u.role = \'member\''
    );
    $byId = [];
    $children = [];
    foreach ($stmt as $row) {
        $id = (int) $row['id'];
        $byId[$id] = $row;
        if ($row['parent_id']) {
            $children[(int) $row['parent_id']][$row['binary_side']] = $id;
        }
    }
    if (!isset($byId[$userId])) {
        return [];
    }
    $build = function (int $id, int $generation, ?string $rootLeg) use (&$build, $byId, $children): array {
        $row = $byId[$id];
        $node = [
            'id' => $row['member_code'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'package' => $row['package_name'] ?: '',
            'pv' => (int) $row['personal_pv'],
            'status' => $row['status'],
            'position' => $generation === 0 ? 'Root' : ucfirst((string) $row['binary_side']),
            'generation' => $generation,
            'sponsor' => $row['sponsor_code'] ?: '-',
            'parent' => $row['parent_code'] ?: '-',
            'leftPv' => $generation === 0 ? (int) $row['left_pv'] : null,
            'rightPv' => $generation === 0 ? (int) $row['right_pv'] : null,
            'isYou' => $generation === 0,
            'side' => $rootLeg,
        ];
        if ($generation === 0) {
            $node['shortName'] = 'You';
        }
        $leftId = $children[$id]['left'] ?? null;
        $rightId = $children[$id]['right'] ?? null;
        if ($leftId || $rightId) {
            $node['showEmpty'] = true;
        }
        if ($leftId) {
            $node['left'] = $build($leftId, $generation + 1, $generation === 0 ? 'left' : $rootLeg);
        }
        if ($rightId) {
            $node['right'] = $build($rightId, $generation + 1, $generation === 0 ? 'right' : $rootLeg);
        }
        return $node;
    };
    return $build($userId, 0, null);
}

function setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function app_timezone(): DateTimeZone
{
    return new DateTimeZone('Africa/Lagos');
}

function ensure_eagle_lands_schema(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();

    $walletCols = $pdo->query('SHOW COLUMNS FROM wallets')->fetchAll(PDO::FETCH_COLUMN);
    $wdCols = $pdo->query('SHOW COLUMNS FROM withdrawals')->fetchAll(PDO::FETCH_COLUMN);
    $needWallet = !in_array('eagle_lands_balance', $walletCols, true);
    $needWd = !in_array('reserved_amount', $wdCols, true);
    if ($needWallet || $needWd) {
        apply_runtime_ddl('SCHEMA_EAGLE_LANDS_MISSING', static function () use ($pdo, $needWallet, $needWd): void {
            if ($needWallet) {
                $pdo->exec('ALTER TABLE wallets ADD COLUMN eagle_lands_balance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER available_balance');
            }
            if ($needWd) {
                $pdo->exec('ALTER TABLE withdrawals ADD COLUMN reserved_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount');
            }
        });
    }

    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_key = setting_key'
    );
    foreach ([
        'withdraw_day' => 'saturday',
        'withdraw_cash_percent' => '40',
        'withdraw_reserve_percent' => '60',
    ] as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    $done = true;
}

function ensure_store_sales_bonus_schema(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();

    // Never run DDL inside an active transaction — MySQL implicitly commits on CREATE TABLE.
    $tableExists = (bool) $pdo->query(
        "SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'store_sales_bonus_awards'
         LIMIT 1"
    )->fetchColumn();

    if (!$tableExists) {
        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'store_sales_bonus_awards is missing; call ensure_store_sales_bonus_schema() before beginning a transaction.'
            );
        }
        apply_runtime_ddl('SCHEMA_STORE_SALES_BONUS_MISSING', static function () use ($pdo): void {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS store_sales_bonus_awards (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    store_id INT UNSIGNED NOT NULL,
                    owner_user_id INT UNSIGNED NOT NULL,
                    threshold_level INT UNSIGNED NOT NULL,
                    target_amount DECIMAL(14,2) NOT NULL,
                    bonus_amount DECIMAL(14,2) NOT NULL,
                    sales_total DECIMAL(14,2) NOT NULL,
                    reference VARCHAR(40) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_store_bonus_level (store_id, threshold_level),
                    UNIQUE KEY uq_store_bonus_reference (reference),
                    KEY idx_store_bonus_owner (owner_user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        });
    }

    // Settings inserts are DML and safe inside a transaction.
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_key = setting_key'
    );
    foreach ([
        'store_sales_target' => '3000000',
        'store_sales_bonus' => '0',
    ] as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    $done = true;
}

function store_sales_bonus_config(?PDO $pdo = null): array
{
    ensure_store_sales_bonus_schema($pdo);
    $target = max(0, (float) setting('store_sales_target', '3000000'));
    $bonus = max(0, (float) setting('store_sales_bonus', '0'));
    if ($target <= 0) {
        $target = 3000000;
    }
    return [
        'target' => $target,
        'bonus' => $bonus,
        'enabled' => $bonus > 0,
    ];
}

function store_completed_sales_total(int $storeId, ?PDO $pdo = null): float
{
    $pdo = $pdo ?: db();
    ensure_order_payment_schema($pdo);
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantity * unit_price), 0)
         FROM orders
         WHERE store_id = ? AND status = 'Completed' AND payment_status = 'paid'"
    );
    $stmt->execute([$storeId]);
    return round((float) $stmt->fetchColumn(), 2);
}

/**
 * Award repeating store sales bonuses each time completed sales cross the admin target
 * (default ?3,000,000). Bonus amount is set by admin.
 */
function maybe_award_store_sales_bonuses(int $storeId, ?PDO $pdo = null): array
{
    $pdo = $pdo ?: db();
    // Schema/DDL must already be ensured before any outer transaction begins.
    // This call is a no-op once warm, and refuses CREATE TABLE while inTransaction().
    ensure_store_sales_bonus_schema($pdo);
    $config = store_sales_bonus_config($pdo);
    $awarded = [];
    $ownsTx = false;

    if ($config['bonus'] <= 0 || $config['target'] <= 0) {
        return [
            'awarded' => [],
            'salesTotal' => store_completed_sales_total($storeId, $pdo),
            'config' => $config,
        ];
    }

    try {
        // Join the caller's transaction when present; never commit/roll it back yourself.
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $ownsTx = true;
        }

        $storeStmt = $pdo->prepare('SELECT id, owner_user_id, name FROM stores WHERE id = ? LIMIT 1 FOR UPDATE');
        $storeStmt->execute([$storeId]);
        $store = $storeStmt->fetch();
        if (!$store) {
            if ($ownsTx) {
                $pdo->rollBack();
            }
            return ['awarded' => [], 'salesTotal' => 0.0, 'config' => $config];
        }

        $ownerId = (int) $store['owner_user_id'];
        $salesTotal = store_completed_sales_total($storeId, $pdo);
        $levelsDue = (int) floor($salesTotal / $config['target']);
        if ($levelsDue < 1) {
            if ($ownsTx) {
                $pdo->commit();
            }
            return ['awarded' => [], 'salesTotal' => $salesTotal, 'config' => $config];
        }

        $existingStmt = $pdo->prepare(
            'SELECT threshold_level FROM store_sales_bonus_awards WHERE store_id = ? FOR UPDATE'
        );
        $existingStmt->execute([$storeId]);
        $have = [];
        foreach ($existingStmt->fetchAll(PDO::FETCH_COLUMN) as $level) {
            $have[(int) $level] = true;
        }

        for ($level = 1; $level <= $levelsDue; $level++) {
            if (isset($have[$level])) {
                continue;
            }

            $wallet = $pdo->prepare('SELECT available_balance FROM wallets WHERE user_id = ? FOR UPDATE');
            $wallet->execute([$ownerId]);
            if ($wallet->fetch() === false) {
                $pdo->prepare('INSERT INTO wallets (user_id, available_balance, eagle_lands_balance) VALUES (?, 0, 0)')
                    ->execute([$ownerId]);
            }

            $reference = 'SB-' . $storeId . '-L' . $level . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            try {
                $pdo->prepare(
                    'INSERT INTO store_sales_bonus_awards
                        (store_id, owner_user_id, threshold_level, target_amount, bonus_amount, sales_total, reference)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $storeId,
                    $ownerId,
                    $level,
                    $config['target'],
                    $config['bonus'],
                    $salesTotal,
                    $reference,
                ]);
            } catch (PDOException $e) {
                // Only treat MySQL duplicate-key (1062) as an already-awarded race.
                $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
                if ($driverCode === 1062) {
                    continue;
                }
                throw $e;
            }

            $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?')
                ->execute([$config['bonus'], $ownerId]);

            $milestone = $config['target'] * $level;
            $pdo->prepare(
                'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
                 VALUES (?, ?, \'Store Bonus\', ?, ?, \'Approved\', CURDATE())'
            )->execute([
                $ownerId,
                $reference,
                'Store sales bonus for reaching ?' . number_format($milestone, 0) . ' completed sales',
                $config['bonus'],
            ]);

            $awarded[] = [
                'level' => $level,
                'target' => $config['target'],
                'milestone' => $milestone,
                'bonus' => $config['bonus'],
                'reference' => $reference,
            ];
        }

        if ($ownsTx) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'awarded' => $awarded,
        'salesTotal' => store_completed_sales_total($storeId, $pdo),
        'config' => $config,
    ];
}

function store_sales_bonus_summary(int $storeId, int $ownerUserId, ?PDO $pdo = null): array
{
    $pdo = $pdo ?: db();
    ensure_store_sales_bonus_schema($pdo);
    $config = store_sales_bonus_config($pdo);
    $salesTotal = store_completed_sales_total($storeId, $pdo);
    $target = (float) $config['target'];
    $levelsEarned = $target > 0 ? (int) floor($salesTotal / $target) : 0;
    $intoCurrent = $target > 0 ? fmod($salesTotal, $target) : 0.0;
    if ($intoCurrent < 0) {
        $intoCurrent = 0.0;
    }
    $remaining = $target > 0 ? max(0, $target - $intoCurrent) : 0.0;
    if ($levelsEarned > 0 && abs($intoCurrent) < 0.00001) {
        $intoCurrent = $target;
        $remaining = 0.0;
    }

    $wallet = $pdo->prepare('SELECT available_balance FROM wallets WHERE user_id = ?');
    $wallet->execute([$ownerUserId]);
    $balance = (float) ($wallet->fetchColumn() ?: 0);

    $awardsStmt = $pdo->prepare(
        'SELECT threshold_level, target_amount, bonus_amount, sales_total, reference, created_at
         FROM store_sales_bonus_awards
         WHERE store_id = ?
         ORDER BY threshold_level DESC'
    );
    $awardsStmt->execute([$storeId]);
    $awards = [];
    $bonusPaid = 0.0;
    foreach ($awardsStmt as $row) {
        $bonusPaid += (float) $row['bonus_amount'];
        $awards[] = [
            'level' => (int) $row['threshold_level'],
            'target' => (float) $row['target_amount'],
            'bonus' => (float) $row['bonus_amount'],
            'salesTotal' => (float) $row['sales_total'],
            'milestone' => (float) $row['target_amount'] * (int) $row['threshold_level'],
            'reference' => $row['reference'],
            'date' => date('d M Y H:i', strtotime($row['created_at'])),
        ];
    }

    return [
        'target' => $target,
        'bonus' => (float) $config['bonus'],
        'enabled' => !empty($config['enabled']),
        'salesTotal' => $salesTotal,
        'levelsEarned' => $levelsEarned,
        'progressAmount' => round($intoCurrent, 2),
        'remainingToNext' => round($remaining, 2),
        'progressPercent' => $target > 0 ? min(100, round(($intoCurrent / $target) * 100, 1)) : 0,
        'walletBalance' => $balance,
        'bonusPaid' => round($bonusPaid, 2),
        'awards' => $awards,
    ];
}

/**
 * Eagle land and Homes LTD 40/60 split applies only from Vision through Platinum.
 * Plans below Vision (or no package) withdraw without that reduction.
 */
function eagle_lands_package_codes(): array
{
    return ['vision', 'silver', 'gold', 'diamond', 'platinum'];
}

function package_uses_eagle_lands_split(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    $code = strtolower(trim((string) ($user['package_code'] ?? '')));
    if ($code === '') {
        return false;
    }
    // Strict: Vision through Platinum only (no package-price fallback).
    return in_array($code, eagle_lands_package_codes(), true);
}

function withdrawal_policy(?array $user = null): array
{
    ensure_eagle_lands_schema();
    $cashPercent = max(0, min(100, (float) setting('withdraw_cash_percent', '40')));
    $reservePercent = max(0, min(100, (float) setting('withdraw_reserve_percent', '60')));
    if ($cashPercent <= 0) {
        $cashPercent = 40;
    }
    if ($reservePercent <= 0) {
        $reservePercent = 60;
    }
    $now = new DateTimeImmutable('now', app_timezone());
    $isSaturday = ((int) $now->format('N')) === 6;
    $eligible = package_uses_eagle_lands_split($user);
    if (!$eligible) {
        $cashPercent = 100;
        $reservePercent = 0;
    }
    return [
        'day' => 'Saturday',
        'cashPercent' => $cashPercent,
        'reservePercent' => $reservePercent,
        'isWithdrawDay' => $isSaturday,
        'today' => $now->format('l'),
        'timezone' => 'Africa/Lagos',
        'eagleLandsEligible' => $eligible,
        'eagleLandsSplit' => $eligible && $reservePercent > 0,
        'eagleLandsPackages' => eagle_lands_package_codes(),
    ];
}

function withdrawal_split(float $cashAmount, ?array $user = null): array
{
    $policy = withdrawal_policy($user);
    $cashPercent = (float) $policy['cashPercent'];
    $reservePercent = (float) $policy['reservePercent'];
    $cash = round($cashAmount, 2);
    if ($reservePercent <= 0 || $cashPercent <= 0 || empty($policy['eagleLandsSplit'])) {
        return [
            'cash' => $cash,
            'reserved' => 0.0,
            'total' => $cash,
            'cashPercent' => $cashPercent,
            'reservePercent' => 0.0,
            'eagleLandsSplit' => false,
        ];
    }
    $reserved = round($cash * ($reservePercent / $cashPercent), 2);
    return [
        'cash' => $cash,
        'reserved' => $reserved,
        'total' => round($cash + $reserved, 2),
        'cashPercent' => $cashPercent,
        'reservePercent' => $reservePercent,
        'eagleLandsSplit' => true,
    ];
}

function require_user(?string $role = null): array
{
    $user = current_user();
    if (!$user) {
        json_out(['ok' => false, 'error' => 'Sign in required.'], 401);
    }
    if ($role !== null && $user['role'] !== $role) {
        json_out(['ok' => false, 'error' => 'This account cannot open that area.'], 403);
    }
    return $user;
}

function get_leg_under_ancestor(PDO $pdo, int $childId, int $ancestorId): ?string
{
    $parentStmt = $pdo->prepare('SELECT parent_id, binary_side FROM users WHERE id = ?');
    $curr = $childId;
    $guard = 0;
    while ($curr > 0 && $guard < 60) {
        $parentStmt->execute([$curr]);
        $row = $parentStmt->fetch();
        if (!$row || empty($row['parent_id'])) {
            return null;
        }
        if ((int) $row['parent_id'] === $ancestorId) {
            return $row['binary_side'] ?: null;
        }
        $curr = (int) $row['parent_id'];
        $guard++;
    }
    return null;
}

function is_binary_qualified(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE sponsor_id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $sponsored = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($sponsored) < 2) {
        return false;
    }
    $hasLeft = false;
    $hasRight = false;
    foreach ($sponsored as $childId) {
        $leg = get_leg_under_ancestor($pdo, (int) $childId, $userId);
        if ($leg === 'left') {
            $hasLeft = true;
        } elseif ($leg === 'right') {
            $hasRight = true;
        }
        if ($hasLeft && $hasRight) {
            return true;
        }
    }
    return false;
}

function settle_binary_pairing(PDO $pdo, int $userId): ?array
{
    $pairPv = (int) setting('pair_pv', '60');
    if ($pairPv <= 0) {
        $pairPv = 60;
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.left_pv, u.right_pv, u.status, u.package_id,
                p.name AS package_name, p.pair_amount, p.daily_pairs, p.daily_cap
         FROM users u
         LEFT JOIN packages p ON p.id = u.package_id
         WHERE u.id = ? AND u.role = \'member\''
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active' || empty($user['package_id'])) {
        return null;
    }

    $qualRequired = setting('binary_qualification', '1') === '1';
    if ($qualRequired && !is_binary_qualified($pdo, $userId)) {
        return null;
    }

    $leftPv = (int) $user['left_pv'];
    $rightPv = (int) $user['right_pv'];
    $possiblePairs = (int) floor(min($leftPv, $rightPv) / $pairPv);
    if ($possiblePairs < 1) {
        return null;
    }

    $pairAmount = (float) $user['pair_amount'];
    $dailyPairsLimit = (int) $user['daily_pairs'];
    $dailyCapLimit = (float) $user['daily_cap'];

    $todayStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS amount_today, COALESCE(SUM(pv), 0) AS pv_today
         FROM commissions
         WHERE user_id = ? AND commission_type = \'Pairing\' AND earned_on = CURDATE()'
    );
    $todayStmt->execute([$userId]);
    $todayRow = $todayStmt->fetch();
    $amountEarnedToday = (float) $todayRow['amount_today'];
    $pairsEarnedToday = (int) floor(((int) $todayRow['pv_today']) / $pairPv);

    $remainingPairs = max(0, $dailyPairsLimit - $pairsEarnedToday);
    if ($dailyCapLimit > 0 && $pairAmount > 0) {
        $remainingByCap = max(0, (int) floor(($dailyCapLimit - $amountEarnedToday) / $pairAmount));
        $remainingPairs = min($remainingPairs, $remainingByCap);
    }

    $pairsToPay = min($possiblePairs, $remainingPairs);
    if ($pairsToPay < 1) {
        return null;
    }

    $pvDeducted = $pairsToPay * $pairPv;
    $totalAmount = round($pairsToPay * $pairAmount, 2);

    // Deduct matched PV
    $pdo->prepare('UPDATE users SET left_pv = left_pv - ?, right_pv = right_pv - ? WHERE id = ?')
        ->execute([$pvDeducted, $pvDeducted, $userId]);

    // Insert commission
    $pdo->prepare(
        'INSERT INTO commissions (user_id, source_label, commission_type, package_name, pv, amount, status, earned_on)
         VALUES (?, \'Left / Right match\', \'Pairing\', ?, ?, ?, \'Approved\', CURDATE())'
    )->execute([$userId, $user['package_name'], $pvDeducted, $totalAmount]);

    // Insert transaction
    $reference = 'TX-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    $description = $pairsToPay === 1 ? 'Pairing match (1 pair)' : "Pairing match ({$pairsToPay} pairs)";
    $pdo->prepare(
        'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
         VALUES (?, ?, \'Commission\', ?, ?, \'Approved\', CURDATE())'
    )->execute([$userId, $reference, $description, $totalAmount]);

    // Update wallet
    $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?')
        ->execute([$totalAmount, $userId]);

    return [
        'userId' => $userId,
        'pairs' => $pairsToPay,
        'pv' => $pvDeducted,
        'amount' => $totalAmount,
    ];
}

function process_upline_pairing(PDO $pdo, int $startParentId): array
{
    $results = [];
    $current = $startParentId;
    $parentStmt = $pdo->prepare('SELECT parent_id FROM users WHERE id = ?');
    $guard = 0;
    while ($current > 0 && $guard < 60) {
        $res = settle_binary_pairing($pdo, $current);
        if ($res) {
            $results[] = $res;
        }
        $parentStmt->execute([$current]);
        $next = $parentStmt->fetchColumn();
        $current = $next ? (int) $next : 0;
        $guard++;
    }
    return $results;
}

if (!function_exists('add_leg_pv')) {
    function add_leg_pv(PDO $pdo, int $parentId, string $side, int $pv): void
    {
        $update = $pdo->prepare('UPDATE users SET left_pv = left_pv + ? WHERE id = ?');
        $updateRight = $pdo->prepare('UPDATE users SET right_pv = right_pv + ? WHERE id = ?');
        $parent = $pdo->prepare('SELECT parent_id, binary_side FROM users WHERE id = ?');
        $current = $parentId;
        $leg = $side;
        $guard = 0;
        while ($current && $guard < 60) {
            if ($leg === 'left') {
                $update->execute([$pv, $current]);
            } else {
                $updateRight->execute([$pv, $current]);
            }
            $parent->execute([$current]);
            $row = $parent->fetch();
            if (!$row || empty($row['parent_id'])) {
                break;
            }
            $leg = $row['binary_side'] ?: $leg;
            $current = (int) $row['parent_id'];
            $guard++;
        }
    }
}

if (!function_exists('pay_indirect')) {
    /** Alias ? authoritative implementation is registration_pay_indirect(). */
    function pay_indirect(PDO $pdo, int $newUserId, string $newName, int $sponsorId, array $packageRow): void
    {
        registration_pay_indirect($pdo, $newUserId, $newName, $sponsorId, $packageRow);
    }
}
