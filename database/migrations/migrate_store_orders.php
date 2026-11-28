<?php
/**
 * Legacy store/orders migrator (CLI only).
 *
 * Default: dry-run inspection (no DDL).
 * Apply:   php database/migrations/migrate_store_orders.php --apply --i-understand-ddl
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

echo "=== migrate_store_orders (legacy) ===\n";
echo 'Mode: ' . ($apply ? 'apply' : 'dry-run') . "\n";
echo "Note: connected database name is not printed.\n";

if ($expectDb !== '' && !hash_equals($expectDb, $dbName)) {
    fwrite(STDERR, "REFUSED: database identity does not match ZENVITA_MIGRATE_EXPECT_DB.\n");
    exit(1);
}

$roleCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
$roleNeedsStore = $roleCol && strpos((string) $roleCol['Type'], 'store') === false;
echo $roleNeedsStore
    ? "Stage A: users.role would ADD store enum value\n"
    : "Stage A: users.role already supports store\n";

$tables = ['stores', 'orders', 'order_notifications'];
foreach ($tables as $name) {
    $exists = (bool) $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($name))->fetch();
    echo $exists
        ? "Stage A: {$name} present\n"
        : "Stage A: {$name} MISSING (would CREATE)\n";
}

if (!$apply) {
    echo "Dry-run complete. No DDL executed. Re-run with --apply --i-understand-ddl to apply.\n";
    exit(0);
}

if (!$confirm) {
    fwrite(STDERR, "REFUSED: apply requires --i-understand-ddl.\n");
    exit(1);
}

if ($roleNeedsStore) {
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('member','admin','store') NOT NULL DEFAULT 'member'");
    fwrite(STDOUT, "Updated users.role to include store.\n");
} else {
    fwrite(STDOUT, "users.role already supports store.\n");
}

$createSql = [
    'stores' => "CREATE TABLE stores (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      owner_user_id INT UNSIGNED NOT NULL UNIQUE,
      name VARCHAR(120) NOT NULL,
      address VARCHAR(255) NOT NULL DEFAULT '',
      city VARCHAR(80) NOT NULL DEFAULT '',
      phone VARCHAR(30) NOT NULL DEFAULT '',
      status ENUM('active','inactive') NOT NULL DEFAULT 'active',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_stores_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
    )",
    'orders' => "CREATE TABLE orders (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      reference VARCHAR(40) NOT NULL UNIQUE,
      member_id INT UNSIGNED NOT NULL,
      store_id INT UNSIGNED NOT NULL,
      product_id INT UNSIGNED NOT NULL,
      quantity INT UNSIGNED NOT NULL DEFAULT 1,
      unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
      fulfillment ENUM('home_delivery','pickup') NOT NULL,
      full_name VARCHAR(160) NOT NULL,
      phone VARCHAR(30) NOT NULL,
      email VARCHAR(120) NOT NULL DEFAULT '',
      delivery_address VARCHAR(255) NOT NULL DEFAULT '',
      delivery_city VARCHAR(80) NOT NULL DEFAULT '',
      delivery_notes VARCHAR(255) NOT NULL DEFAULT '',
      preferred_pickup_at VARCHAR(120) NOT NULL DEFAULT '',
      status ENUM('Pending','Accepted','Ready','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
      payment_status ENUM('unpaid','pending','paid','failed') NOT NULL DEFAULT 'unpaid',
      payment_reference VARCHAR(64) NULL DEFAULT NULL,
      paystack_reference VARCHAR(64) NULL DEFAULT NULL,
      paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
      paid_at DATETIME NULL DEFAULT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_orders_member FOREIGN KEY (member_id) REFERENCES users(id),
      CONSTRAINT fk_orders_store FOREIGN KEY (store_id) REFERENCES stores(id),
      CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id)
    )",
    'order_notifications' => "CREATE TABLE order_notifications (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      store_id INT UNSIGNED NOT NULL,
      order_id INT UNSIGNED NOT NULL,
      is_read TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_order_notifications_store FOREIGN KEY (store_id) REFERENCES stores(id),
      CONSTRAINT fk_order_notifications_order FOREIGN KEY (order_id) REFERENCES orders(id)
    )",
];

foreach ($createSql as $name => $sql) {
    $exists = (bool) $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($name))->fetch();
    if ($exists) {
        fwrite(STDOUT, "{$name} already exists.\n");
        continue;
    }
    $pdo->exec($sql);
    fwrite(STDOUT, "Created {$name}.\n");
}

fwrite(STDOUT, "Store order migration complete.\n");
