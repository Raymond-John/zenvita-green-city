<?php
/**
 * Phase 1J acceptance-gap tests (isolated DB only).
 * Database: zenvita_phase1j_test (never primary zenvita).
 *
 * Usage: C:\xampp\php\php.exe tests/phase1j_runner.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$phpBin = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
require_once $root . '/database/migrations/lib/schema_migrate.php';
require_once $root . '/bootstrap/app.php';

$results = [];
$failed = 0;

function t_assert(string $name, bool $ok, string $detail = ''): void
{
    global $results, $failed;
    $status = $ok ? 'Passed' : 'Failed';
    if (!$ok) {
        $failed++;
    }
    $results[] = compact('name', 'status', 'detail');
    echo str_pad($status, 14) . $name . ($detail !== '' ? " — $detail" : '') . PHP_EOL;
}

function t_proc_env(array $assoc): array
{
    if (PHP_OS_FAMILY !== 'Windows') {
        return $assoc;
    }
    $out = [];
    foreach ($assoc as $k => $v) {
        if ($v === null || $v === false) {
            continue;
        }
        $out[] = $k . '=' . (string) $v;
    }
    return $out;
}

/** @return array{code:int,out:string,err:string} */
function t_run(array $cmd, array $envAssoc, string $cwd): array
{
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, t_proc_env($envAssoc));
    if (!is_resource($proc)) {
        return ['code' => -1, 'out' => '', 'err' => 'proc_open failed'];
    }
    $out = stream_get_contents($pipes[1]) ?: '';
    $err = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($proc), 'out' => $out, 'err' => $err];
}

