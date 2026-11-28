<?php
/**
 * Shared idempotent schema helpers for CLI migrations only.
 * MySQL DDL statements implicitly commit — callers must not assume transactional rollback of ALTER/CREATE.
 *
 * Empty / whitespace-only reference policy (Phase 1H/1J):
 * MySQL UNIQUE indexes treat '' as a real value. Whitespace-only values (TRIM = '') are not legitimate
 * payment references in this application. Stage A blocks when any non-NULL blank/whitespace-only value
 * exists on unique reference columns, and also blocks duplicate non-blank values.
 * Migrator never converts, trims, deletes, or rewrites existing reference values.
 */

function migrate_cli_only(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
}

/**
 * Test-only failure hooks. Armed only when DATABASE() is an isolated test DB
 * (zenvita_phase1f_test / zenvita_phase1j_test). Never activates on primary zenvita or production names.
 */
function migrate_test_hook(string $hookName, PDO $pdo): void
{
    $want = getenv('ZENVITA_MIGRATE_TEST_HOOK');
    if ($want === false || $want === '' || $want !== $hookName) {
        return;
    }
    $db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $allowed = ['zenvita_phase1f_test', 'zenvita_phase1j_test'];
    if (!in_array($db, $allowed, true)) {
        return;
    }
    throw new RuntimeException('TEST HOOK: ' . $hookName);
}

function migrate_assert_sql_identifier(string $identifier, string $kind = 'identifier'): string
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        throw new InvalidArgumentException("Invalid SQL {$kind}: empty.");
    }
    if (strlen($identifier) > 64) {
        throw new InvalidArgumentException("Invalid SQL {$kind}: too long.");
    }
    if (str_contains($identifier, '`')
        || str_contains($identifier, ' ')
        || str_contains($identifier, "\n")
        || str_contains($identifier, "\r")
        || str_contains($identifier, "\t")
        || str_contains($identifier, '-')
        || str_contains($identifier, ';')
        || str_contains($identifier, '/')
        || str_contains($identifier, '*')
        || str_contains($identifier, '\\')
    ) {
        throw new InvalidArgumentException("Invalid SQL {$kind}: forbidden characters.");
    }
    if (!preg_match('/^[A-Za-z0-9_$]+$/', $identifier)) {
        throw new InvalidArgumentException("Invalid SQL {$kind}: must match [A-Za-z0-9_\$]+.");
    }
    return $identifier;
}

function migrate_quote_identifier(string $identifier, string $kind = 'identifier'): string
{
    $id = migrate_assert_sql_identifier($identifier, $kind);
    return '`' . $id . '`';
}

/**
 * @param list<string> $args
 * @return array{mode:'dry-run'|'apply', confirmed:bool}
 */
function migrate_parse_cli_args(array $args): array
{
    $allowed = ['--dry-run', '--apply', '--i-understand-ddl', '--help', '-h'];
    $seen = [];
    $dry = false;
    $apply = false;
    $confirmed = false;

    foreach ($args as $arg) {
        if (!is_string($arg) || $arg === '') {
            throw new InvalidArgumentException('Malformed CLI argument.');
        }
        if (!str_starts_with($arg, '-')) {
            throw new InvalidArgumentException('Unknown argument: ' . $arg);
        }
        if (!in_array($arg, $allowed, true)) {
            throw new InvalidArgumentException('Unknown argument: ' . $arg);
        }
        if (isset($seen[$arg])) {
            throw new InvalidArgumentException('Duplicate argument: ' . $arg);
        }
        $seen[$arg] = true;
        if ($arg === '--dry-run') {
            $dry = true;
        } elseif ($arg === '--apply') {
            $apply = true;
        } elseif ($arg === '--i-understand-ddl') {
            $confirmed = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            throw new InvalidArgumentException('HELP');
        }
    }

    if ($dry && $apply) {
        throw new InvalidArgumentException('Conflicting arguments: --dry-run and --apply.');
    }
    if ($apply && !$confirmed) {
        throw new InvalidArgumentException('Apply requires both --apply and --i-understand-ddl.');
    }
    if ($confirmed && !$apply) {
        throw new InvalidArgumentException('--i-understand-ddl is only valid with --apply.');
    }

    return [
        'mode' => $apply ? 'apply' : 'dry-run',
        'confirmed' => $confirmed,
    ];
}

