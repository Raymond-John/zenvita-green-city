<?php
/**
 * Phase 1D/1F/1H/1J schema readiness migrator (CLI only).
 *
 * Verification sequence:
 *   1) Parse CLI (dry-run default; apply requires --apply --i-understand-ddl).
 *   2) Open one PDO via db(); capture configured host from config/database.php.
 *   3) Initial identity check (ZENVITA_MIGRATE_EXPECT_DB / optional HOST).
 *   4) Stage A — prerequisites, uniqueness, blank/whitespace refs, settings compatibility,
 *      existing unique-index definition checks (no DDL).
 *   5) Immediately before Stage B DDL: re-verify identity on the SAME PDO (SELECT DATABASE()).
 *   6) Stage B — DDL with step tracker (DONE only after success; ADDED/CREATED vs OK/SKIP no-op).
 *
 * Settings schema (app-compatible): setting_key VARCHAR(100) PK, setting_value TEXT NOT NULL.
 * uq_reg_pay_reference on registration_payments.reference (not payment_reference).
 *
 * MySQL DDL implicitly commits. Mid-Stage B failure may leave earlier completed DDL committed.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/schema_migrate.php';
migrate_cli_only();

$argvList = array_slice($argv ?? [], 1);
try {
    $cli = migrate_parse_cli_args($argvList);
} catch (InvalidArgumentException $e) {
    $msg = $e->getMessage();
    if ($msg === 'HELP') {
        fwrite(STDOUT, migrate_usage_text());
        exit(0);
    }
    fwrite(STDERR, "ERROR: {$msg}\n\n" . migrate_usage_text());
    exit(1);
}

$mode = $cli['mode'];
$isApply = $mode === 'apply';

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

$pdo = db();
$config = require dirname(__DIR__, 2) . '/config/database.php';
$hostConfig = (string) ($config['host'] ?? '');

$expect = [
    'expect_db' => getenv('ZENVITA_MIGRATE_EXPECT_DB') !== false
        ? (string) getenv('ZENVITA_MIGRATE_EXPECT_DB')
        : '',
    'expect_host' => getenv('ZENVITA_MIGRATE_EXPECT_HOST') !== false
        ? (string) getenv('ZENVITA_MIGRATE_EXPECT_HOST')
        : '',
];

$identity = migrate_verify_db_identity_on_pdo($pdo, $expect, $hostConfig, $isApply);

echo "=== Phase 1D/1J schema readiness ===\n";
echo "Mode: {$mode}\n";
echo "Identity status (startup): {$identity['status']}\n";
foreach ($identity['messages'] as $m) {
    echo "  - {$m}\n";
}
echo "Note: connected database/host values are not printed to avoid leaking environment details.\n";

if ($isApply && $identity['status'] !== 'passed') {
    fwrite(STDERR, "REFUSED: apply mode requires passed database identity verification.\n");
    exit(1);
}

if (!$isApply && $identity['status'] === 'failed') {
    fwrite(STDERR, "REFUSED: identity verification failed (dry-run will not continue).\n");
    exit(1);
}

if (!$isApply && $identity['status'] === 'not_configured') {
    echo "WARNING: identity not configured — Stage A inspection continues, but apply is impossible until ZENVITA_MIGRATE_EXPECT_DB is set and matches.\n";
}

echo "\n--- Stage A: read-only preflight ---\n";

$inventory = [];
$requiredTables = [
    'users',
    'registration_payments',
    'orders',
    'order_notifications',
    'payment_idempotency',
    'settings',
];
foreach ($requiredTables as $t) {
    $inventory["table:{$t}"] = migrate_table_exists($pdo, $t) ? 'present' : 'missing';
}

$columnChecks = [
    ['users', 'parent_id'],
    ['users', 'binary_side'],
    ['registration_payments', 'reference'],
    ['registration_payments', 'amount_kobo'],
    ['registration_payments', 'currency'],
    ['registration_payments', 'paid_at'],
    ['registration_payments', 'webhook_verified_at'],
    ['registration_payments', 'provider_event_id'],
    ['orders', 'payment_reference'],
    ['orders', 'paystack_reference'],
    ['orders', 'paid_at'],
    ['orders', 'webhook_verified_at'],
    ['orders', 'provider_event_id'],
    ['order_notifications', 'order_id'],
    ['settings', 'setting_key'],
    ['settings', 'setting_value'],
];
foreach ($columnChecks as [$table, $col]) {
    $key = "column:{$table}.{$col}";
    if (!migrate_table_exists($pdo, $table)) {
        $inventory[$key] = 'table_missing';
    } else {
        $inventory[$key] = migrate_column_exists($pdo, $table, $col) ? 'present' : 'missing';
    }
}

$indexChecks = [
    ['users', 'uq_users_parent_side', ['parent_id', 'binary_side']],
    ['registration_payments', 'uq_reg_pay_reference', ['reference']],
    ['orders', 'uq_orders_payment_reference', ['payment_reference']],
    ['orders', 'uq_orders_paystack_reference', ['paystack_reference']],
    ['order_notifications', 'uq_order_notifications_order', ['order_id']],
];
foreach ($indexChecks as [$table, $idx, $cols]) {
    $key = "index:{$table}.{$idx}";
    if (!migrate_table_exists($pdo, $table)) {
        $inventory[$key] = 'table_missing';
    } else {
        $state = migrate_unique_index_state($pdo, $table, $idx, $cols);
        $inventory[$key] = $state['status'];
    }
}

$settingsState = migrate_settings_schema_state($pdo);
$inventory['settings_schema'] = $settingsState['status'];

foreach ($inventory as $k => $v) {
    echo "  {$k}: {$v}\n";
}

try {
    migrate_test_hook('stage_a_throw', $pdo);
    $stageA = migrate_collect_uniqueness_blockers($pdo);
} catch (Throwable $e) {
    fwrite(STDERR, "Stage A CHECK FAILED (query/error) — no DDL will run: " . $e->getMessage() . "\n");
    fwrite(STDERR, "A failed check is never treated as zero duplicates.\n");
    exit(2);
}

echo "\nStage A check statuses:\n";
foreach ($stageA['checks'] as $c) {
    echo "  - {$c['constraint']}: {$c['status']} ({$c['detail']})\n";
}

if ($stageA['prerequisites'] !== []) {
    echo "\nPREREQUISITES MISSING / INCOMPATIBLE (Stage A) — no DDL will run:\n";
    foreach ($stageA['prerequisites'] as $p) {
        $col = $p['column'] !== null ? " column={$p['column']}" : '';
        echo "  - table={$p['table']} constraint={$p['constraint']}{$col} ({$p['detail']})\n";
    }
}

if ($stageA['blockers'] !== []) {
    echo "\nBLOCKERS (Stage A) — no DDL will run:\n";
    foreach ($stageA['blockers'] as $b) {
        echo "  - table={$b['table']} constraint={$b['constraint']}"
            . " duplicate_groups={$b['duplicate_groups']}"
            . " empty_string_rows={$b['empty_string_rows']}"
            . " ({$b['detail']})\n";
    }
}

if (!$stageA['ok']) {
    fwrite(STDERR, "Exit: Stage A blockers or missing prerequisites must be resolved before any DDL.\n");
    exit(2);
}
echo "Stage A uniqueness/integrity: clear\n";

$stageBPlan = [
    'create_payment_idempotency',
    'add_registration_payments_amount_kobo',
    'add_registration_payments_currency',
    'add_registration_payments_paid_at',
    'add_registration_payments_webhook_verified_at',
    'add_registration_payments_provider_event_id',
    'add_uq_reg_pay_reference_on_reference',
    'add_orders_payment_reference',
    'add_orders_paystack_reference',
    'add_orders_paid_at',
    'add_orders_webhook_verified_at',
    'add_orders_provider_event_id',
    'late_uniqueness_recheck',
    'add_uq_orders_payment_reference',
    'add_uq_orders_paystack_reference',
    'add_uq_order_notifications_order',
    'add_uq_users_parent_side',
    'create_settings',
];

echo "\n--- Stage B: DDL " . ($isApply ? 'execution' : 'plan (dry-run)') . " ---\n";
echo "WARNING: MySQL DDL implicitly commits. A mid-stage failure may leave earlier DDL applied.\n";
echo "NOTE: Log ADDED/CREATED = DDL committed; OK/SKIP = completed no-op (no new DDL).\n";

if (!$isApply) {
    foreach ($stageBPlan as $step) {
        echo "  PLAN: {$step}\n";
    }
    echo "  PLAN-NOTE: settings CREATE uses setting_key / setting_value (app-compatible)\n";
    echo "  PLAN-NOTE: uq_reg_pay_reference on registration_payments.reference\n";
    if ($identity['status'] === 'not_configured') {
        echo "\nDry-run complete with identity NOT CONFIGURED. Set ZENVITA_MIGRATE_EXPECT_DB before apply.\n";
        exit(0);
    }
    echo "\nDry-run complete. Identity: {$identity['status']}. No DDL executed.\n";
    exit(0);
}

echo "\nIdentity re-verification immediately before Stage B DDL (same PDO)...\n";
$identityPreDdl = migrate_verify_db_identity_on_pdo($pdo, $expect, $hostConfig, true);
echo "Identity status (pre-DDL): {$identityPreDdl['status']}\n";
foreach ($identityPreDdl['messages'] as $m) {
    echo "  - {$m}\n";
}
if ($identityPreDdl['status'] !== 'passed') {
    fwrite(STDERR, "REFUSED: pre-DDL identity verification failed — no DDL executed.\n");
    exit(1);
}

$log = [];
$tracker = migrate_step_tracker_init($stageBPlan);
$anyDdlCommitted = false;
$currentStep = null;

try {
    $currentStep = 'create_payment_idempotency';
    migrate_test_hook('first_ddl_throw', $pdo);
    $out = migrate_exec_create_table($pdo, 'payment_idempotency',
        "CREATE TABLE payment_idempotency (
            idempotency_key VARCHAR(128) NOT NULL,
            payment_type VARCHAR(32) NOT NULL,
            payment_id BIGINT UNSIGNED NULL,
            request_hash CHAR(64) NOT NULL,
            response_json LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'started',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (idempotency_key),
            KEY idx_payment_idempotency_type_id (payment_type, payment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        $log
    );
    if ($out === 'CREATED') {
        $anyDdlCommitted = true;
    }
    migrate_step_mark_completed($tracker, $currentStep);

    $colSteps = [
        'add_registration_payments_amount_kobo' => ['registration_payments', 'amount_kobo', 'amount_kobo BIGINT UNSIGNED NULL'],
        'add_registration_payments_currency' => ['registration_payments', 'currency', "currency VARCHAR(8) NOT NULL DEFAULT 'NGN'"],
        'add_registration_payments_paid_at' => ['registration_payments', 'paid_at', 'paid_at DATETIME NULL'],
        'add_registration_payments_webhook_verified_at' => ['registration_payments', 'webhook_verified_at', 'webhook_verified_at DATETIME NULL'],
        'add_registration_payments_provider_event_id' => ['registration_payments', 'provider_event_id', 'provider_event_id VARCHAR(128) NULL'],
    ];
    foreach ($colSteps as $stepName => [$table, $col, $def]) {
        $currentStep = $stepName;
        if ($stepName === 'add_registration_payments_amount_kobo') {
            migrate_test_hook('later_ddl_throw', $pdo);
        }
        $out = migrate_add_column($pdo, $table, $col, $def, $log);
        if ($out === 'ADDED') {
            $anyDdlCommitted = true;
        }
        migrate_step_mark_completed($tracker, $currentStep);
    }

    $currentStep = 'add_uq_reg_pay_reference_on_reference';
    $out = migrate_add_unique_index(
        $pdo,
        'registration_payments',
        'uq_reg_pay_reference',
        '(reference)',
        $log,
        ['reference']
    );
    if ($out === 'ADDED') {
        $anyDdlCommitted = true;
    }
    migrate_step_mark_completed($tracker, $currentStep);

    $orderCols = [
        'add_orders_payment_reference' => ['orders', 'payment_reference', 'payment_reference VARCHAR(128) NULL'],
        'add_orders_paystack_reference' => ['orders', 'paystack_reference', 'paystack_reference VARCHAR(128) NULL'],
        'add_orders_paid_at' => ['orders', 'paid_at', 'paid_at DATETIME NULL'],
        'add_orders_webhook_verified_at' => ['orders', 'webhook_verified_at', 'webhook_verified_at DATETIME NULL'],
        'add_orders_provider_event_id' => ['orders', 'provider_event_id', 'provider_event_id VARCHAR(128) NULL'],
    ];
    foreach ($orderCols as $stepName => [$table, $col, $def]) {
        $currentStep = $stepName;
        $out = migrate_add_column($pdo, $table, $col, $def, $log);
        if ($out === 'ADDED') {
            $anyDdlCommitted = true;
        }
        migrate_step_mark_completed($tracker, $currentStep);
    }

    $currentStep = 'late_uniqueness_recheck';
    $late = migrate_collect_uniqueness_blockers($pdo);
    if (!$late['ok']) {
        throw new RuntimeException(
            'Late uniqueness/prerequisite blockers detected immediately before unique-index DDL; aborting remaining DDL.'
        );
    }
    migrate_step_mark_completed($tracker, $currentStep);

    $uqSteps = [
        'add_uq_orders_payment_reference' => ['orders', 'uq_orders_payment_reference', '(payment_reference)', ['payment_reference']],
        'add_uq_orders_paystack_reference' => ['orders', 'uq_orders_paystack_reference', '(paystack_reference)', ['paystack_reference']],
        'add_uq_order_notifications_order' => ['order_notifications', 'uq_order_notifications_order', '(order_id)', ['order_id']],
        'add_uq_users_parent_side' => ['users', 'uq_users_parent_side', '(parent_id, binary_side)', ['parent_id', 'binary_side']],
    ];
    foreach ($uqSteps as $stepName => [$table, $idx, $colsSql, $expectedCols]) {
        $currentStep = $stepName;
        $out = migrate_add_unique_index($pdo, $table, $idx, $colsSql, $log, $expectedCols);
        if ($out === 'ADDED') {
            $anyDdlCommitted = true;
        }
        migrate_step_mark_completed($tracker, $currentStep);
    }

    $currentStep = 'create_settings';
    if (migrate_table_exists($pdo, 'settings')) {
        $st = migrate_settings_schema_state($pdo);
        if ($st['status'] !== 'compatible') {
            throw new RuntimeException('settings table incompatible at create step: ' . $st['detail']);
        }
        $log[] = 'OK table settings already exists and is compatible (no-op)';
        migrate_step_mark_completed($tracker, $currentStep);
    } else {
        $out = migrate_exec_create_table($pdo, 'settings', migrate_settings_create_sql(), $log);
        if ($out === 'CREATED') {
            $anyDdlCommitted = true;
        }
        migrate_step_mark_completed($tracker, $currentStep);
    }
} catch (Throwable $e) {
    if ($currentStep !== null) {
        migrate_step_mark_failed($tracker, $currentStep);
    }
    foreach ($log as $line) {
        echo "  {$line}\n";
    }
    echo "\n--- Stage B partial-failure report ---\n";
    echo 'Completed steps (' . count($tracker['completed']) . "):\n";
    foreach ($tracker['completed'] as $s) {
        echo "  DONE: {$s}\n";
    }
    echo 'Failed step: ' . ($tracker['failed'] ?? '(unknown)') . "\n";
    echo "Pending steps:\n";
    foreach ($tracker['pending'] as $s) {
        echo "  PENDING: {$s}\n";
    }
    fwrite(STDERR, 'Stage B ERROR: ' . $e->getMessage() . "\n");
    if ($anyDdlCommitted) {
        fwrite(STDERR, "MySQL may have implicitly committed earlier successful DDL (ADDED/CREATED). Do not assume nothing changed.\n");
    } else {
        fwrite(STDERR, "No ADDED/CREATED DDL outcome was recorded before this failure.\n");
    }
    fwrite(STDERR, "Inspect schema with dry-run, then resume only after resolving the failed step.\n");
    exit(3);
}

foreach ($log as $line) {
    echo "  {$line}\n";
}
echo "\n--- Stage B completion ---\n";
foreach ($tracker['completed'] as $s) {
    echo "  DONE: {$s}\n";
}
echo "Apply complete. Re-run dry-run to verify inventory.\n";
exit(0);
