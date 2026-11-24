<?php
/**
 * Creates sellable products and switches product orders off MLM packages.
 * Products: Cellvigor and Vigormax capsule at ₦20,000 each.
 * Run: php database/migrate_products.php
 */
require_once __DIR__ . '/../public/api/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS products (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(40) NOT NULL UNIQUE,
      name VARCHAR(120) NOT NULL,
      description TEXT NULL,
      price DECIMAL(12,2) NOT NULL,
      unit_label VARCHAR(40) NOT NULL DEFAULT 'unit',
      status ENUM('active','inactive') NOT NULL DEFAULT 'active',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )"
);
fwrite(STDOUT, "products table ready.\n");

$seed = [
    ['cellvigor', 'Cellvigor', 'Cellvigor herbal product.', 20000, 'unit'],
    ['vigormax', 'Vigormax capsule', 'Vigormax capsule product.', 20000, 'capsule'],
];

$upsert = $pdo->prepare(
    'INSERT INTO products (code, name, description, price, unit_label, status)
     VALUES (?, ?, ?, ?, ?, \'active\')
     ON DUPLICATE KEY UPDATE
       name = VALUES(name),
       description = VALUES(description),
       price = VALUES(price),
       unit_label = VALUES(unit_label),
       status = \'active\''
);

foreach ($seed as $row) {
    $upsert->execute($row);
    fwrite(STDOUT, "Seeded product {$row[1]} at ₦" . number_format($row[3], 0) . ".\n");
}

$orderCols = $pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN);
$hasProductId = in_array('product_id', $orderCols, true);
$hasPackageId = in_array('package_id', $orderCols, true);
$hasUnitPrice = in_array('unit_price', $orderCols, true);

if (!$hasProductId) {
    $pdo->exec('ALTER TABLE orders ADD COLUMN product_id INT UNSIGNED NULL AFTER store_id');
    fwrite(STDOUT, "Added orders.product_id.\n");
}

if (!$hasUnitPrice) {
    $pdo->exec('ALTER TABLE orders ADD COLUMN unit_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quantity');
    fwrite(STDOUT, "Added orders.unit_price.\n");
}

$fkRows = $pdo->query(
    "SELECT CONSTRAINT_NAME
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orders'
       AND COLUMN_NAME = 'package_id'
       AND REFERENCED_TABLE_NAME IS NOT NULL"
)->fetchAll(PDO::FETCH_COLUMN);

foreach ($fkRows as $fk) {
    $pdo->exec('ALTER TABLE orders DROP FOREIGN KEY `' . str_replace('`', '``', $fk) . '`');
    fwrite(STDOUT, "Dropped foreign key {$fk}.\n");
}

if ($hasPackageId) {
    // Existing bookings referenced MLM packages; clear them so the product FK can be enforced.
    $cleared = $pdo->exec('DELETE FROM order_notifications');
    $clearedOrders = $pdo->exec('DELETE FROM orders');
    fwrite(STDOUT, "Cleared legacy package orders (orders={$clearedOrders}, notifications={$cleared}).\n");
    $pdo->exec('ALTER TABLE orders DROP COLUMN package_id');
    fwrite(STDOUT, "Dropped orders.package_id.\n");
}

$productFk = $pdo->query(
    "SELECT CONSTRAINT_NAME
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orders'
       AND COLUMN_NAME = 'product_id'
       AND REFERENCED_TABLE_NAME = 'products'"
)->fetchColumn();

if (!$productFk) {
    $pdo->exec(
        'ALTER TABLE orders
         MODIFY product_id INT UNSIGNED NOT NULL,
         ADD CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id)'
    );
    fwrite(STDOUT, "Linked orders.product_id to products.\n");
} else {
    $pdo->exec('ALTER TABLE orders MODIFY product_id INT UNSIGNED NOT NULL');
    fwrite(STDOUT, "orders.product_id already linked.\n");
}

fwrite(STDOUT, "Product migration complete.\n");
