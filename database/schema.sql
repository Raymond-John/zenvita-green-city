DROP DATABASE IF EXISTS zenvita;
CREATE DATABASE zenvita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zenvita;

CREATE TABLE IF NOT EXISTS packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(40) NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  price DECIMAL(12,2) NOT NULL,
  bottles INT UNSIGNED NOT NULL,
  pv INT UNSIGNED NOT NULL,
  direct_bonus DECIMAL(12,2) NOT NULL,
  daily_pairs INT UNSIGNED NOT NULL,
  pair_amount DECIMAL(12,2) NOT NULL,
  daily_cap DECIMAL(12,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
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
  CONSTRAINT fk_users_package FOREIGN KEY (package_id) REFERENCES packages(id),
  CONSTRAINT fk_users_sponsor FOREIGN KEY (sponsor_id) REFERENCES users(id),
  CONSTRAINT fk_users_parent FOREIGN KEY (parent_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS wallets (
  user_id INT UNSIGNED PRIMARY KEY,
  available_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  eagle_lands_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS commissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  source_label VARCHAR(120) NOT NULL,
  commission_type VARCHAR(40) NOT NULL,
  package_name VARCHAR(40) NOT NULL,
  pv INT UNSIGNED NOT NULL DEFAULT 0,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('Pending','Approved','Paid','Rejected') NOT NULL DEFAULT 'Pending',
  earned_on DATE NOT NULL,
  CONSTRAINT fk_commissions_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  reference VARCHAR(40) NOT NULL UNIQUE,
  tx_type VARCHAR(40) NOT NULL,
  description VARCHAR(180) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('Pending','Approved','Paid','Rejected') NOT NULL DEFAULT 'Pending',
  tx_date DATE NOT NULL,
  CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS withdrawals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  reserved_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  bank_name VARCHAR(80) NOT NULL,
  account_number VARCHAR(30) NOT NULL,
  account_name VARCHAR(120) NOT NULL,
  status ENUM('Pending','Approved','Paid','Rejected') NOT NULL DEFAULT 'Pending',
  requested_on DATE NOT NULL,
  CONSTRAINT fk_withdrawals_user FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO packages (code, name, price, bottles, pv, direct_bonus, daily_pairs, pair_amount, daily_cap) VALUES
('vision', 'Vision', 150000, 8, 150, 45000, 45, 1440, 64800),
('silver', 'Silver', 300000, 16, 300, 90000, 60, 1800, 108000),
('gold', 'Gold', 600000, 32, 600, 180000, 70, 2160, 151200),
('diamond', 'Diamond', 900000, 48, 900, 270000, 100, 2520, 252000),
('platinum', 'Platinum', 1200000, 64, 1200, 360000, 120, 2880, 345600);

INSERT INTO users (id, member_code, username, email, phone, password_hash, first_name, last_name, role, package_id, sponsor_id, parent_id, binary_side, status, personal_pv, left_pv, right_pv, city, country, bank_name, account_name, account_number) VALUES
(1, NULL, 'admin', 'admin@example.com', NULL, '$2y$10$X6T4yk.3e8Tokhuu3XGVJOV1V6AYrMXImGP5IImtOhSikEYKFkBpS', 'Admin', '', 'admin', NULL, NULL, NULL, NULL, 'active', 0, 0, 0, '', '', '', '', '');

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(40) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
);

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Zenvita Green City'),
('currency', 'NGN'),
('support_email', ''),
('direct_rate', '30'),
('pair_pv', '60'),
('min_withdrawal', '1000'),
('email_alerts', '1'),
('sms_alerts', '0');

CREATE TABLE IF NOT EXISTS stores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT UNSIGNED NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  address VARCHAR(255) NOT NULL DEFAULT '',
  city VARCHAR(80) NOT NULL DEFAULT '',
  phone VARCHAR(30) NOT NULL DEFAULT '',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_stores_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(40) NOT NULL UNIQUE,
  member_id INT UNSIGNED NOT NULL,
  store_id INT UNSIGNED NOT NULL,
  package_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
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
  CONSTRAINT fk_orders_package FOREIGN KEY (package_id) REFERENCES packages(id)
);

CREATE TABLE IF NOT EXISTS order_notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_notifications_store FOREIGN KEY (store_id) REFERENCES stores(id),
  CONSTRAINT fk_order_notifications_order FOREIGN KEY (order_id) REFERENCES orders(id)
);

