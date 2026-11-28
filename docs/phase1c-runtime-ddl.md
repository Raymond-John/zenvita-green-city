# Phase 1C: Runtime DDL inventory and production approach

## Policy

- **Defaults (committed `config/app.php`):** `env=production`, `allow_runtime_ddl=false`, `debug=false`.
- **Local/dev:** explicit `config/app.local.php` or env vars — see `docs/phase1d-local-config.md`.
- Production-like envs **never** enable runtime DDL (hard lock in `resolve_app_config()`).
- **Binary placement unique index is never created on HTTP requests.** Registration calls `require_binary_placement_schema()` (check-only). Missing index → safe 503 + audit `schema.missing`.
- Explicit migrator: `database/migrations/2026_phase1d_schema_readiness.php`.

## Inventory

| Helper | Schema change | Callers | Production risk | Recommended migration |
|--------|---------------|---------|-----------------|------------------------|
| `require_binary_placement_schema` / `ensure_binary_placement_schema` | **Check only** — no DDL | `add-user.php`, registration processing | High if missing (registration blocked) | `database/migrations/2026_phase1b_uq_users_parent_side.sql` |
| `ensure_registration_payments_table` | CREATE TABLE + ALTER columns | paystack-*, registration-proof, admin-payments, add-user, portal/payments, payment-receipt | High if gated off and missing | `database/migrations/migrate_payments.php` |
| `ensure_order_payment_schema` | ALTER orders + unique indexes | order.php, order-pay, paystack-order-callback, orders, admin-orders, store | High | Existing order payment migrations / Phase 1 audit SQL |
| `ensure_award_rank_schema` | ALTER users.award_rank | add-user, dashboard, admin, me, admin-member | Medium | Apply award_rank column before deploy |
| `ensure_eagle_lands_schema` | ALTER wallets/withdrawals | dashboard, admin, withdrawal | Medium | `migrate_eagle_lands.php` |
| `ensure_store_sales_bonus_schema` | CREATE store_sales_bonus_awards | store-order, store | Medium | `migrate_store_sales_bonus.php` |
| `ensure_product_landing_columns` | ALTER products + CREATE landing_features | index, orders, admin-product*, portal/products | Medium | `migrate_landing_products.php`, `migrate_smtp_and_product_images.php` |
| `ensure_package_description_column` | ALTER packages.description | register, dashboard, portal, admin-package | Low | `migrate_package_description.php` |
| `ensure_smtp_settings` | ALTER settings MODIFY (gated); DML defaults | login, admin-settings, admin | Low | `migrate_smtp_and_product_images.php` |

When `allow_runtime_ddl` is false, missing schema throws `SCHEMA_*_MISSING` (fail safe) instead of silent ALTER.

## nginx note

`.htaccess` (`Require all denied`) protects Apache. For nginx, deny web access to `/storage/` (and especially `/storage/logs/`) in the server config; do not rely on `.htaccess`.
