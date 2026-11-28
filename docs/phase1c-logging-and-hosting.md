# Phase 1C: Payment audit log protection

## Apache (typical Hostinger)

- `storage/.htaccess` → `Require all denied`
- `storage/logs/payments/.htaccess` created by `payment_audit_logs_dir()` → `Require all denied`
- Log path is under `BASE_PATH/storage/...`, outside `public/` document root when the vhost points at `public/`.

## nginx (required if used)

`.htaccess` is ignored. Example:

```nginx
location ^~ /storage/ {
    deny all;
    return 404;
}
```

Prefer keeping the document root at `.../public` so `/storage` is never mapped.

## Write safety

- `file_put_contents(..., FILE_APPEND | LOCK_EX)` for concurrent appends.
- Entire logger wrapped in try/catch — failures never interrupt payment processing.
- Newlines/tabs stripped from scalar context; secret-like keys blocked.
