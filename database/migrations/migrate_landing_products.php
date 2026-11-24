<?php
/**
 * Adds product landing fields + homepage features table.
 * Run: php database/migrations/migrate_landing_products.php
 */
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();
$cols = $pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('features', $cols, true)) {
    $pdo->exec("ALTER TABLE products ADD COLUMN features TEXT NOT NULL DEFAULT '[]' AFTER description");
    fwrite(STDOUT, "Added products.features\n");
}
if (!in_array('sort_order', $cols, true)) {
    $pdo->exec('ALTER TABLE products ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER status');
    fwrite(STDOUT, "Added products.sort_order\n");
}
if (!in_array('show_on_landing', $cols, true)) {
    $pdo->exec("ALTER TABLE products ADD COLUMN show_on_landing TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order");
    fwrite(STDOUT, "Added products.show_on_landing\n");
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
fwrite(STDOUT, "landing_features table ready\n");

$pdo->prepare(
    "UPDATE products SET features = ?, sort_order = 1, show_on_landing = 1 WHERE code = 'cellvigor' AND (features = '' OR features = '[]')"
)->execute([json_encode([
    'Boosts immunity',
    'Enhances energy',
    'Supports cellular health',
    'Promotes overall wellness',
], JSON_UNESCAPED_UNICODE)]);

$pdo->prepare(
    "UPDATE products SET features = ?, sort_order = 2, show_on_landing = 1 WHERE code = 'vigormax' AND (features = '' OR features = '[]')"
)->execute([json_encode([
    'Supports strength and stamina',
    'Helps restore daily balance',
    'Part of the Cellvigor product line',
], JSON_UNESCAPED_UNICODE)]);

$count = (int) $pdo->query('SELECT COUNT(*) FROM landing_features')->fetchColumn();
if ($count < 1) {
    $ins = $pdo->prepare('INSERT INTO landing_features (title, sort_order, status) VALUES (?, ?, \'active\')');
    $defaults = [
        'Boosts immunity',
        'Enhances energy',
        'Supports cellular health',
        'Promotes overall wellness',
    ];
    foreach ($defaults as $i => $title) {
        $ins->execute([$title, $i + 1]);
    }
    fwrite(STDOUT, "Seeded landing features\n");
}

fwrite(STDOUT, "Migration complete.\n");
