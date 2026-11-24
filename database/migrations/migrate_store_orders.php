<?php
/**
 * Adds store role + stores/orders/notifications tables.
 * Run: php database/migrations/migrate_store_orders.php
 */
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();

$roleCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
if ($roleCol && strpos((string) $roleCol['Type'], 'store') === false) {
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('member','admin','store') NOT NULL DEFAULT 'member'");
    fwrite(STDOUT, "Updated users.role to include store.\n");
} else {
    fwrite(STDOUT, "users.role already supports store.\n");
}

$tables = [
    'stores' => "CREATE TABLE IF NOT EXISTS stores (
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
    'orders' => "CREATE TABLE IF NOT EXISTS orders (
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
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_orders_member FOREIGN KEY (member_id) REFERENCES users(id),
      CONSTRAINT fk_orders_store FOREIGN KEY (store_id) REFERENCES stores(id),
      CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id)
    )",
    'order_notifications' => "CREATE TABLE IF NOT EXISTS order_notifications (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      store_id INT UNSIGNED NOT NULL,
      order_id INT UNSIGNED NOT NULL,
      is_read TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_order_notifications_store FOREIGN KEY (store_id) REFERENCES stores(id),
      CONSTRAINT fk_order_notifications_order FOREIGN KEY (order_id) REFERENCES orders(id)
    )",
];

foreach ($tables as $name => $sql) {
    $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($name))->fetch();
    if ($exists) {
        fwrite(STDOUT, "{$name} already exists.\n");
        continue;
    }
    $pdo->exec($sql);
    fwrite(STDOUT, "Created {$name}.\n");
}

fwrite(STDOUT, "Store order migration complete.\n");
