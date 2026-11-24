<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

require_user('admin');
$body = request_body();
$code = trim((string) ($body['key'] ?? ''));
$name = trim((string) ($body['name'] ?? ''));
$description = trim((string) ($body['description'] ?? ''));
$price = (float) ($body['price'] ?? 0);
$pv = (int) ($body['pv'] ?? 0);
$bottles = (int) ($body['bottles'] ?? 0);
$referral = (float) ($body['referral'] ?? 0);
$pairing = (float) ($body['pairing'] ?? 0);
$dailyCap = (float) ($body['dailyCap'] ?? 0);
$matches = (int) ($body['matches'] ?? 0);

if ($name === '' || $price <= 0 || $pv < 0 || $bottles < 0) {
    json_out(['ok' => false, 'error' => 'Enter a package name and a price above zero.'], 422);
}

if (mb_strlen($name) > 40) {
    json_out(['ok' => false, 'error' => 'Package name must be 40 characters or fewer.'], 422);
}

if (mb_strlen($description) > 2000) {
    json_out(['ok' => false, 'error' => 'Description must be 2000 characters or fewer.'], 422);
}

$bonus = round($price * ($referral / 100), 2);
if ($matches < 1) {
    $matches = $pairing > 0 ? max(1, (int) floor($dailyCap / $pairing)) : 45;
}

$pdo = db();
ensure_package_description_column($pdo);

$isCreate = ($code === '' || !empty($body['create']));

if ($isCreate) {
    $code = package_code_from_name($name);
    $code = unique_package_code($pdo, $code);

    $stmt = $pdo->prepare(
        'INSERT INTO packages (code, name, description, price, bottles, pv, direct_bonus, daily_pairs, pair_amount, daily_cap)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    try {
        $stmt->execute([$code, $name, $description, $price, $bottles, $pv, $bonus, $matches, $pairing, $dailyCap]);
    } catch (PDOException $e) {
        json_out(['ok' => false, 'error' => 'That package could not be created. Try a different name.'], 422);
    }

    json_out(['ok' => true, 'key' => $code, 'created' => true]);
}

$stmt = $pdo->prepare(
    'UPDATE packages
     SET name = ?, description = ?, price = ?, pv = ?, bottles = ?, direct_bonus = ?, pair_amount = ?, daily_cap = ?, daily_pairs = IF(? > 0, ?, daily_pairs)
     WHERE code = ?'
);
$stmt->execute([$name, $description, $price, $pv, $bottles, $bonus, $pairing, $dailyCap, $matches, $matches, $code]);

$check = $pdo->prepare('SELECT id FROM packages WHERE code = ?');
$check->execute([$code]);
if (!$check->fetch()) {
    json_out(['ok' => false, 'error' => 'That package was not found.'], 404);
}

json_out(['ok' => true, 'key' => $code, 'created' => false]);

function package_code_from_name(string $name): string
{
    $code = strtolower(trim($name));
    $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?? '';
    $code = trim($code, '_');
    if ($code === '') {
        $code = 'package';
    }
    return substr($code, 0, 20);
}

function unique_package_code(PDO $pdo, string $base): string
{
    $code = $base;
    $i = 2;
    $stmt = $pdo->prepare('SELECT id FROM packages WHERE code = ?');
    while (true) {
        $stmt->execute([$code]);
        if (!$stmt->fetch()) {
            return $code;
        }
        $suffix = '_' . $i;
        $code = substr($base, 0, max(1, 20 - strlen($suffix))) . $suffix;
        $i++;
        if ($i > 99) {
            return substr($base, 0, 12) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        }
    }
}
