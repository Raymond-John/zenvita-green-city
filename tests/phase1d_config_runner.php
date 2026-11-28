<?php
/**
 * Phase 1D configuration resolver tests (no database required).
 * Usage: C:\xampp\php\php.exe tests/phase1d_config_runner.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/bootstrap/app.php';

$failed = 0;
$results = [];

function t_assert(string $name, bool $ok, string $detail = ''): void
{
    global $failed, $results;
    $status = $ok ? 'Passed' : 'Failed';
    if (!$ok) {
        $failed++;
    }
    $results[] = compact('name', 'status', 'detail');
    echo str_pad($status, 12) . $name . ($detail !== '' ? " — $detail" : '') . PHP_EOL;
}

echo "=== Phase 1D configuration tests ===\n";

// 1. No environment configuration (empty file + no local + no env vars)
$none = resolve_app_config([], null, []);
t_assert('No config: env is production', ($none['env'] ?? '') === 'production');
t_assert('No config: runtime DDL false', ($none['allow_runtime_ddl'] ?? true) === false);
t_assert('No config: debug false', ($none['debug'] ?? true) === false);

// 2. Explicit local
$local = resolve_app_config(
    ['env' => 'production', 'allow_runtime_ddl' => false, 'debug' => false],
    ['env' => 'local', 'allow_runtime_ddl' => true, 'debug' => true],
    []
);
t_assert('Explicit local: env local', $local['env'] === 'local');
t_assert('Explicit local: runtime DDL true', $local['allow_runtime_ddl'] === true);
t_assert('Explicit local: debug true', $local['debug'] === true);

// 3. Explicit testing
$testing = resolve_app_config([], null, [
    'ZENVITA_APP_ENV' => 'testing',
    'ZENVITA_ALLOW_RUNTIME_DDL' => 'true',
    'ZENVITA_APP_DEBUG' => 'true',
]);
t_assert('Explicit testing: env testing', $testing['env'] === 'testing');
t_assert('Explicit testing: runtime DDL true', $testing['allow_runtime_ddl'] === true);
t_assert('Explicit testing: debug true', $testing['debug'] === true);

// 4. Explicit production
$prod = resolve_app_config(['env' => 'production', 'allow_runtime_ddl' => false, 'debug' => false], null, []);
t_assert('Explicit production: env production', $prod['env'] === 'production');
t_assert('Explicit production: runtime DDL false', $prod['allow_runtime_ddl'] === false);
t_assert('Explicit production: debug false', $prod['debug'] === false);

// 5. Production with runtime DDL requested as true (file + env var)
$prodDdlFile = resolve_app_config(['env' => 'production', 'allow_runtime_ddl' => true, 'debug' => true], null, []);
t_assert('Production + file DDL true: DDL forced false', $prodDdlFile['allow_runtime_ddl'] === false);
t_assert('Production + file DDL true: debug forced false', $prodDdlFile['debug'] === false);

$prodDdlEnv = resolve_app_config(
    ['env' => 'local', 'allow_runtime_ddl' => true, 'debug' => true],
    null,
    ['ZENVITA_APP_ENV' => 'production', 'ZENVITA_ALLOW_RUNTIME_DDL' => 'true', 'ZENVITA_APP_DEBUG' => 'true']
);
t_assert('Production via env + DDL true: env production', $prodDdlEnv['env'] === 'production');
t_assert('Production via env + DDL true: DDL forced false', $prodDdlEnv['allow_runtime_ddl'] === false);
t_assert('Production via env + DDL true: debug forced false', $prodDdlEnv['debug'] === false);

// 6. Production with runtime DDL omitted
$prodOmit = resolve_app_config(['env' => 'production'], null, []);
t_assert('Production DDL omitted: DDL false', ($prodOmit['allow_runtime_ddl'] ?? true) === false);

// Staging treated as production-like
$staging = resolve_app_config([], null, [
    'ZENVITA_APP_ENV' => 'staging',
    'ZENVITA_ALLOW_RUNTIME_DDL' => '1',
    'ZENVITA_APP_DEBUG' => '1',
]);
t_assert('Staging: DDL forced false', $staging['allow_runtime_ddl'] === false);
t_assert('Staging: debug forced false', $staging['debug'] === false);

// Unknown env → production
$unknown = resolve_app_config(['env' => 'weird-cloud'], null, ['ZENVITA_ALLOW_RUNTIME_DDL' => 'true']);
t_assert('Unknown env normalizes to production', $unknown['env'] === 'production');
t_assert('Unknown env: DDL false', $unknown['allow_runtime_ddl'] === false);

// app_allows_runtime_ddl / app_debug helpers honour hard lock
app_settings_set_for_tests(['env' => 'production', 'allow_runtime_ddl' => true, 'debug' => true]);
t_assert('Helper hard lock: production DDL false', app_allows_runtime_ddl() === false);
t_assert('Helper hard lock: production debug false', app_debug() === false);
app_settings_set_for_tests(['env' => 'local', 'allow_runtime_ddl' => true, 'debug' => true]);
t_assert('Helper local: DDL true', app_allows_runtime_ddl() === true);
t_assert('Helper local: debug true', app_debug() === true);
app_settings_clear_test_override();

// Loader does not expose secrets in a report shape
$safeKeys = array_keys(resolve_app_config([], null, []));
$forbidden = ['password', 'secret', 'paystack', 'db_pass', 'pass'];
$leaked = false;
foreach ($safeKeys as $k) {
    foreach ($forbidden as $f) {
        if (str_contains(strtolower((string) $k), $f)) {
            $leaked = true;
        }
    }
}
t_assert('Config keys contain no secret-like fields', $leaked === false, implode(',', $safeKeys));

$report = $root . '/storage/reports/phase1d_config_results_' . gmdate('Ymd_His') . '.json';
file_put_contents($report, json_encode([
    'generated_at' => gmdate('c'),
    'failed' => $failed,
    'results' => $results,
    // No credentials — only resolved non-secret app flags from a blank resolve.
    'sample_resolved' => resolve_app_config([], null, []),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo PHP_EOL . 'Failed: ' . $failed . PHP_EOL;
echo 'Report: storage/reports/' . basename($report) . PHP_EOL;
exit($failed > 0 ? 1 : 0);
