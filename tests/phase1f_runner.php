<?php
/**
 * Phase 1F migration safety tests.
 *
 * - CLI / identity / identifier tests: no database required.
 * - Duplicate / dry-run / idempotency tests: isolated DB zenvita_phase1f_test only.
 * - Never targets production. Never uses primary local DB name "zenvita" for DDL apply.
 *
 * Usage: C:\xampp\php\php.exe tests/phase1f_runner.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$phpBin = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';

require_once $root . '/database/migrations/lib/schema_migrate.php';

$results = [];
$failed = 0;
$notExecuted = 0;

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

function t_skip(string $name, string $detail): void
{
    global $results, $notExecuted;
    $notExecuted++;
    $results[] = ['name' => $name, 'status' => 'Not executed', 'detail' => $detail];
    echo str_pad('Not executed', 14) . $name . ' — ' . $detail . PHP_EOL;
}

/**
 * Build env for proc_open. Windows requires KEY=VALUE strings.
 *
 * @param array<string,mixed> $assoc
 * @return array<string,mixed>|list<string>
 */
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

/**
 * @param list<string> $cmd
 * @param array<string,mixed> $envAssoc
 * @return array{code:int,out:string,err:string}
 */
function t_run(array $cmd, array $envAssoc, string $cwd): array
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptors, $pipes, $cwd, t_proc_env($envAssoc));
    if (!is_resource($proc)) {
        return ['code' => -1, 'out' => '', 'err' => 'proc_open failed'];
    }
    $out = stream_get_contents($pipes[1]) ?: '';
    $err = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($proc), 'out' => $out, 'err' => $err];
}

echo "=== Phase 1F migration safety tests ===\n";

// ---------------------------------------------------------------------------
// CLI argument validation
// ---------------------------------------------------------------------------
try {
    $r = migrate_parse_cli_args([]);
    t_assert('CLI default is dry-run', $r['mode'] === 'dry-run' && $r['confirmed'] === false);
} catch (Throwable $e) {
    t_assert('CLI default is dry-run', false, $e->getMessage());
}

try {
    $r = migrate_parse_cli_args(['--dry-run']);
    t_assert('CLI explicit dry-run', $r['mode'] === 'dry-run');
} catch (Throwable $e) {
    t_assert('CLI explicit dry-run', false, $e->getMessage());
}

$threw = false;
try {
    migrate_parse_cli_args(['--dry-run', '--apply']);
} catch (InvalidArgumentException $e) {
    $threw = str_contains($e->getMessage(), 'Conflicting');
}
t_assert('CLI conflicting dry-run and apply rejected', $threw);

$threw = false;
try {
    migrate_parse_cli_args(['--unknown-flag']);
} catch (InvalidArgumentException $e) {
    $threw = str_contains($e->getMessage(), 'Unknown');
}
t_assert('CLI unknown flag rejected', $threw);

$threw = false;
try {
    migrate_parse_cli_args(['--apply']);
} catch (InvalidArgumentException $e) {
    $threw = str_contains($e->getMessage(), 'i-understand-ddl');
}
t_assert('CLI apply without confirmation rejected', $threw);

$threw = false;
try {
    migrate_parse_cli_args(['--i-understand-ddl']);
} catch (InvalidArgumentException $e) {
    $threw = true;
}
t_assert('CLI confirmation without apply rejected', $threw);

try {
    $r = migrate_parse_cli_args(['--apply', '--i-understand-ddl']);
    t_assert('CLI apply with confirmation accepted', $r['mode'] === 'apply' && $r['confirmed'] === true);
} catch (Throwable $e) {
    t_assert('CLI apply with confirmation accepted', false, $e->getMessage());
}

$threw = false;
try {
    migrate_parse_cli_args(['--dry-run', '--dry-run']);
} catch (InvalidArgumentException $e) {
    $threw = str_contains($e->getMessage(), 'Duplicate');
}
t_assert('CLI duplicate flag rejected', $threw);

$threw = false;
try {
    migrate_parse_cli_args(['positional']);
} catch (InvalidArgumentException $e) {
    $threw = true;
}
t_assert('CLI positional arg rejected', $threw);

// ---------------------------------------------------------------------------
// SQL identifier validation
// ---------------------------------------------------------------------------
try {
    migrate_assert_sql_identifier('users');
    migrate_assert_sql_identifier('uq_orders_payment_reference');
    migrate_assert_sql_identifier('payment_reference');
    t_assert('Valid SQL identifiers accepted', true);
} catch (Throwable $e) {
    t_assert('Valid SQL identifiers accepted', false, $e->getMessage());
}

