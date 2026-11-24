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
    echo json_encode($data);
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
    $done = true;
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

function verify_paystack_reference(string $reference): array
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
    return ['ok' => true, 'data' => $data];
}

function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT u.id, u.member_code, u.username, u.email, u.phone, u.first_name, u.last_name,
                u.role, u.status, u.personal_pv, u.left_pv, u.right_pv, u.binary_side,
                u.city, u.country, u.bank_name, u.account_name, u.account_number,
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
            'sponsor' => $row['sponsor_code'] ?: '—',
            'parent' => $row['parent_code'] ?: '—',
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

function withdrawal_policy(): array
{
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
    return [
        'day' => 'Saturday',
        'cashPercent' => $cashPercent,
        'reservePercent' => $reservePercent,
        'isWithdrawDay' => $isSaturday,
        'today' => $now->format('l'),
        'timezone' => 'Africa/Lagos',
    ];
}

function withdrawal_split(float $cashAmount): array
{
    $policy = withdrawal_policy();
    $cashPercent = (float) $policy['cashPercent'];
    $reservePercent = (float) $policy['reservePercent'];
    $reserved = round($cashAmount * ($reservePercent / $cashPercent), 2);
    return [
        'cash' => round($cashAmount, 2),
        'reserved' => $reserved,
        'total' => round($cashAmount + $reserved, 2),
        'cashPercent' => $cashPercent,
        'reservePercent' => $reservePercent,
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