function migrate_usage_text(): string
{
    return "Usage:\n"
        . "  php database/migrations/2026_phase1d_schema_readiness.php [--dry-run]\n"
        . "  php database/migrations/2026_phase1d_schema_readiness.php --apply --i-understand-ddl\n"
        . "\nRequired for apply:\n"
        . "  ZENVITA_MIGRATE_EXPECT_DB   Exact database name that must match DATABASE()\n"
        . "Optional:\n"
        . "  ZENVITA_MIGRATE_EXPECT_HOST Exact configured connection host\n"
        . "\nIdentity is verified at startup and re-verified on the same PDO immediately before Stage B DDL.\n"
        . "Log lines ADDED/CREATED mean DDL committed; OK/SKIP are completed no-ops (no new DDL).\n";
}

/**
 * @param array{expect_db:?string,expect_host:?string} $expect
 * @param array{database:string,host_config:string} $actual
 * @return array{status:'passed'|'failed'|'not_configured', messages:list<string>}
 */
function migrate_verify_db_identity(array $expect, array $actual, bool $requireForMode): array
{
    $messages = [];
    $expectDb = isset($expect['expect_db']) ? trim((string) $expect['expect_db']) : '';
    $expectHost = isset($expect['expect_host']) ? trim((string) $expect['expect_host']) : '';
    $actualDb = trim((string) ($actual['database'] ?? ''));
    $actualHost = trim((string) ($actual['host_config'] ?? ''));

    if ($expectDb === '') {
        $messages[] = 'ZENVITA_MIGRATE_EXPECT_DB is not set (expected database identity missing).';
        if ($requireForMode) {
            return ['status' => 'failed', 'messages' => $messages];
        }
        return ['status' => 'not_configured', 'messages' => $messages];
    }

    if ($actualDb === '') {
        $messages[] = 'Connected database name is empty.';
        return ['status' => 'failed', 'messages' => $messages];
    }
    if (!hash_equals($expectDb, $actualDb)) {
        $messages[] = 'Database name mismatch (expected identity does not match connected DATABASE()).';
        return ['status' => 'failed', 'messages' => $messages];
    }
    $messages[] = 'Database name identity matched.';

    if ($expectHost !== '') {
        if ($actualHost === '') {
            $messages[] = 'Configured connection host is empty but ZENVITA_MIGRATE_EXPECT_HOST was set.';
            return ['status' => 'failed', 'messages' => $messages];
        }
        if (!hash_equals(strtolower($expectHost), strtolower($actualHost))) {
            $messages[] = 'Connection host mismatch (expected host does not match configured host).';
            return ['status' => 'failed', 'messages' => $messages];
        }
        $messages[] = 'Connection host identity matched.';
    } else {
        $messages[] = 'ZENVITA_MIGRATE_EXPECT_HOST not set; host comparison skipped.';
    }

    return ['status' => 'passed', 'messages' => $messages];
}

/**
 * @param array{expect_db:?string,expect_host:?string} $expect
 * @return array{status:'passed'|'failed'|'not_configured', messages:list<string>, database_present:bool}
 */
function migrate_verify_db_identity_on_pdo(PDO $pdo, array $expect, string $hostConfig, bool $requireForMode): array
{
    $actualDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $result = migrate_verify_db_identity(
        $expect,
        ['database' => $actualDb, 'host_config' => $hostConfig],
        $requireForMode
    );
    $result['database_present'] = $actualDb !== '';
    return $result;
}

function migrate_table_exists(PDO $pdo, string $table): bool
{
    $table = migrate_assert_sql_identifier($table, 'table');
    // SHOW TABLES avoids fragile information_schema privilege-table paths on some local MariaDB installs.
    $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
    return (bool) $stmt->fetchColumn();
}