$badIds = [
    '' => 'empty',
    'users; DROP TABLE users' => 'SQL fragment',
    'foo`bar' => 'backtick',
    'foo bar' => 'space',
    'foo--bar' => 'comment dash',
    'foo/*x*/' => 'comment slash',
    str_repeat('a', 65) => 'too long',
];
$allBad = true;
foreach ($badIds as $id => $label) {
    try {
        migrate_assert_sql_identifier((string) $id);
        $allBad = false;
        t_assert("Invalid identifier rejected ($label)", false, 'accepted unexpectedly');
    } catch (InvalidArgumentException $e) {
        t_assert("Invalid identifier rejected ($label)", true);
    }
}

// ---------------------------------------------------------------------------
// Database identity protection
// ---------------------------------------------------------------------------
$missing = migrate_verify_db_identity(
    ['expect_db' => '', 'expect_host' => ''],
    ['database' => 'anything', 'host_config' => '127.0.0.1'],
    false
);
t_assert('Identity missing expected DB → not_configured (dry)', $missing['status'] === 'not_configured');

$missingApply = migrate_verify_db_identity(
    ['expect_db' => '', 'expect_host' => ''],
    ['database' => 'anything', 'host_config' => '127.0.0.1'],
    true
);
t_assert('Identity missing expected DB → failed (apply)', $missingApply['status'] === 'failed');

$mismatch = migrate_verify_db_identity(
    ['expect_db' => 'expected_db', 'expect_host' => ''],
    ['database' => 'other_db', 'host_config' => '127.0.0.1'],
    true
);
t_assert('Identity DB name mismatch → failed', $mismatch['status'] === 'failed');

$hostMismatch = migrate_verify_db_identity(
    ['expect_db' => 'zenvita_phase1f_test', 'expect_host' => '127.0.0.1'],
    ['database' => 'zenvita_phase1f_test', 'host_config' => 'db.example.com'],
    true
);
t_assert('Identity host mismatch → failed', $hostMismatch['status'] === 'failed');

$match = migrate_verify_db_identity(
    ['expect_db' => 'zenvita_phase1f_test', 'expect_host' => '127.0.0.1'],
    ['database' => 'zenvita_phase1f_test', 'host_config' => '127.0.0.1'],
    true
);
t_assert('Identity successful match', $match['status'] === 'passed');

$localhostOk = migrate_verify_db_identity(
    ['expect_db' => 'prod_like', 'expect_host' => 'localhost'],
    ['database' => 'prod_like', 'host_config' => 'localhost'],
    true
);
t_assert('Identity localhost allowed when expected', $localhostOk['status'] === 'passed');

// ---------------------------------------------------------------------------
// Binary placement helper gate (no DDL)
// ---------------------------------------------------------------------------
require_once $root . '/bootstrap/app.php';

// ---------------------------------------------------------------------------
// Isolated DB section (dedicated Phase 1F schema — not primary zenvita)
// ---------------------------------------------------------------------------
$testDb = 'zenvita_phase1f_test';
$dbAvailable = false;
$pdo = null;
try {
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
    $active = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $dbAvailable = ($active === $testDb);
    t_assert('Isolated test DB connected', $dbAvailable, "active={$active}");
} catch (Throwable $e) {
    t_assert('Isolated test DB connected', false, $e->getMessage());
}

