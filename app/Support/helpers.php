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

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    global $config;
    $pdo = new PDO(
        'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=utf8mb4',
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
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
        $pdo->exec("ALTER TABLE packages ADD COLUMN description TEXT NOT NULL DEFAULT '' AFTER name");
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
    $addedPaymentStatus = false;
    if (!in_array('payment_status', $cols, true)) {
        $pdo->exec(
            "ALTER TABLE orders
             ADD COLUMN payment_status ENUM('unpaid','pending','paid','failed') NOT NULL DEFAULT 'unpaid'
             AFTER status"
        );
        $addedPaymentStatus = true;
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
    if ($addedPaymentStatus) {
        // Legacy orders placed before Paystack gate stay visible to stores.
        $pdo->exec("UPDATE orders SET payment_status = 'paid' WHERE payment_status = 'unpaid' AND created_at < NOW()");
    }

    // Unique payment refs (NULLs allowed multiple times in MySQL unique indexes).
    $indexes = $pdo->query('SHOW INDEX FROM orders')->fetchAll(PDO::FETCH_ASSOC);
    $indexNames = [];
    foreach ($indexes as $idx) {
        $indexNames[(string) $idx['Key_name']] = true;
    }
    if (empty($indexNames['uq_orders_payment_reference'])) {
        $pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_payment_reference (payment_reference)');
    }
    if (empty($indexNames['uq_orders_paystack_reference'])) {
        $pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_paystack_reference (paystack_reference)');
    }

    $notifExists = $pdo->query("SHOW TABLES LIKE 'order_notifications'")->fetch();
    if ($notifExists) {
        $nIndexes = $pdo->query('SHOW INDEX FROM order_notifications')->fetchAll(PDO::FETCH_ASSOC);
        $nNames = [];
        foreach ($nIndexes as $idx) {
            $nNames[(string) $idx['Key_name']] = true;
        }
        if (empty($nNames['uq_order_notifications_order'])) {
            // Safe when no duplicate order_id rows exist (pre-checked in Phase 1 audit).
            $dup = (int) $pdo->query(
                'SELECT COUNT(*) FROM (
                    SELECT order_id FROM order_notifications GROUP BY order_id HAVING COUNT(*) > 1
                 ) d'
            )->fetchColumn();
            if ($dup === 0) {
                $pdo->exec('ALTER TABLE order_notifications ADD UNIQUE KEY uq_order_notifications_order (order_id)');
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
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS landing_features (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $done = true;
}

function ensure_smtp_settings(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?: db();
    try {
        $pdo->exec('ALTER TABLE settings MODIFY setting_value TEXT NOT NULL');
    } catch (Throwable $e) {
        // Column may already be TEXT or migration may lack privilege; ignore.
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

    $cols = $pdo->query('SHOW COLUMNS FROM registration_payments')->fetchAll(PDO::FETCH_COLUMN);
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
    // Re-fetch after possible ALTERs above.
    $cols = $pdo->query('SHOW COLUMNS FROM registration_payments')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('sponsor_code', $cols, true)) {
        $add("ALTER TABLE registration_payments ADD COLUMN sponsor_code VARCHAR(80) NOT NULL DEFAULT '' AFTER package_name");
    }
    if (!in_array('placement_side', $cols, true)) {
        $add("ALTER TABLE registration_payments ADD COLUMN placement_side VARCHAR(10) NOT NULL DEFAULT '' AFTER sponsor_code");
    }
    $statusCol = $pdo->query("SHOW COLUMNS FROM registration_payments LIKE 'status'")->fetch();
    $type = (string) ($statusCol['Type'] ?? '');
    if ($type !== '' && stripos($type, 'rejected') === false) {
        $pdo->exec(
            "ALTER TABLE registration_payments
             MODIFY COLUMN status ENUM('pending','paid','registered','funded','rejected') NOT NULL DEFAULT 'pending'"
        );
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
    if (!is_array($data) || strtolower((string) ($data['status'] ?? '')) !== 'success') {
        return ['ok' => false, 'error' => 'Payment was not successful.'];
    }

    $verifiedRef = trim((string) ($data['reference'] ?? ''));
    $expectedRef = trim((string) ($expects['reference'] ?? $reference));
    if ($verifiedRef === '' || !hash_equals($expectedRef, $verifiedRef)) {
        return ['ok' => false, 'error' => 'Paystack reference does not match the expected payment reference.'];
    }

    $currency = strtoupper(trim((string) ($expects['currency'] ?? 'NGN')));
    $verifiedCurrency = strtoupper(trim((string) ($data['currency'] ?? '')));
    if ($verifiedCurrency !== $currency) {
        return ['ok' => false, 'error' => 'Payment currency must be ' . $currency . '.'];
    }

    if (array_key_exists('amount_kobo', $expects)) {
        $expectedKobo = (int) $expects['amount_kobo'];
        $verifiedKobo = (int) ($data['amount'] ?? -1);
        if ($expectedKobo < 1 || $verifiedKobo !== $expectedKobo) {
            return ['ok' => false, 'error' => 'Paid amount does not match the expected amount.'];
        }
    }

    $expectedPurpose = trim((string) ($expects['purpose'] ?? ''));
    if ($expectedPurpose !== '') {
        $meta = $data['metadata'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        $purpose = trim((string) ($meta['purpose'] ?? ''));
        // If Paystack returns a purpose, it must match. Missing purpose is tolerated
        // only because the internal payment/order row was already bound by reference.
        if ($purpose !== '' && !hash_equals($expectedPurpose, $purpose)) {
            return ['ok' => false, 'error' => 'Payment purpose does not match this transaction type.'];
        }
    }

    return [
        'ok' => true,
        'data' => $data,
        'reference' => $verifiedRef,
        'amount_kobo' => (int) ($data['amount'] ?? 0),
        'currency' => $verifiedCurrency,
    ];
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
        $pdo->exec("ALTER TABLE users ADD COLUMN award_rank VARCHAR(60) NOT NULL DEFAULT 'Member' AFTER package_id");
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
    if (!in_array('eagle_lands_balance', $walletCols, true)) {
        $pdo->exec('ALTER TABLE wallets ADD COLUMN eagle_lands_balance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER available_balance');
    }

    $wdCols = $pdo->query('SHOW COLUMNS FROM withdrawals')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('reserved_amount', $wdCols, true)) {
        $pdo->exec('ALTER TABLE withdrawals ADD COLUMN reserved_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount');
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

if (!function_exists('open_seat')) {
    function open_seat(PDO $pdo, int $startId, string $side): array
    {
        $current = $startId;
        $guard = 0;
        $stmt = $pdo->prepare('SELECT id FROM users WHERE parent_id = ? AND binary_side = ? LIMIT 1');
        while ($guard < 60) {
            $stmt->execute([$current, $side]);
            $child = $stmt->fetchColumn();
            if (!$child) {
                return ['parent_id' => $current, 'side' => $side];
            }
            $current = (int) $child;
            $guard++;
        }
        json_out(['ok' => false, 'error' => 'That side has no open seat.'], 422);
    }
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
    function pay_indirect(PDO $pdo, int $newUserId, string $newName, int $sponsorId, array $packageRow): void
    {
        $rates = [2, 1, 1, 0.5, 0.5];
        $upline = $pdo->prepare('SELECT sponsor_id FROM users WHERE id = ?');
        $status = $pdo->prepare('SELECT status FROM users WHERE id = ?');
        $current = $sponsorId;
        foreach ($rates as $index => $rate) {
            $upline->execute([$current]);
            $ancestorId = (int) $upline->fetchColumn();
            if ($ancestorId < 1) {
                break;
            }
            $status->execute([$ancestorId]);
            $ancestorStatus = (string) $status->fetchColumn();
            $amount = round(((float) $packageRow['price']) * $rate / 100, 2);
            if ($ancestorStatus !== 'suspended' && $amount > 0) {
                $pdo->prepare(
                    'INSERT INTO commissions (user_id, source_label, commission_type, package_name, pv, amount, status, earned_on)
                     VALUES (?, ?, \'Indirect Referral\', ?, ?, ?, \'Approved\', CURDATE())'
                )->execute([$ancestorId, $newName, $packageRow['name'], (int) $packageRow['pv'], $amount]);
                $reference = sprintf('ZGV-I%d-%05d-%05d', $index + 1, $newUserId, $ancestorId);
                $pdo->prepare(
                    'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
                     VALUES (?, ?, \'Commission\', ?, ?, \'Approved\', CURDATE())'
                )->execute([$ancestorId, $reference, 'Indirect referral', $amount]);
                $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?')
                    ->execute([$amount, $ancestorId]);
            }
            $current = $ancestorId;
        }
    }
}
