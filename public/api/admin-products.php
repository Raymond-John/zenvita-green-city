<?php
require __DIR__ . '/bootstrap.php';

require_user('admin');
ensure_product_landing_columns();
$pdo = db();

$productRows = $pdo->query(
    'SELECT id, code, name, description, image_path, features, price, unit_label, status, sort_order, show_on_landing, created_at
     FROM products
     ORDER BY sort_order ASC, id ASC'
)->fetchAll();

$featureRows = $pdo->query(
    'SELECT id, title, sort_order, status, created_at
     FROM landing_features
     ORDER BY sort_order ASC, id ASC'
)->fetchAll();

$products = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'name' => $row['name'],
        'description' => (string) ($row['description'] ?? ''),
        'imagePath' => (string) ($row['image_path'] ?? ''),
        'imageUrl' => product_image_public_url($row['image_path'] ?? ''),
        'features' => parse_feature_list($row['features'] ?? '[]'),
        'price' => (float) $row['price'],
        'unitLabel' => $row['unit_label'],
        'status' => $row['status'],
        'sortOrder' => (int) $row['sort_order'],
        'showOnLanding' => (int) $row['show_on_landing'] === 1,
        'createdAt' => $row['created_at'],
    ];
}, $productRows);

$features = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'sortOrder' => (int) $row['sort_order'],
        'status' => $row['status'],
        'createdAt' => $row['created_at'],
    ];
}, $featureRows);

json_out(['ok' => true, 'products' => $products, 'features' => $features]);
