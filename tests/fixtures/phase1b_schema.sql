CREATE DATABASE IF NOT EXISTS zenvita_phase1b_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zenvita_phase1b_test;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_notifications;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS stores;
DROP TABLE IF EXISTS registration_payments;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS commissions;
DROP TABLE IF EXISTS pairs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS wallets;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS packages;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(40) NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  price DECIMAL(12,2) NOT NULL,
  bottles INT UNSIGNED NOT NULL DEFAULT 0,
  pv INT UNSIGNED NOT NULL DEFAULT 0,
  direct_bonus DECIMAL(12,2) NOT NULL DEFAULT 0,
  daily_pairs INT UNSIGNED NOT NULL DEFAULT 0,
  pair_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  daily_cap DECIMAL(12,2) NOT NULL DEFAULT 0
);

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_code VARCHAR(20) NULL UNIQUE,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(120) NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL DEFAULT '',
  role ENUM('member','admin','store') NOT NULL DEFAULT 'member',
  package_id INT UNSIGNED NULL,
  award_rank VARCHAR(60) NOT NULL DEFAULT 'Member',
  sponsor_id INT UNSIGNED NULL,
  parent_id INT UNSIGNED NULL,
  binary_side ENUM('left','right') NULL,
  status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  personal_pv INT UNSIGNED NOT NULL DEFAULT 0,
  left_pv INT UNSIGNED NOT NULL DEFAULT 0,
  right_pv INT UNSIGNED NOT NULL DEFAULT 0,
  city VARCHAR(80) NOT NULL DEFAULT '',
  country VARCHAR(80) NOT NULL DEFAULT '',
  bank_name VARCHAR(80) NOT NULL DEFAULT '',
  account_name VARCHAR(120) NOT NULL DEFAULT '',
  account_number VARCHAR(30) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_t_users_package FOREIGN KEY (package_id) REFERENCES packages(id),
  CONSTRAINT fk_t_users_sponsor FOREIGN KEY (sponsor_id) REFERENCES users(id),
  CONSTRAINT fk_t_users_parent FOREIGN KEY (parent_id) REFERENCES users(id),
  UNIQUE KEY uq_users_parent_side (parent_id, binary_side)
);

CREATE TABLE wallets (
  user_id INT UNSIGNED PRIMARY KEY,
  available_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  eagle_lands_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_t_wallets_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE settings (
  setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value TEXT NOT NULL
);

CREATE TABLE pairs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  pair_count INT UNSIGNED NOT NULL DEFAULT 0,
  earned_on DATE NOT NULL,
  KEY idx_pairs_user_day (user_id, earned_on)
);

CREATE TABLE registration_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  package_id INT UNSIGNED NULL,
  package_name VARCHAR(40) NOT NULL DEFAULT '',
  sponsor_code VARCHAR(60) NULL,
  placement_side ENUM('left','right') NULL,
  payment_method VARCHAR(20) NOT NULL DEFAULT 'paystack',
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  paystack_reference VARCHAR(80) NULL,
  proof_path VARCHAR(255) NULL,
  admin_note VARCHAR(255) NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE stores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL
);

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_t_products_store FOREIGN KEY (store_id) REFERENCES stores(id)
);

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(80) NOT NULL UNIQUE,
  store_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL,
  email VARCHAR(120) NULL,
  payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
  payment_reference VARCHAR(80) NULL,
  paystack_reference VARCHAR(80) NULL,
  paid_amount DECIMAL(12,2) NULL,
  paid_at DATETIME NULL,
  CONSTRAINT fk_t_orders_store FOREIGN KEY (store_id) REFERENCES stores(id),
  CONSTRAINT fk_t_orders_member FOREIGN KEY (member_id) REFERENCES users(id),
  CONSTRAINT fk_t_orders_product FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE order_notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_order_notifications_order (order_id)
);

CREATE TABLE commissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  source_label VARCHAR(120) NOT NULL,
  commission_type VARCHAR(40) NOT NULL,
  package_name VARCHAR(40) NOT NULL,
  pv INT UNSIGNED NOT NULL DEFAULT 0,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('Pending','Approved','Paid','Rejected') NOT NULL DEFAULT 'Pending',
  earned_on DATE NOT NULL
);

CREATE TABLE transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  reference VARCHAR(40) NOT NULL UNIQUE,
  tx_type VARCHAR(40) NOT NULL,
  description VARCHAR(180) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('Pending','Approved','Paid','Rejected') NOT NULL DEFAULT 'Pending',
  tx_date DATE NOT NULL
);

INSERT INTO packages (id, code, name, price, bottles, pv, direct_bonus, daily_pairs, pair_amount, daily_cap) VALUES
(1, 'vision', 'Vision', 150000.00, 8, 150, 45000.00, 10, 3000.00, 30000.00);

INSERT INTO settings (setting_key, setting_value) VALUES
('pair_pv', '60'),
('binary_qualification', '0'),
('site_name', 'Cellvigor Biorevive LTD');

INSERT INTO users (id, member_code, username, email, phone, password_hash, first_name, last_name, role, package_id, sponsor_id, parent_id, binary_side, status)
VALUES
(1, 'ZGV00001', 'rootmember', 'root@example.test', '08000000001', '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUV', 'Root', 'Member', 'member', 1, NULL, NULL, NULL, 'active'),
(2, 'ZGV00002', 'adminuser', 'admin@example.test', '08000000002', '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUV', 'Admin', 'User', 'admin', NULL, NULL, NULL, NULL, 'active'),
(3, 'ZGV00003', 'storeuser', 'store@example.test', '08000000003', '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUV', 'Store', 'User', 'store', NULL, NULL, NULL, NULL, 'active');

INSERT INTO wallets (user_id, available_balance) VALUES (1, 0), (2, 0), (3, 0);

INSERT INTO stores (id, name) VALUES (1, 'Test Store');
INSERT INTO products (id, store_id, name, price) VALUES (1, 1, 'Test Product', 10000.00);