function migrate_column_exists(PDO $pdo, string $table, string $column): bool
{
    $table = migrate_assert_sql_identifier($table, 'table');
    $column = migrate_assert_sql_identifier($column, 'column');
    if (!migrate_table_exists($pdo, $table)) {
        return false;
    }
    $stmt = $pdo->query('SHOW COLUMNS FROM ' . migrate_quote_identifier($table, 'table') . ' LIKE ' . $pdo->quote($column));
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Name-only existence (legacy). Prefer migrate_unique_index_state for constraint checks.
 */
function migrate_index_exists(PDO $pdo, string $table, string $indexName): bool
{
    $state = migrate_unique_index_state($pdo, $table, $indexName, null);
    return $state['status'] === 'match' || $state['status'] === 'mismatch' || $state['status'] === 'non_unique';
}

/**
 * Inspect unique index definition via SHOW INDEX (ordered by Seq_in_index).
 *
 * @param list<string>|null $expectedColumns Ordered columns required for a match; null = any unique with that name
 * @return array{status:'absent'|'match'|'mismatch'|'non_unique'|'table_missing', columns:list<string>, non_unique:int|null, detail:string}
 */
function migrate_unique_index_state(PDO $pdo, string $table, string $indexName, ?array $expectedColumns = null): array
{
    $table = migrate_assert_sql_identifier($table, 'table');
    $indexName = migrate_assert_sql_identifier($indexName, 'index');
    if (!migrate_table_exists($pdo, $table)) {
        return [
            'status' => 'table_missing',
            'columns' => [],
            'non_unique' => null,
            'detail' => "Table {$table} missing",
        ];
    }

    $stmt = $pdo->query('SHOW INDEX FROM ' . migrate_quote_identifier($table, 'table'));
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string) ($row['Key_name'] ?? '') === $indexName) {
            $rows[] = $row;
        }
    }
    usort($rows, static function (array $a, array $b): int {
        return ((int) ($a['Seq_in_index'] ?? 0)) <=> ((int) ($b['Seq_in_index'] ?? 0));
    });

    if ($rows === []) {
        return [
            'status' => 'absent',
            'columns' => [],
            'non_unique' => null,
            'detail' => "Index {$indexName} absent",
        ];
    }

    $columns = [];
    $nonUnique = (int) ($rows[0]['Non_unique'] ?? 1);
    foreach ($rows as $row) {
        $columns[] = (string) $row['Column_name'];
        if ((int) ($row['Non_unique'] ?? 1) !== 0) {
            $nonUnique = 1;
        }
    }

    if ($nonUnique !== 0) {
        return [
            'status' => 'non_unique',
            'columns' => $columns,
            'non_unique' => $nonUnique,
            'detail' => "Index {$indexName} exists but is not UNIQUE",
        ];
    }

    if ($expectedColumns !== null) {
        $expected = array_values(array_map(
            static fn($c) => migrate_assert_sql_identifier((string) $c, 'column'),
            $expectedColumns
        ));
        if ($columns !== $expected) {
            return [
                'status' => 'mismatch',
                'columns' => $columns,
                'non_unique' => 0,
                'detail' => "Index {$indexName} UNIQUE but columns do not match required definition",
            ];
        }
    }

    return [
        'status' => 'match',
        'columns' => $columns,
        'non_unique' => 0,
        'detail' => "Index {$indexName} matches",
    ];
}

/**
 * Expected settings schema (matches helpers.php / admin-settings / phase1b fixture):
 * setting_key VARCHAR(100) NOT NULL PRIMARY KEY, setting_value TEXT NOT NULL
 *
 * @return array{status:'absent'|'compatible'|'incompatible', detail:string}
 */
