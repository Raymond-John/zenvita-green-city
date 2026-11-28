<?php
/**
 * Apply uq_users_parent_side on an approved LOCAL loopback database only.
 *
 * Usage:
 *   php scripts/phase1b_apply_local_uq.php --i-understand-local-only --database=zenvita
 *
 * Refuses:
 * - Non-loopback hosts
 * - Database names not in the local allow-list
 * - Missing confirmation flag
 *
 * Production / Hostinger: use database/migrations/2026_phase1b_uq_users_parent_side.sql
 * after a reviewed duplicate audit — never this script.
 */
require dirname(__DIR__) . '/public/api/bootstrap.php';

$args = array_slice($argv, 1);
$confirmed = in_array('--i-understand-local-only', $args, true);
$dbArg = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--database=')) {
        $dbArg = substr($arg, strlen('--database='));
    }
}

$allowedLocalDb = ['zenvita', 'zenvita_phase1b_test'];
$loopbackHosts = ['127.0.0.1', 'localhost', '::1'];

global $config;
$host = strtolower(trim((string) ($config['host'] ?? '')));
$configName = (string) ($config['name'] ?? '');

if (!$confirmed) {
    fwrite(STDERR, "Refused: pass --i-understand-local-only\n");
    exit(1);
}
if ($dbArg === null || $dbArg === '') {
    fwrite(STDERR, "Refused: pass --database=<name> (allowed: " . implode(', ', $allowedLocalDb) . ")\n");
    exit(1);
}
if (!in_array($host, $loopbackHosts, true)) {
    fwrite(STDERR, "Refused: database host must be loopback (127.0.0.1/localhost), got '{$host}'.\n");
    exit(1);
}
if (!in_array($dbArg, $allowedLocalDb, true)) {
    fwrite(STDERR, "Refused: database '{$dbArg}' is not in the local allow-list.\n");
    exit(1);
}
if ($configName !== '' && $configName !== $dbArg && getenv('ZENVITA_DB_NAME') !== $dbArg) {
    // Force the named local DB via env for this process.
    putenv('ZENVITA_DB_NAME=' . $dbArg);
    $_ENV['ZENVITA_DB_NAME'] = $dbArg;
}

$pdo = db();
$active = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($active !== $dbArg) {
    fwrite(STDERR, "Refused: connected database '{$active}' does not match requested '{$dbArg}'.\n");
    exit(1);
}

// Extra host check via connection metadata when available.
try {
    $connHost = (string) $pdo->query('SELECT @@hostname')->fetchColumn();
    // @@hostname is server machine name; still require config host was loopback above.
} catch (Throwable $e) {
    // ignore
}

echo "database=$active host_config=$host\n";

// Explicit opt-in for migration-only DDL helper (Phase 1F gate).
putenv('ZENVITA_ALLOW_BINARY_INDEX_DDL=1');
$_ENV['ZENVITA_ALLOW_BINARY_INDEX_DDL'] = '1';

$dup = (int) $pdo->query(
    "SELECT COUNT(*) FROM (
        SELECT parent_id, binary_side
        FROM users
        WHERE parent_id IS NOT NULL AND binary_side IN ('left','right')
        GROUP BY parent_id, binary_side
        HAVING COUNT(*) > 1
     ) d"
)->fetchColumn();
echo "dup_groups=$dup\n";
if ($dup > 0) {
    fwrite(STDERR, "Aborting: duplicates exist.\n");
    exit(1);
}

apply_binary_placement_unique_index($pdo);
$idx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_parent_side'")->fetchAll(PDO::FETCH_ASSOC);
echo 'uq_users_parent_side=' . (count($idx) > 0 ? 'yes' : 'no') . "\n";
