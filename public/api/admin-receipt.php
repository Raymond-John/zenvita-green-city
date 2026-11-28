<?php
require __DIR__ . '/bootstrap.php';

$actor = current_user();
$reference = trim((string) ($_GET['reference'] ?? ''));
$result = registration_receipt_access($actor, $reference);
if (empty($result['ok'])) {
    json_out(['ok' => false, 'error' => (string) ($result['error'] ?? 'Access denied.')], (int) ($result['code'] ?? 403));
}

header('Content-Type: ' . $result['mime']);
header('Content-Length: ' . (string) filesize($result['absolute']));
header('Content-Disposition: inline; filename="' . rawurlencode((string) $result['download_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($result['absolute']);
exit;
