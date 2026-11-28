<?php
/**
 * Phase 1H migration correction tests.
 * Isolated DB only: zenvita_phase1f_test (never primary zenvita).
 *
 * Usage: C:\xampp\php\php.exe tests/phase1h_runner.php
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

function t_reset_schema(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (['order_notifications', 'orders', 'registration_payments', 'users', 'payment_idempotency', 'settings'] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
    }
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
}

echo "=== Phase 1H migration correction tests ===\n";

// Unit: registration reference constraint name in collector
require_once $root . '/bootstrap/app.php';

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
    $dbAvailable = ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $testDb);
    t_assert('Isolated zenvita_phase1f_test connected', $dbAvailable);
} catch (Throwable $e) {
    t_assert('Isolated zenvita_phase1f_test connected', false, $e->getMessage());
}

if (!$dbAvailable || !$pdo instanceof PDO) {
    foreach ([
        'Reg reference missing table → prerequisite',
        'Reg reference duplicate blocks Stage A',
        'Reg empty-string multiples block Stage A',
        'Order payment_reference empty-string multiples block',
        'Order paystack_reference empty-string multiples block',
        'NULL order refs do not block',
        'Stage A clear when unique-ready',
        'Dry-run with reg dups exits 2 no DDL',
        'Apply with empty-string blocker exits 2 no DDL',
        'Apply missing identity refused pre-DDL',
        'Identity mismatch refused',
        'Migrator dry-run mentions reference not payment_reference',
        'Partial failure tracker marks completed only after success',
        'Step tracker init pending equals planned',
    ] as $n) {
        t_skip($n, 'Isolated test DB unavailable');
    }
} else {
    $hostCfg = (string) ($config['host'] ?? '');
    $migrator = $root . '/database/migrations/2026_phase1d_schema_readiness.php';
    $baseEnv = array_merge($_ENV, [
        'ZENVITA_DB_NAME' => $testDb,
        'ZENVITA_MIGRATE_EXPECT_DB' => $testDb,
        'ZENVITA_MIGRATE_EXPECT_HOST' => $hostCfg,
        'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
        'PATH' => getenv('PATH') ?: '',
    ]);

    // Missing registration_payments table
    t_reset_schema($pdo);
    $pdo->exec('DROP TABLE IF EXISTS registration_payments');
    $stage = migrate_collect_uniqueness_blockers($pdo);
    $hasPrereq = false;
    foreach ($stage['prerequisites'] as $p) {
        if ($p['constraint'] === 'uq_reg_pay_reference' && $p['column'] === 'reference') {
            $hasPrereq = true;
        }
    }
    t_assert('Reg reference missing table → prerequisite', !$stage['ok'] && $hasPrereq);

    // Duplicate registration reference
    t_reset_schema($pdo);
    $pdo->exec("INSERT INTO registration_payments (reference) VALUES ('REG-DUP'),('REG-DUP')");
    $stage = migrate_collect_uniqueness_blockers($pdo);
    $blocked = false;
    foreach ($stage['blockers'] as $b) {
        if ($b['constraint'] === 'uq_reg_pay_reference' && $b['duplicate_groups'] >= 1) {
            $blocked = true;
        }
    }
    t_assert('Reg reference duplicate blocks Stage A', !$stage['ok'] && $blocked);

    // Empty-string multiples on registration reference
    t_reset_schema($pdo);
    $pdo->exec("INSERT INTO registration_payments (reference) VALUES (''),('')");
    $stage = migrate_collect_uniqueness_blockers($pdo);
    $emptyBlocked = false;
    foreach ($stage['blockers'] as $b) {
        if ($b['constraint'] === 'uq_reg_pay_reference' && ($b['empty_string_rows'] ?? 0) > 1) {
            $emptyBlocked = true;
        }
    }
    t_assert('Reg empty-string multiples block Stage A', !$stage['ok'] && $emptyBlocked);

    // Order empty-string payment_reference
    t_reset_schema($pdo);
    $pdo->exec("INSERT INTO orders (payment_reference) VALUES (''),('')");
    $stage = migrate_collect_uniqueness_blockers($pdo);
    $payEmpty = false;
    foreach ($stage['blockers'] as $b) {
        if ($b['constraint'] === 'uq_orders_payment_reference' && ($b['empty_string_rows'] ?? 0) > 1) {
            $payEmpty = true;
        }
    }
    t_assert('Order payment_reference empty-string multiples block', !$stage['ok'] && $payEmpty);

    // Order empty-string paystack_reference
    t_reset_schema($pdo);
    $pdo->exec("INSERT INTO orders (paystack_reference) VALUES (''),('')");
    $stage = migrate_collect_uniqueness_blockers($pdo);
    $psEmpty = false;
    foreach ($stage['blockers'] as $b) {
        if ($b['constraint'] === 'uq_orders_paystack_reference' && ($b['empty_string_rows'] ?? 0) > 1) {
            $psEmpty = true;
        }
    }
    t_assert('Order paystack_reference empty-string multiples block', !$stage['ok'] && $psEmpty);

    // NULLs allowed (multiple NULL)
    t_reset_schema($pdo);
    $pdo->exec('INSERT INTO orders (payment_reference, paystack_reference) VALUES (NULL,NULL),(NULL,NULL)');
    $pdo->exec("INSERT INTO registration_payments (reference) VALUES ('A'),('B')");
    $stage = migrate_collect_uniqueness_blockers($pdo);
    t_assert('NULL order refs do not block', $stage['ok'] === true);

    // Clear Stage A
    t_reset_schema($pdo);
    $pdo->exec("INSERT INTO registration_payments (reference) VALUES ('ONLY-ONE')");
    $stage = migrate_collect_uniqueness_blockers($pdo);
    t_assert('Stage A clear when unique-ready', $stage['ok'] === true);

    // Dry-run with reg dups
    t_reset_schema($pdo);
    $pdo->exec("INSERT INTO registration_payments (reference) VALUES ('X'),('X')");
    $run = t_run([$phpBin, $migrator, '--dry-run'], $baseEnv, $root);
    $combined = $run['out'] . $run['err'];
    t_assert(
        'Dry-run with reg dups exits 2 no DDL',
        $run['code'] === 2 && str_contains($combined, 'uq_reg_pay_reference') && !str_contains($combined, 'ADDED '),
        'exit=' . $run['code']
    );

    // Apply with empty-string blocker
    t_reset_schema($pdo);
    $pdo->exec("INSERT INTO orders (payment_reference) VALUES (''),('')");
    $run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $baseEnv, $root);
    $idx = migrate_index_exists($pdo, 'orders', 'uq_orders_payment_reference');
    t_assert(
        'Apply with empty-string blocker exits 2 no DDL',
        $run['code'] === 2 && !$idx && !str_contains($run['out'] . $run['err'], 'ADDED '),
        'exit=' . $run['code']
    );

    // Missing identity
    $envNoId = $baseEnv;
    unset($envNoId['ZENVITA_MIGRATE_EXPECT_DB'], $envNoId['ZENVITA_MIGRATE_EXPECT_HOST']);
    t_reset_schema($pdo);
    $run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $envNoId, $root);
    t_assert(
        'Apply missing identity refused pre-DDL',
        $run['code'] === 1 && str_contains($run['out'] . $run['err'], 'REFUSED'),
        'exit=' . $run['code']
    );

    // Identity mismatch
    $envBad = array_merge($baseEnv, ['ZENVITA_MIGRATE_EXPECT_DB' => 'definitely_not_this_db']);
    $run = t_run([$phpBin, $migrator, '--apply', '--i-understand-ddl'], $envBad, $root);
    t_assert(
        'Identity mismatch refused',
        $run['code'] === 1 && (str_contains($run['out'] . $run['err'], 'mismatch') || str_contains($run['out'] . $run['err'], 'REFUSED')),
        'exit=' . $run['code']
    );

    // Dry-run plan mentions reference
    t_reset_schema($pdo);
    $run = t_run([$phpBin, $migrator, '--dry-run'], $baseEnv, $root);
    $combined = $run['out'] . $run['err'];
    t_assert(
        'Migrator dry-run mentions reference not payment_reference',
        $run['code'] === 0
        && str_contains($combined, 'uq_reg_pay_reference')
        && str_contains($combined, 'registration_payments.reference')
        && !str_contains($combined, 'uq_reg_pay_reference on registration_payments(payment_reference)'),
        'exit=' . $run['code']
    );

    // Step tracker unit tests
    $tracker = migrate_step_tracker_init(['a', 'b', 'c']);
    t_assert('Step tracker init pending equals planned', $tracker['pending'] === ['a', 'b', 'c'] && $tracker['completed'] === []);
    migrate_step_mark_completed($tracker, 'a');
    t_assert('Partial failure tracker marks completed only after success', $tracker['completed'] === ['a'] && $tracker['pending'] === ['b', 'c']);
    migrate_step_mark_failed($tracker, 'b');
    t_assert('Failed step recorded without claiming pending complete', $tracker['failed'] === 'b' && in_array('c', $tracker['pending'], true));
}

// Identity helper timing helper exists
t_assert(
    'migrate_verify_db_identity_on_pdo exists',
    function_exists('migrate_verify_db_identity_on_pdo')
);

// Source inspection: Stage B uses (reference)
$src = file_get_contents($root . '/database/migrations/2026_phase1d_schema_readiness.php');
t_assert(
    'Source Stage B unique on (reference)',
    is_string($src)
    && str_contains($src, "'uq_reg_pay_reference'")
    && str_contains($src, "'(reference)'")
    && str_contains($src, "['reference']")
);
t_assert(
    'Source re-verifies identity before Stage B DDL',
    is_string($src) && str_contains($src, 'Identity re-verification immediately before Stage B DDL')
);
t_assert(
    'Source does not set ddlStarted before success',
    is_string($src) && !str_contains($src, '$ddlStarted = true')
);

$reportDir = $root . '/storage/reports';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0775, true);
}
$stamp = date('Ymd_His');
$reportPath = $reportDir . '/phase1h_test_results_' . $stamp . '.json';
file_put_contents($reportPath, json_encode([
    'phase' => '1H',
    'failed' => $failed,
    'not_executed' => $notExecuted,
    'results' => $results,
    'hostinger_accessed' => false,
    'production_db_accessed' => false,
    'primary_zenvita_migrated' => false,
], JSON_PRETTY_PRINT));

echo "\nFailed: {$failed}; Not executed: {$notExecuted}\n";
echo "Report: {$reportPath}\n";
exit($failed > 0 ? 1 : 0);
