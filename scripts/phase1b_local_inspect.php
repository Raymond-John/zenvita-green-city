<?php
/**
 * Local development inspection only. Does not touch production.
 */
require dirname(__DIR__) . '/public/api/bootstrap.php';

$pdo = db();
echo 'PHP ' . PHP_VERSION . PHP_EOL;
echo 'DB ' . ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '(none)') . PHP_EOL;

$cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'binary_side'")->fetch(PDO::FETCH_ASSOC);
echo 'binary_side_type=' . ($cols['Type'] ?? '?') . PHP_EOL;
echo 'binary_side_null=' . ($cols['Null'] ?? '?') . PHP_EOL;

$dup = $pdo->query(
    "SELECT parent_id, binary_side, COUNT(*) AS c
     FROM users
     WHERE parent_id IS NOT NULL
       AND binary_side IN ('left', 'right')
     GROUP BY parent_id, binary_side
     HAVING c > 1"
)->fetchAll(PDO::FETCH_ASSOC);
echo 'dup_groups=' . count($dup) . PHP_EOL;
foreach ($dup as $row) {
    echo 'DUP parent=' . $row['parent_id'] . ' side=' . $row['binary_side'] . ' count=' . $row['c'] . PHP_EOL;
}

$idx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_parent_side'")->fetchAll(PDO::FETCH_ASSOC);
echo 'uq_users_parent_side=' . (count($idx) > 0 ? 'yes' : 'no') . PHP_EOL;

$nullParents = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE parent_id IS NULL')->fetchColumn();
echo 'null_parent_rows=' . $nullParents . PHP_EOL;

$sides = $pdo->query('SELECT binary_side, COUNT(*) AS c FROM users GROUP BY binary_side')->fetchAll(PDO::FETCH_ASSOC);
foreach ($sides as $row) {
    echo 'side[' . var_export($row['binary_side'], true) . ']=' . $row['c'] . PHP_EOL;
}

$parentNull = $pdo->query('SHOW COLUMNS FROM users LIKE \'parent_id\'')->fetch(PDO::FETCH_ASSOC);
echo 'parent_id_type=' . ($parentNull['Type'] ?? '?') . ' null=' . ($parentNull['Null'] ?? '?') . PHP_EOL;

$legacyDir = BASE_PATH . '/public/assets/uploads/receipts';
$privateDir = BASE_PATH . '/storage/receipts';
$legacyFiles = [];
if (is_dir($legacyDir)) {
    foreach (scandir($legacyDir) ?: [] as $f) {
        if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === '.gitkeep') {
            continue;
        }
        if (is_file($legacyDir . DIRECTORY_SEPARATOR . $f)) {
            $legacyFiles[] = $f;
        }
    }
}
echo 'legacy_receipt_files=' . count($legacyFiles) . PHP_EOL;

$privateFiles = [];
if (is_dir($privateDir)) {
    foreach (scandir($privateDir) ?: [] as $f) {
        if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === '.gitkeep') {
            continue;
        }
        if (is_file($privateDir . DIRECTORY_SEPARATOR . $f)) {
            $privateFiles[] = $f;
        }
    }
}
echo 'private_receipt_files=' . count($privateFiles) . PHP_EOL;

$proofs = $pdo->query(
    "SELECT id, reference, proof_path, status
     FROM registration_payments
     WHERE proof_path IS NOT NULL AND proof_path <> ''"
)->fetchAll(PDO::FETCH_ASSOC);
echo 'db_proof_rows=' . count($proofs) . PHP_EOL;
foreach ($proofs as $p) {
    $resolved = resolve_registration_receipt_path((string) $p['proof_path']);
    echo $p['id'] . '|' . $p['reference'] . '|' . $p['proof_path'] . '|' . $p['status']
        . '|resolved=' . ($resolved ? 'yes' : 'no') . PHP_EOL;
}

$htLegacy = is_file($legacyDir . '/.htaccess');
$htPrivate = is_file($privateDir . '/.htaccess');
echo 'legacy_htaccess=' . ($htLegacy ? 'yes' : 'no') . PHP_EOL;
echo 'private_htaccess=' . ($htPrivate ? 'yes' : 'no') . PHP_EOL;
