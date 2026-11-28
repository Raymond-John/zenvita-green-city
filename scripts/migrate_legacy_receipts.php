<?php
/**
 * Legacy receipt migration utility (local / staging only).
 *
 * Usage:
 *   php scripts/migrate_legacy_receipts.php --dry-run
 *   php scripts/migrate_legacy_receipts.php --apply
 *
 * Does NOT delete originals. Safe to re-run (skips identical copies).
 * Do NOT run against production/Hostinger unless ops explicitly approves.
 */
require dirname(__DIR__) . '/public/api/bootstrap.php';

$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$dryRun = !$apply || in_array('--dry-run', $args, true);
if ($apply) {
    $dryRun = false;
}

$legacyDir = receipt_uploads_dir();
$privateDir = private_receipt_uploads_dir();
$reportDir = BASE_PATH . '/storage/reports';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0750, true);
}

$report = [
    'generated_at' => gmdate('c'),
    'mode' => $dryRun ? 'dry-run' : 'apply',
    'legacy_dir' => 'public/assets/uploads/receipts',
    'private_dir' => 'storage/receipts',
    'files_on_disk' => [],
    'db_rows' => [],
    'copied' => [],
    'skipped' => [],
    'updated_db' => [],
    'errors' => [],
    'notes' => [
        'Original legacy files are never deleted by this script.',
        'DB proof_path is updated only after copy + size/hash verification.',
        'Hostinger/production were not targeted by this utility invocation.',
    ],
];

foreach (scandir($legacyDir) ?: [] as $f) {
    if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === '.gitkeep') {
        continue;
    }
    $abs = $legacyDir . DIRECTORY_SEPARATOR . $f;
    if (!is_file($abs)) {
        continue;
    }
    $report['files_on_disk'][] = [
        'name' => $f,
        'bytes' => filesize($abs),
        'sha256' => hash_file('sha256', $abs),
    ];
}

$pdo = db();
ensure_registration_payments_table($pdo);
$rows = $pdo->query(
    "SELECT id, reference, proof_path, status
     FROM registration_payments
     WHERE proof_path IS NOT NULL AND TRIM(proof_path) <> ''"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $proof = str_replace('\\', '/', trim((string) $row['proof_path']));
    $entry = [
        'id' => (int) $row['id'],
        'reference' => (string) $row['reference'],
        'proof_path' => $proof,
        'status' => (string) $row['status'],
        'resolved' => resolve_registration_receipt_path($proof) !== null,
        'already_private' => str_starts_with($proof, 'storage/receipts/'),
        'legacy_style' => str_starts_with($proof, 'uploads/receipts/')
            || str_starts_with($proof, 'receipts/')
            || !str_contains($proof, '/'),
    ];
    $report['db_rows'][] = $entry;

    if ($entry['already_private']) {
        $report['skipped'][] = ['id' => $entry['id'], 'reason' => 'already_private'];
        continue;
    }

    $src = resolve_registration_receipt_path($proof);
    if ($src === null) {
        $report['errors'][] = ['id' => $entry['id'], 'error' => 'source_missing_or_unsafe'];
        continue;
    }

    $base = basename($src);
    if ($base === '' || $base === '.' || $base === '..') {
        $report['errors'][] = ['id' => $entry['id'], 'error' => 'bad_basename'];
        continue;
    }

    $dest = $privateDir . DIRECTORY_SEPARATOR . $base;
    $newPath = 'storage/receipts/' . $base;
    $srcHash = hash_file('sha256', $src);
    $srcSize = filesize($src);

    if (is_file($dest)) {
        $destHash = hash_file('sha256', $dest);
        if (!hash_equals((string) $srcHash, (string) $destHash)) {
            $report['errors'][] = [
                'id' => $entry['id'],
                'error' => 'dest_exists_with_different_hash',
                'dest' => $newPath,
            ];
            continue;
        }
        $report['skipped'][] = ['id' => $entry['id'], 'reason' => 'identical_copy_exists'];
    } elseif ($dryRun) {
        $report['copied'][] = [
            'id' => $entry['id'],
            'would_copy' => $base,
            'bytes' => $srcSize,
        ];
    } else {
        if (!copy($src, $dest)) {
            $report['errors'][] = ['id' => $entry['id'], 'error' => 'copy_failed'];
            continue;
        }
        $destHash = hash_file('sha256', $dest);
        if (!hash_equals((string) $srcHash, (string) $destHash) || filesize($dest) !== $srcSize) {
            @unlink($dest);
            $report['errors'][] = ['id' => $entry['id'], 'error' => 'verify_failed'];
            continue;
        }
        $report['copied'][] = [
            'id' => $entry['id'],
            'copied' => $base,
            'bytes' => $srcSize,
            'sha256' => $destHash,
        ];
    }

    if ($dryRun) {
        $report['updated_db'][] = [
            'id' => $entry['id'],
            'would_set' => $newPath,
        ];
        continue;
    }

    // Only update DB after verified private copy exists.
    if (!is_file($dest) || !hash_equals((string) $srcHash, (string) hash_file('sha256', $dest))) {
        $report['errors'][] = ['id' => $entry['id'], 'error' => 'refusing_db_update_without_verified_copy'];
        continue;
    }

    $upd = $pdo->prepare(
        'UPDATE registration_payments
         SET proof_path = ?, updated_at = NOW()
         WHERE id = ? AND proof_path = ?'
    );
    $upd->execute([$newPath, (int) $row['id'], $row['proof_path']]);
    if ($upd->rowCount() === 1) {
        $report['updated_db'][] = [
            'id' => $entry['id'],
            'from' => $proof,
            'to' => $newPath,
        ];
    } else {
        $report['skipped'][] = ['id' => $entry['id'], 'reason' => 'db_row_changed_concurrently'];
    }
}

// Also inventory orphan legacy files with no DB row.
$dbBasenames = [];
foreach ($report['db_rows'] as $r) {
    $dbBasenames[basename((string) $r['proof_path'])] = true;
}
$report['orphan_legacy_files'] = [];
foreach ($report['files_on_disk'] as $f) {
    if (empty($dbBasenames[$f['name']])) {
        $report['orphan_legacy_files'][] = $f['name'];
    }
}

$out = $reportDir . '/phase1b_receipt_migration_' . gmdate('Ymd_His') . '.json';
file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo ($dryRun ? "DRY-RUN" : "APPLY") . " report written: storage/reports/" . basename($out) . PHP_EOL;
echo 'legacy_files=' . count($report['files_on_disk'])
    . ' db_rows=' . count($report['db_rows'])
    . ' errors=' . count($report['errors'])
    . PHP_EOL;