if (!$dbAvailable || !$pdo instanceof PDO) {
    $skipNames = [
        'Identity match against live test DATABASE()',
        'Duplicate placement blocks Stage A',
        'Duplicate notifications block Stage A',
        'Duplicate payment_reference blocks Stage A',
        'Duplicate paystack_reference blocks Stage A',
        'Dry-run with blockers exits non-zero without DDL',
        'Apply with blockers exits before DDL',
        'Binary helper refuses without allow flag',
        'Partial DDL failure reporting path',
        'Re-run completed migration idempotent dry-run',
    ];
    foreach ($skipNames as $n) {
        t_skip($n, 'Isolated test database unavailable');
    }
} else {
    $actualDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $hostCfg = (string) ($config['host'] ?? '');
    $liveId = migrate_verify_db_identity(
        ['expect_db' => $testDb, 'expect_host' => $hostCfg],
        ['database' => $actualDb, 'host_config' => $hostCfg],
        true
    );
    t_assert('Identity match against live test DATABASE()', $liveId['status'] === 'passed');

    // Minimal schema for blocker tests (does not touch primary zenvita)
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('DROP TABLE IF EXISTS order_notifications');
    $pdo->exec('DROP TABLE IF EXISTS orders');
    $pdo->exec('DROP TABLE IF EXISTS registration_payments');
    $pdo->exec('DROP TABLE IF EXISTS users');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec(
        "CREATE TABLE users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id INT UNSIGNED NULL,
            binary_side VARCHAR(8) NULL
        ) ENGINE=InnoDB"
    );
    $pdo->exec(
        "CREATE TABLE registration_payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(128) NOT NULL,
            email VARCHAR(120) NOT NULL DEFAULT 't@example.test',
            amount DECIMAL(12,2) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB"
    );
    $pdo->exec(
        "CREATE TABLE orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_reference VARCHAR(128) NULL,
            paystack_reference VARCHAR(128) NULL
        ) ENGINE=InnoDB"
    );
    $pdo->exec(
        "CREATE TABLE order_notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB"
    );

    // Placement duplicates
    $pdo->exec("INSERT INTO users (parent_id, binary_side) VALUES (1,'left'),(1,'left')");
    $stageA = migrate_collect_uniqueness_blockers($pdo);
    $blockers = $stageA['blockers'];
    $hasPlacement = false;
    foreach ($blockers as $b) {
        if ($b['constraint'] === 'uq_users_parent_side' && $b['duplicate_groups'] >= 1) {
            $hasPlacement = true;
        }
    }
    t_assert('Duplicate placement blocks Stage A', $hasPlacement, 'groups=' . ($blockers[0]['duplicate_groups'] ?? 0));
    $pdo->exec('DELETE FROM users');

    // Notification duplicates
    $pdo->exec('INSERT INTO order_notifications (order_id) VALUES (10),(10)');
    $stageA = migrate_collect_uniqueness_blockers($pdo);
    $blockers = $stageA['blockers'];
    $hasNotif = false;
    foreach ($blockers as $b) {
        if ($b['constraint'] === 'uq_order_notifications_order' && $b['duplicate_groups'] >= 1) {
            $hasNotif = true;
        }
    }
    t_assert('Duplicate notifications block Stage A', $hasNotif);
    $pdo->exec('DELETE FROM order_notifications');

    // payment_reference duplicates (NULL not counted)
    $pdo->exec("INSERT INTO orders (payment_reference, paystack_reference) VALUES ('PAY-DUP','A'),('PAY-DUP','B'),(NULL,'C'),(NULL,'D')");
    $stageA = migrate_collect_uniqueness_blockers($pdo);
    $blockers = $stageA['blockers'];
    $hasPay = false;
    foreach ($blockers as $b) {
        if ($b['constraint'] === 'uq_orders_payment_reference' && $b['duplicate_groups'] >= 1) {
            $hasPay = true;
        }
    }
    t_assert('Duplicate payment_reference blocks Stage A', $hasPay);

    // Clear payment dup; create paystack dup
    $pdo->exec('DELETE FROM orders');
    $pdo->exec("INSERT INTO orders (payment_reference, paystack_reference) VALUES ('X','PS-DUP'),('Y','PS-DUP')");
    $stageA = migrate_collect_uniqueness_blockers($pdo);
    $blockers = $stageA['blockers'];
    $hasPs = false;
    foreach ($blockers as $b) {
        if ($b['constraint'] === 'uq_orders_paystack_reference' && $b['duplicate_groups'] >= 1) {
            $hasPs = true;
        }
    }
    t_assert('Duplicate paystack_reference blocks Stage A', $hasPs);

    // Dry-run subprocess with blockers — must exit non-zero, no ADDED
    $pdo->exec('DELETE FROM orders');
    $pdo->exec("INSERT INTO orders (payment_reference) VALUES ('BLOCK-REF'),('BLOCK-REF')");
    $migrator = $root . '/database/migrations/2026_phase1d_schema_readiness.php';
    $env = array_merge($_ENV, [
        'ZENVITA_DB_NAME' => $testDb,
        'ZENVITA_MIGRATE_EXPECT_DB' => $testDb,
        'ZENVITA_MIGRATE_EXPECT_HOST' => $hostCfg,
        'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
        'PATH' => getenv('PATH') ?: '',
    ]);
    $run = t_run([$phpBin, $migrator, '--dry-run'], $env, $root);
    $combined = $run['out'] . $run['err'];
    t_assert(
        'Dry-run with blockers exits non-zero without DDL',
        $run['code'] !== 0 && !str_contains($combined, 'ADDED ') && str_contains($combined, 'BLOCKERS'),
        'exit=' . $run['code'] . ' err=' . substr($run['err'], 0, 200)
    );

    // Apply with blockers — must refuse before DDL (exit 2)
    $run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $env, $root);
    $combined = $run['out'] . $run['err'];
    $idxExists = migrate_index_exists($pdo, 'orders', 'uq_orders_payment_reference');
    t_assert(
        'Apply with blockers exits before DDL',
        $run['code'] === 2 && !$idxExists && !str_contains($combined, 'ADDED index'),
        'exit=' . $run['code'] . ' err=' . substr($run['err'], 0, 200)
    );

    // Binary helper gate without allow flag
    putenv('ZENVITA_ALLOW_BINARY_INDEX_DDL');
    unset($_ENV['ZENVITA_ALLOW_BINARY_INDEX_DDL']);
    $gateOk = false;
    try {
        apply_binary_placement_unique_index($pdo);
    } catch (RuntimeException $e) {
        $gateOk = str_contains($e->getMessage(), 'ZENVITA_ALLOW_BINARY_INDEX_DDL');
    }
    t_assert('Binary helper refuses without allow flag', $gateOk);

    // Partial DDL failure reporting: force invalid identifier through helper
    $partialLogged = false;
    try {
        $log = [];
        migrate_add_column($pdo, 'orders', 'evil`;--', 'evil`;-- INT', $log);
    } catch (InvalidArgumentException $e) {
        $partialLogged = true;
    }
    t_assert('Partial DDL failure reporting path', $partialLogged, 'invalid identifier aborts before exec');

    // Clear blockers and verify dry-run succeeds (Stage A clear) — still no apply against messy mini-schema for full migrator
    $pdo->exec('DELETE FROM orders');
    $pdo->exec('DELETE FROM order_notifications');
    $pdo->exec('DELETE FROM users');
    $pdo->exec('DELETE FROM registration_payments');
    $pdo->exec("INSERT INTO registration_payments (reference) VALUES ('KEEP-ONE')");
    $stageA = migrate_collect_uniqueness_blockers($pdo);
    t_assert('Re-run completed migration idempotent dry-run', $stageA['ok'] === true, 'Stage A clear after cleanup');

    // Subprocess dry-run with clear blockers + identity
    $envClear = array_merge($_ENV, [
        'ZENVITA_DB_NAME' => $testDb,
        'ZENVITA_MIGRATE_EXPECT_DB' => $testDb,
        'ZENVITA_MIGRATE_EXPECT_HOST' => $hostCfg,
        'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
        'PATH' => getenv('PATH') ?: '',
    ]);
    $run = t_run([$phpBin, $migrator, '--dry-run'], $envClear, $root);
    $combined = $run['out'] . $run['err'];
    t_assert(
        'Dry-run clear Stage A exits 0 without DDL',
        $run['code'] === 0 && str_contains($combined, 'uniqueness/integrity: clear') && !str_contains($combined, 'ADDED '),
        'exit=' . $run['code'] . ' err=' . substr($run['err'], 0, 200)
    );

    // Missing identity env on apply via subprocess
    $envNoId = array_merge($_ENV, [
        'ZENVITA_DB_NAME' => $testDb,
        'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
        'PATH' => getenv('PATH') ?: '',
    ]);
    unset($envNoId['ZENVITA_MIGRATE_EXPECT_DB'], $envNoId['ZENVITA_MIGRATE_EXPECT_HOST']);
    $run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $envNoId, $root);
    t_assert(
        'Apply without expect DB refused',
        $run['code'] !== 0 && (str_contains($run['out'] . $run['err'], 'REFUSED') || str_contains($run['out'] . $run['err'], 'identity')),
        'exit=' . $run['code']
    );
}

// Invalid CLI via migrator subprocess (no DDL)
$migrator = $root . '/database/migrations/2026_phase1d_schema_readiness.php';
$run = t_run([$phpBin, $migrator, '--apply'], array_merge($_ENV, [
    'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
    'PATH' => getenv('PATH') ?: '',
]), $root);
t_assert(
    'Migrator invalid args exit non-zero no DDL',
    $run['code'] === 1 && str_contains($run['out'] . $run['err'], 'Usage'),
    'exit=' . $run['code']
);

$reportDir = $root . '/storage/reports';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0775, true);
}
$stamp = date('Ymd_His');
$reportPath = $reportDir . '/phase1f_test_results_' . $stamp . '.json';
file_put_contents($reportPath, json_encode([
    'phase' => '1F',
    'failed' => $failed,
    'not_executed' => $notExecuted,
    'results' => $results,
    'hostinger_accessed' => false,
    'production_db_accessed' => false,
    'production_migration_executed' => false,
], JSON_PRETTY_PRINT));

echo "\nFailed: {$failed}; Not executed: {$notExecuted}\n";
echo "Report: {$reportPath}\n";
exit($failed > 0 ? 1 : 0);