function migrate_settings_schema_state(PDO $pdo): array
{
    if (!migrate_table_exists($pdo, 'settings')) {
        return ['status' => 'absent', 'detail' => 'settings table absent; Stage B may CREATE compatible schema'];
    }

    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM ' . migrate_quote_identifier('settings', 'table'))->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cols[strtolower((string) $row['Field'])] = $row;
    }

    if (!isset($cols['setting_key'], $cols['setting_value'])) {
        return [
            'status' => 'incompatible',
            'detail' => 'settings exists but missing setting_key and/or setting_value (app-required columns)',
        ];
    }

    $key = $cols['setting_key'];
    $val = $cols['setting_value'];
    $keyKey = strtoupper((string) ($key['Key'] ?? ''));
    if ($keyKey !== 'PRI') {
        return [
            'status' => 'incompatible',
            'detail' => 'settings.setting_key is not PRIMARY KEY',
        ];
    }
    if (strtoupper((string) ($key['Null'] ?? '')) === 'YES') {
        return [
            'status' => 'incompatible',
            'detail' => 'settings.setting_key must be NOT NULL',
        ];
    }
    if (strtoupper((string) ($val['Null'] ?? '')) === 'YES') {
        return [
            'status' => 'incompatible',
            'detail' => 'settings.setting_value must be NOT NULL',
        ];
    }
    $type = strtolower((string) ($val['Type'] ?? ''));
    if (!preg_match('/^(tiny|medium|long)?text\b/', $type) && !str_starts_with($type, 'varchar')) {
        return [
            'status' => 'incompatible',
            'detail' => 'settings.setting_value type is not text-compatible',
        ];
    }

    return ['status' => 'compatible', 'detail' => 'settings has setting_key PK and setting_value text-compatible NOT NULL'];
}

/**
 * Stage A: prerequisites + uniqueness + blank/whitespace reference policy + settings + index definition mismatches.
 *
 * @return array{
 *   ok:bool,
 *   blockers:list<array<string,mixed>>,
 *   prerequisites:list<array<string,mixed>>,
 *   checks:list<array{constraint:string,status:string,detail:string}>
 * }
 */
