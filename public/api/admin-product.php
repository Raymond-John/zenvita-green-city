<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

require_user('admin');
ensure_product_landing_columns();
$body = request_body();
$action = strtolower(trim((string) ($body['action'] ?? 'save')));
$pdo = db();

if ($action === 'delete') {
    $id = (int) ($body['id'] ?? 0);
    if ($id < 1) {
        json_out(['ok' => false, 'error' => 'Choose a product to delete.'], 422);
    }
    $imgStmt = $pdo->prepare('SELECT image_path FROM products WHERE id = ?');
    $imgStmt->execute([$id]);
    $oldImage = $imgStmt->fetchColumn();

    $used = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE product_id = ?');
    $used->execute([$id]);
    if ((int) $used->fetchColumn() > 0) {
        $pdo->prepare("UPDATE products SET status = 'inactive', show_on_landing = 0 WHERE id = ?")->execute([$id]);
        json_out(['ok' => true, 'deactivated' => true]);
    }
    $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    if ($oldImage) {
        delete_product_image_file((string) $oldImage);
    }
    json_out(['ok' => true, 'deleted' => true]);
}

$id = (int) ($body['id'] ?? 0);
$name = trim((string) ($body['name'] ?? ''));
$description = trim((string) ($body['description'] ?? ''));
$price = round((float) ($body['price'] ?? 0), 2);
$unitLabel = trim((string) ($body['unitLabel'] ?? 'unit'));
$status = strtolower(trim((string) ($body['status'] ?? 'active')));
$sortOrder = (int) ($body['sortOrder'] ?? 0);
$showRaw = $body['showOnLanding'] ?? '0';
$showOnLanding = in_array($showRaw, [1, '1', true, 'true', 'on', 'yes'], true) ? 1 : 0;
$features = parse_feature_list($body['features'] ?? []);
$code = trim((string) ($body['code'] ?? ''));
$removeImage = in_array($body['removeImage'] ?? '0', [1, '1', true, 'true', 'on', 'yes'], true);

if ($name === '' || mb_strlen($name) > 120) {
    json_out(['ok' => false, 'error' => 'Enter a product name (max 120 characters).'], 422);
}
if ($price < 0) {
    json_out(['ok' => false, 'error' => 'Price cannot be negative.'], 422);
}
if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}
if ($unitLabel === '' || mb_strlen($unitLabel) > 40) {
    $unitLabel = 'unit';
}
if (mb_strlen($description) > 4000) {
    json_out(['ok' => false, 'error' => 'Description is too long.'], 422);
}

$featuresJson = json_encode(array_values($features), JSON_UNESCAPED_UNICODE);
$currentImage = '';
if ($id > 0) {
    $cur = $pdo->prepare('SELECT image_path FROM products WHERE id = ?');
    $cur->execute([$id]);
    $found = $cur->fetch();
    if (!$found) {
        json_out(['ok' => false, 'error' => 'Product not found.'], 404);
    }
    $currentImage = (string) ($found['image_path'] ?? '');
}

$imagePath = $currentImage;
try {
    if ($removeImage && empty($_FILES['image']['name'])) {
        delete_product_image_file($currentImage);
        $imagePath = '';
    } elseif (!empty($_FILES['image']) && is_array($_FILES['image'])) {
        $imagePath = store_product_image_upload($_FILES['image'], $currentImage ?: null);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 422);
}

if ($id > 0) {
    $stmt = $pdo->prepare(
        'UPDATE products
         SET name = ?, description = ?, image_path = ?, features = ?, price = ?, unit_label = ?, status = ?, sort_order = ?, show_on_landing = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $name,
        $description,
        $imagePath !== '' ? $imagePath : null,
        $featuresJson,
        $price,
        $unitLabel,
        $status,
        $sortOrder,
        $showOnLanding,
        $id,
    ]);
    json_out([
        'ok' => true,
        'id' => $id,
        'created' => false,
        'imageUrl' => product_image_public_url($imagePath),
    ]);
}

if ($code === '') {
    $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name) ?? '');
    $code = trim($code, '_');
}
if ($code === '') {
    $code = 'product_' . substr(bin2hex(random_bytes(3)), 0, 6);
}
$code = mb_substr($code, 0, 40);

$base = $code;
$n = 1;
$exists = $pdo->prepare('SELECT id FROM products WHERE code = ? LIMIT 1');
while (true) {
    $exists->execute([$code]);
    if (!$exists->fetch()) {
        break;
    }
    $n++;
    $code = mb_substr($base, 0, 36) . '_' . $n;
}

$stmt = $pdo->prepare(
    'INSERT INTO products (code, name, description, image_path, features, price, unit_label, status, sort_order, show_on_landing)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
try {
    $stmt->execute([
        $code,
        $name,
        $description,
        $imagePath !== '' ? $imagePath : null,
        $featuresJson,
        $price,
        $unitLabel,
        $status,
        $sortOrder,
        $showOnLanding,
    ]);
} catch (PDOException $e) {
    if ($imagePath !== '' && $imagePath !== $currentImage) {
        delete_product_image_file($imagePath);
    }
    json_out(['ok' => false, 'error' => 'Could not create that product. Try a different name.'], 422);
}

$newId = (int) $pdo->lastInsertId();
json_out([
    'ok' => true,
    'id' => $newId,
    'created' => true,
    'imageUrl' => product_image_public_url($imagePath),
]);