function t_reset(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (['order_notifications', 'orders', 'registration_payments', 'users', 'payment_idempotency', 'settings'] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec("CREATE TABLE users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id INT UNSIGNED NULL,
        binary_side VARCHAR(8) NULL
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE registration_payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        reference VARCHAR(128) NOT NULL,
        email VARCHAR(120) NOT NULL DEFAULT 't@example.test',
        amount DECIMAL(12,2) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE orders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payment_reference VARCHAR(128) NULL,
        paystack_reference VARCHAR(128) NULL
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE order_notifications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO registration_payments (reference) VALUES ('ONLY-ONE')");
}

echo "=== Phase 1J tests ===\n";

$testDb = 'zenvita_phase1j_test';
global $config;
$admin = new PDO(
    'mysql:host=' . $config['host'] . ';charset=utf8mb4',
    $config['user'],
    $config['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$admin->exec("CREATE DATABASE IF NOT EXISTS `{$testDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
putenv('ZENVITA_DB_NAME=' . $testDb);
$_ENV['ZENVITA_DB_NAME'] = $testDb;
$pdo = new PDO(
    'mysql:host=' . $config['host'] . ';dbname=' . $testDb . ';charset=utf8mb4',
    $config['user'],
    $config['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
t_assert('Isolated zenvita_phase1j_test connected', (string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $testDb);

$hostCfg = (string) ($config['host'] ?? '');
$migrator = $root . '/database/migrations/2026_phase1d_schema_readiness.php';
$baseEnv = array_merge($_ENV, [
    'ZENVITA_DB_NAME' => $testDb,
    'ZENVITA_MIGRATE_EXPECT_DB' => $testDb,
    'ZENVITA_MIGRATE_EXPECT_HOST' => $hostCfg,
    'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
    'PATH' => getenv('PATH') ?: '',
]);

// --- Settings schema ---
t_reset($pdo);
$pdo->exec('DROP TABLE IF EXISTS settings');
$sql = migrate_settings_create_sql();
t_assert('Settings CREATE SQL uses setting_key', str_contains($sql, 'setting_key') && str_contains($sql, 'setting_value') && !str_contains($sql, '`key`'));
$log = [];
migrate_exec_create_table($pdo, 'settings', $sql, $log);
$st = migrate_settings_schema_state($pdo);
t_assert('Missing-table creation yields compatible settings', $st['status'] === 'compatible', $st['detail']);

$pdo->exec('DROP TABLE IF EXISTS settings');
$pdo->exec('CREATE TABLE settings (`key` VARCHAR(100) PRIMARY KEY, `value` TEXT NOT NULL) ENGINE=InnoDB');
$st = migrate_settings_schema_state($pdo);
t_assert('Incompatible key/value settings blocked', $st['status'] === 'incompatible');
$stage = migrate_collect_uniqueness_blockers($pdo);
t_assert('Incompatible settings blocks Stage A ok', $stage['ok'] === false);

// --- Index definition verification ---
t_reset($pdo);
$pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_payment_reference (payment_reference)');
$match = migrate_unique_index_state($pdo, 'orders', 'uq_orders_payment_reference', ['payment_reference']);
t_assert('Correct same-named unique index matches', $match['status'] === 'match');

$pdo->exec('ALTER TABLE orders DROP INDEX uq_orders_payment_reference');
$pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_payment_reference (paystack_reference)');
$wrongCol = migrate_unique_index_state($pdo, 'orders', 'uq_orders_payment_reference', ['payment_reference']);
t_assert('Same name wrong column is mismatch', $wrongCol['status'] === 'mismatch');

$pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_parent_side (binary_side, parent_id)');
$wrongOrder = migrate_unique_index_state($pdo, 'users', 'uq_users_parent_side', ['parent_id', 'binary_side']);
t_assert('Same name wrong column order is mismatch', $wrongOrder['status'] === 'mismatch');

$pdo->exec('ALTER TABLE users DROP INDEX uq_users_parent_side');
$pdo->exec('ALTER TABLE users ADD KEY uq_users_parent_side (parent_id, binary_side)');
$nonUniq = migrate_unique_index_state($pdo, 'users', 'uq_users_parent_side', ['parent_id', 'binary_side']);
t_assert('Same name non-unique is non_unique', $nonUniq['status'] === 'non_unique');

$absent = migrate_unique_index_state($pdo, 'orders', 'uq_orders_paystack_reference', ['paystack_reference']);
t_assert('Missing index is absent', $absent['status'] === 'absent');

$stage = migrate_collect_uniqueness_blockers($pdo);
t_assert('Wrong-definition index blocks Stage A', $stage['ok'] === false);

// --- Whitespace / blank refs ---
t_reset($pdo);
$pdo->exec("INSERT INTO orders (payment_reference) VALUES ('   ')");
$stage = migrate_collect_uniqueness_blockers($pdo);
$ws = false;
foreach ($stage['blockers'] as $b) {
    if ($b['constraint'] === 'uq_orders_payment_reference' && ($b['empty_string_rows'] ?? 0) > 0) {
        $ws = true;
    }
}
t_assert('Whitespace-only payment_reference blocks', !$stage['ok'] && $ws);

t_reset($pdo);
$pdo->exec("INSERT INTO orders (payment_reference) VALUES ('')");
$stage = migrate_collect_uniqueness_blockers($pdo);
t_assert('Empty-string payment_reference blocks', $stage['ok'] === false);

t_reset($pdo);
$pdo->exec("INSERT INTO orders (payment_reference) VALUES ('\t')");
$stage = migrate_collect_uniqueness_blockers($pdo);
t_assert('Tab-only payment_reference blocks', $stage['ok'] === false);

t_reset($pdo);
$pdo->exec("INSERT INTO orders (payment_reference) VALUES ('PAY-OK')");
$stage = migrate_collect_uniqueness_blockers($pdo);
t_assert('Valid non-empty payment_reference does not blank-block', $stage['ok'] === true);

// --- Migrator failure paths ---
t_reset($pdo);
$env = array_merge($baseEnv, ['ZENVITA_MIGRATE_TEST_HOOK' => 'stage_a_throw']);
$run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $env, $root);
$combined = $run['out'] . $run['err'];
t_assert(
    'Stage A metadata/query failure exits 2 no DDL',
    $run['code'] === 2 && str_contains($combined, 'Stage A CHECK FAILED') && !str_contains($combined, 'ADDED ') && !str_contains($combined, 'CREATED '),
    'exit=' . $run['code']
);

t_reset($pdo);
$env = array_merge($baseEnv, ['ZENVITA_MIGRATE_TEST_HOOK' => 'first_ddl_throw']);
$run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $env, $root);
$combined = $run['out'] . $run['err'];
t_assert(
    'First DDL failure reports Failed and PENDING, no committed DDL claim',
    $run['code'] === 3
    && str_contains($combined, 'Failed step: create_payment_idempotency')
    && str_contains($combined, 'PENDING:')
    && str_contains($combined, 'No ADDED/CREATED'),
    'exit=' . $run['code']
);
t_assert(
    'First DDL failure left payment_idempotency absent',
    !migrate_table_exists($pdo, 'payment_idempotency')
);

t_reset($pdo);
$env = array_merge($baseEnv, ['ZENVITA_MIGRATE_TEST_HOOK' => 'later_ddl_throw']);
$run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $env, $root);
$combined = $run['out'] . $run['err'];
t_assert(
    'Later DDL failure reports DONE create_payment_idempotency',
    $run['code'] === 3
    && str_contains($combined, 'DONE: create_payment_idempotency')
    && str_contains($combined, 'Failed step:')
    && str_contains($combined, 'PENDING:')
    && (str_contains($combined, 'implicitly committed') || str_contains($combined, 'ADDED/CREATED')),
    'exit=' . $run['code']
);
t_assert(
    'Later DDL failure left payment_idempotency present',
    migrate_table_exists($pdo, 'payment_idempotency')
);

// Blocked helper: wrong index definition during apply Stage A
t_reset($pdo);
$pdo->exec('ALTER TABLE orders ADD KEY uq_orders_payment_reference (payment_reference)');
$run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $baseEnv, $root);
t_assert(
    'Blocked non-unique same-named index fails closed before DDL',
    $run['code'] === 2 && !migrate_table_exists($pdo, 'payment_idempotency'),
    'exit=' . $run['code']
);

// Pre-DDL identity failure
t_reset($pdo);
$envBad = array_merge($baseEnv, ['ZENVITA_MIGRATE_EXPECT_DB' => 'wrong_db_name_for_test']);
unset($envBad['ZENVITA_MIGRATE_TEST_HOOK']);
$run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $envBad, $root);
t_assert(
    'Identity mismatch refuses with zero DDL',
    $run['code'] === 1 && !migrate_table_exists($pdo, 'payment_idempotency') && str_contains($run['out'] . $run['err'], 'REFUSED'),
    'exit=' . $run['code']
);

// Settings mismatch via migrator
t_reset($pdo);
$pdo->exec('DROP TABLE IF EXISTS settings');
$pdo->exec('CREATE TABLE settings (`key` VARCHAR(50) PRIMARY KEY, `value` TEXT NOT NULL) ENGINE=InnoDB');
$run = t_run([$phpBin, $migrator, '--dry-run'], $baseEnv, $root);
t_assert(
    'Settings schema mismatch blocks dry-run Stage A',
    $run['code'] === 2 && str_contains($run['out'] . $run['err'], 'settings'),
    'exit=' . $run['code']
);

// Existing wrong unique definition blocks
t_reset($pdo);
$pdo->exec('ALTER TABLE registration_payments ADD UNIQUE KEY uq_reg_pay_reference (email)');
$run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $baseEnv, $root);
t_assert(
    'Same-named incorrectly defined unique index blocks apply',
    $run['code'] === 2 && !migrate_table_exists($pdo, 'payment_idempotency'),
    'exit=' . $run['code']
);

// Dry-run clear path mentions setting_key
t_reset($pdo);
$run = t_run([$phpBin, $migrator, '--dry-run'], $baseEnv, $root);
t_assert(
    'Dry-run clear mentions app-compatible settings columns',
    $run['code'] === 0 && str_contains($run['out'], 'setting_key') && str_contains($run['out'], 'setting_value'),
    'exit=' . $run['code']
);

$reportDir = $root . '/storage/reports';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0775, true);
}
$path = $reportDir . '/phase1j_test_results_' . date('Ymd_His') . '.json';
file_put_contents($path, json_encode([
    'phase' => '1J',
    'database' => $testDb,
    'failed' => $failed,
    'results' => $results,
    'hostinger_accessed' => false,
    'primary_zenvita_used' => false,
], JSON_PRETTY_PRINT));

echo "\nFailed: {$failed}\nReport: {$path}\n";
exit($failed > 0 ? 1 : 0);