function migrate_collect_uniqueness_blockers(PDO $pdo): array
{
    $blockers = [];
    $prerequisites = [];
    $checks = [];

    // Hard Stage A prerequisites for Stage B ALTER/UNIQUE targets
    // [table, column|null, constraint, requiredColumnLabel for missing-table reports]
    $hard = [
        ['users', null, 'uq_users_parent_side', 'parent_id/binary_side'],
        ['users', 'parent_id', 'uq_users_parent_side', 'parent_id'],
        ['users', 'binary_side', 'uq_users_parent_side', 'binary_side'],
        ['registration_payments', null, 'uq_reg_pay_reference', 'reference'],
        ['registration_payments', 'reference', 'uq_reg_pay_reference', 'reference'],
        ['orders', null, 'orders_table', null],
        ['order_notifications', null, 'uq_order_notifications_order', 'order_id'],
        ['order_notifications', 'order_id', 'uq_order_notifications_order', 'order_id'],
    ];
    foreach ($hard as [$table, $column, $constraint, $label]) {
        if ($column === null) {
            if (!migrate_table_exists($pdo, $table)) {
                $prerequisites[] = [
                    'constraint' => $constraint,
                    'table' => $table,
                    'column' => $label,
                    'detail' => "Required table {$table} missing",
                ];
                $checks[] = ['constraint' => $constraint, 'status' => 'prerequisite_missing', 'detail' => "table {$table} missing"];
            }
        } else {
            if (!migrate_table_exists($pdo, $table)) {
                continue; // already recorded
            }
            if (!migrate_column_exists($pdo, $table, $column)) {
                $prerequisites[] = [
                    'constraint' => $constraint,
                    'table' => $table,
                    'column' => $column,
                    'detail' => "Required column {$table}.{$column} missing",
                ];
                $checks[] = ['constraint' => $constraint, 'status' => 'prerequisite_missing', 'detail' => "column {$column} missing"];
            }
        }
    }

    // Order payment reference columns may be added in Stage B; table must exist.
    // Missing columns are NOT reported as uniqueness-clear — status will_add_column (verified absence).
    foreach (
        [
            ['orders', 'payment_reference', 'uq_orders_payment_reference'],
            ['orders', 'paystack_reference', 'uq_orders_paystack_reference'],
        ] as [$table, $column, $constraint]
    ) {
        if (!migrate_table_exists($pdo, $table)) {
            continue;
        }
        if (!migrate_column_exists($pdo, $table, $column)) {
            $checks[] = [
                'constraint' => $constraint,
                'status' => 'will_add_column',
                'detail' => "Column {$table}.{$column} absent; Stage B will ADD then late-recheck (not counted as uniqueness-clear)",
            ];
        }
    }

    // Settings compatibility (absent OK; incompatible blocks)
    $settings = migrate_settings_schema_state($pdo);
    if ($settings['status'] === 'incompatible') {
        $prerequisites[] = [
            'constraint' => 'settings_schema',
            'table' => 'settings',
            'column' => 'setting_key/setting_value',
            'detail' => $settings['detail'],
        ];
        $checks[] = ['constraint' => 'settings_schema', 'status' => 'prerequisite_missing', 'detail' => $settings['detail']];
    } else {
        $checks[] = ['constraint' => 'settings_schema', 'status' => $settings['status'] === 'absent' ? 'will_create' : 'clear', 'detail' => $settings['detail']];
    }

    // Index definition mismatches for indexes that already exist under the expected names
    $indexDefs = [
        ['users', 'uq_users_parent_side', ['parent_id', 'binary_side']],
        ['registration_payments', 'uq_reg_pay_reference', ['reference']],
        ['orders', 'uq_orders_payment_reference', ['payment_reference']],
        ['orders', 'uq_orders_paystack_reference', ['paystack_reference']],
        ['order_notifications', 'uq_order_notifications_order', ['order_id']],
    ];
    foreach ($indexDefs as [$table, $idx, $cols]) {
        if (!migrate_table_exists($pdo, $table)) {
            continue;
        }
        $state = migrate_unique_index_state($pdo, $table, $idx, $cols);
        if ($state['status'] === 'mismatch' || $state['status'] === 'non_unique') {
            $blockers[] = [
                'constraint' => $idx,
                'table' => $table,
                'duplicate_groups' => 0,
                'empty_string_rows' => 0,
                'detail' => 'BLOCKED: ' . $state['detail'],
            ];
            $checks[] = ['constraint' => $idx, 'status' => 'blocked', 'detail' => $state['detail']];
        } elseif ($state['status'] === 'match') {
            $checks[] = ['constraint' => $idx . '_definition', 'status' => 'clear', 'detail' => $state['detail']];
        } elseif ($state['status'] === 'absent') {
            $checks[] = ['constraint' => $idx . '_definition', 'status' => 'absent', 'detail' => $state['detail']];
        }
    }

    // Uniqueness + blank/whitespace — only when table+column present
    if (migrate_table_exists($pdo, 'users')
        && migrate_column_exists($pdo, 'users', 'parent_id')
        && migrate_column_exists($pdo, 'users', 'binary_side')
    ) {
        $dupSeat = (int) $pdo->query(
            "SELECT COUNT(*) FROM (
                SELECT parent_id, binary_side
                FROM users
                WHERE parent_id IS NOT NULL AND binary_side IN ('left','right')
                GROUP BY parent_id, binary_side
                HAVING COUNT(*) > 1
             ) d"
        )->fetchColumn();
        if ($dupSeat > 0) {
            $blockers[] = [
                'constraint' => 'uq_users_parent_side',
                'table' => 'users',
                'duplicate_groups' => $dupSeat,
                'empty_string_rows' => 0,
                'detail' => 'Duplicate (parent_id, binary_side) groups',
            ];
            $checks[] = ['constraint' => 'uq_users_parent_side_data', 'status' => 'blocked', 'detail' => "duplicate_groups={$dupSeat}"];
        } else {
            $checks[] = ['constraint' => 'uq_users_parent_side_data', 'status' => 'clear', 'detail' => 'no duplicate placement groups'];
        }
    }

    if (migrate_table_exists($pdo, 'order_notifications') && migrate_column_exists($pdo, 'order_notifications', 'order_id')) {
        $dupNotif = (int) $pdo->query(
            'SELECT COUNT(*) FROM (
                SELECT order_id FROM order_notifications GROUP BY order_id HAVING COUNT(*) > 1
             ) d'
        )->fetchColumn();
        if ($dupNotif > 0) {
            $blockers[] = [
                'constraint' => 'uq_order_notifications_order',
                'table' => 'order_notifications',
                'duplicate_groups' => $dupNotif,
                'empty_string_rows' => 0,
                'detail' => 'Duplicate order_id groups',
            ];
            $checks[] = ['constraint' => 'uq_order_notifications_order_data', 'status' => 'blocked', 'detail' => "duplicate_groups={$dupNotif}"];
        } else {
            $checks[] = ['constraint' => 'uq_order_notifications_order_data', 'status' => 'clear', 'detail' => 'no duplicate order_id groups'];
        }
    }

    migrate_stage_a_reference_column($pdo, 'orders', 'payment_reference', 'uq_orders_payment_reference', $blockers, $checks);
    migrate_stage_a_reference_column($pdo, 'orders', 'paystack_reference', 'uq_orders_paystack_reference', $blockers, $checks);
    migrate_stage_a_reference_column($pdo, 'registration_payments', 'reference', 'uq_reg_pay_reference', $blockers, $checks);

    $ok = $blockers === [] && $prerequisites === [];
    return [
        'ok' => $ok,
        'blockers' => $blockers,
        'prerequisites' => $prerequisites,
        'checks' => $checks,
    ];
}

