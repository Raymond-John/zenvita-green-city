<?php
/**
 * Adds packages.description for existing databases.
 * Run once: php database/migrate_package_description.php
 */
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();
$col = $pdo->query("SHOW COLUMNS FROM packages LIKE 'description'")->fetch();
if ($col) {
    fwrite(STDOUT, "packages.description already exists.\n");
    exit(0);
}

ensure_package_description_column($pdo);
fwrite(STDOUT, "Added packages.description.\n");
