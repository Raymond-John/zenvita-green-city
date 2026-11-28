<?php

/**
 * Application configuration — PRODUCTION-SAFE DEFAULTS.
 *
 * Do not put local/dev overrides here. Use either:
 *   - config/app.local.php (see app.local.php.example), or
 *   - environment variables ZENVITA_APP_ENV, ZENVITA_APP_DEBUG, ZENVITA_ALLOW_RUNTIME_DDL
 *
 * Production never enables runtime DDL, even if a mistaken env var requests it
 * (enforced in resolve_app_config() / app_allows_runtime_ddl()).
 */
return [
    'name' => 'Zenvita Green City',
    'env' => 'production',
    'debug' => false,
    'url' => '',
    'allow_runtime_ddl' => false,
];