/**
 * @param list<array<string,mixed>> $blockers
 * @param list<array<string,mixed>> $checks
 */
function migrate_stage_a_reference_column(
    PDO $pdo,
    string $table,
    string $column,
    string $constraint,
    array &$blockers,
    array &$checks
): void {
    if (!migrate_table_exists($pdo, $table) || !migrate_column_exists($pdo, $table, $column)) {
        return; // prerequisite already recorded
    }

    $colQ = migrate_quote_identifier($column, 'column');
    $tableQ = migrate_quote_identifier($table, 'table');

    // Non-blank = not NULL and not only whitespace (MySQL TRIM() removes spaces only; use REGEXP for tabs etc.)
    $dup = (int) $pdo->query(
        "SELECT COUNT(*) FROM (
            SELECT {$colQ}
            FROM {$tableQ}
            WHERE {$colQ} IS NOT NULL AND {$colQ} NOT REGEXP '^[[:space:]]*$'
            GROUP BY {$colQ}
            HAVING COUNT(*) > 1
         ) d"
    )->fetchColumn();

    // Blank / whitespace-only (includes '', spaces, tabs): any non-NULL whitespace-only value is a blocker
    $blankRows = (int) $pdo->query(
        "SELECT COUNT(*) FROM {$tableQ}
         WHERE {$colQ} IS NOT NULL AND {$colQ} REGEXP '^[[:space:]]*$'"
    )->fetchColumn();

    if ($dup > 0) {
        $blockers[] = [
            'constraint' => $constraint,
            'table' => $table,
            'duplicate_groups' => $dup,
            'empty_string_rows' => 0,
            'detail' => "Duplicate non-blank {$table}.{$column} values",
        ];
        $checks[] = ['constraint' => $constraint . '_data', 'status' => 'blocked', 'detail' => "duplicate_groups={$dup}"];
        return;
    }
    if ($blankRows > 0) {
        $blockers[] = [
            'constraint' => $constraint,
            'table' => $table,
            'duplicate_groups' => 0,
            'empty_string_rows' => $blankRows,
            'detail' => "Blank/whitespace-only {$table}.{$column} values are blockers (not converted/deleted)",
        ];
        $checks[] = ['constraint' => $constraint . '_data', 'status' => 'blocked', 'detail' => "blank_or_whitespace_rows={$blankRows}"];
        return;
    }

    $checks[] = [
        'constraint' => $constraint . '_data',
        'status' => 'clear',
        'detail' => 'no duplicate non-blank groups; no blank/whitespace-only values',
    ];
}

/**
 * @return array{planned:list<string>, completed:list<string>, failed:?string, pending:list<string>}
 */
function migrate_step_tracker_init(array $planned): array
{
    return [
        'planned' => array_values($planned),
        'completed' => [],
        'failed' => null,
        'pending' => array_values($planned),
    ];
}

function migrate_step_mark_completed(array &$tracker, string $step): void
{
    $tracker['completed'][] = $step;
    $tracker['pending'] = array_values(array_filter(
        $tracker['pending'],
        static fn(string $s): bool => $s !== $step
    ));
}

