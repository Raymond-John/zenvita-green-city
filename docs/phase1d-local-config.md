# Phase 1D: Local development configuration

## Production-safe defaults

`config/app.php` ships with:

- `env` = `production`
- `debug` = `false`
- `allow_runtime_ddl` = `false`

## Local XAMPP setup (required for DDL helpers / debug)

1. Copy `config/app.local.php.example` → `config/app.local.php`
2. Keep values:

```php
return [
    'env' => 'local',
    'debug' => true,
    'url' => 'http://localhost/Zenita%20backend/zenvita_green_city',
    'allow_runtime_ddl' => true,
];
```

`config/app.local.php` is gitignored and must never be deployed to Hostinger.

## Environment variables (optional / CI)

| Variable | Purpose |
|----------|---------|
| `ZENVITA_APP_ENV` | `local` \| `testing` \| `production` \| `staging` |
| `ZENVITA_APP_DEBUG` | `true` / `false` (ignored/forced false in production-like envs) |
| `ZENVITA_ALLOW_RUNTIME_DDL` | `true` / `false` (**forced false** when env is production/staging/unknown) |
| `ZENVITA_DB_NAME` | Override DB name (tests use `zenvita_phase1b_test`) |

## Hard locks

When resolved `env` is production-like (`production`, `staging`, or unknown → production):

- `allow_runtime_ddl` is always `false`
- `debug` is always `false`

Even if `ZENVITA_ALLOW_RUNTIME_DDL=true` is set by mistake.