function migrate_step_mark_failed(array &$tracker, string $step): void
{
    $tracker['failed'] = $step;
    $tracker['pending'] = array_values(array_filter(
        $tracker['pending'],
        static fn(string $s): bool => $s !== $step
    ));
}

/**
 * @return string ADDED|OK|SKIP
 */
function migrate_add_column(PDO $pdo, string $table, string $column, string $definitionSql, array &$log): string
{
    $table = migrate_assert_sql_identifier($table, 'table');
    $column = migrate_assert_sql_identifier($column, 'column');
    if (!migrate_table_exists($pdo, $table)) {
        $log[] = "SKIP column {$table}.{$column}: table missing";
        return 'SKIP';
    }
    if (migrate_column_exists($pdo, $table, $column)) {
        $log[] = "OK {$table}.{$column} already exists (no-op)";
        return 'OK';
    }
    if (!str_starts_with($definitionSql, $column . ' ') && !str_starts_with($definitionSql, $column . "\t")) {
        throw new InvalidArgumentException('Column definition must begin with the validated column name.');
    }
    $pdo->exec('ALTER TABLE ' . migrate_quote_identifier($table, 'table') . ' ADD COLUMN ' . $definitionSql);
    $log[] = "ADDED {$table}.{$column}";
    return 'ADDED';
}

/**
 * @param list<string> $expectedColumns
 * @return string ADDED|OK|SKIP
 */
function migrate_add_unique_index(
    PDO $pdo,
    string $table,
    string $indexName,
    string $columnsSql,
    array &$log,
    array $expectedColumns,
    ?callable $precheck = null
): string {
    $table = migrate_assert_sql_identifier($table, 'table');
    $indexName = migrate_assert_sql_identifier($indexName, 'index');
    if (!migrate_table_exists($pdo, $table)) {
        $log[] = "SKIP index {$indexName}: table {$table} missing";
        return 'SKIP';
    }

    $state = migrate_unique_index_state($pdo, $table, $indexName, $expectedColumns);
    if ($state['status'] === 'match') {
        $log[] = "OK index {$indexName} already matches required definition (no-op)";
        return 'OK';
    }
    if ($state['status'] === 'mismatch' || $state['status'] === 'non_unique') {
        $log[] = "BLOCKED index {$indexName}: {$state['detail']}";
        throw new RuntimeException("Unique index {$indexName} blocked: {$state['detail']}");
    }

    if ($precheck !== null) {
        $msg = $precheck($pdo);
        if ($msg !== null) {
            $log[] = "BLOCKED index {$indexName}: {$msg}";
            throw new RuntimeException("Unique index {$indexName} blocked: {$msg}");
        }
    }
    if (!preg_match('/^\([A-Za-z0-9_$,`\s]+\)$/', $columnsSql)) {
        throw new InvalidArgumentException('Invalid unique index column list.');
    }
    $pdo->exec(
        'ALTER TABLE ' . migrate_quote_identifier($table, 'table')
        . ' ADD UNIQUE KEY ' . migrate_quote_identifier($indexName, 'index') . ' ' . $columnsSql
    );
    $log[] = "ADDED index {$indexName}";
    return 'ADDED';
}

/**
 * @return string CREATED|OK
 */
function migrate_exec_create_table(PDO $pdo, string $table, string $createSql, array &$log): string
{
    $table = migrate_assert_sql_identifier($table, 'table');
    if (migrate_table_exists($pdo, $table)) {
        $log[] = "OK table {$table} already exists (no-op)";
        return 'OK';
    }
    if (!preg_match('/^\s*CREATE\s+TABLE\s+/i', $createSql)) {
        throw new InvalidArgumentException('CREATE TABLE SQL rejected.');
    }
    $pdo->exec($createSql);
    $log[] = "CREATED table {$table}";
    return 'CREATED';
}

/** Canonical CREATE for settings matching application usage. */
function migrate_settings_create_sql(): string
{
    return "CREATE TABLE settings (
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT NOT NULL,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}
