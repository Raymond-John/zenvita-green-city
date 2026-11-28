# Phase 1D: Database migration inventory

**Do not run against Hostinger/production without backup + reviewed dry-run.**

Unified CLI migrator: `database/migrations/2026_phase1d_schema_readiness.php`  
(`--dry-run` default; `--apply --i-understand-ddl` to execute)

**Phase 1F/1H/1J:** Stage A (prerequisites + uniqueness + inventory) always completes before any Stage B DDL.  
Identity: `ZENVITA_MIGRATE_EXPECT_DB` (required for apply) and optional `ZENVITA_MIGRATE_EXPECT_HOST`; re-verified on the same PDO immediately before Stage B DDL.  
**Registration unique index:** `uq_reg_pay_reference` on `registration_payments.reference` (not `payment_reference`).  
**Settings CREATE:** `setting_key VARCHAR(100) PRIMARY KEY`, `setting_value TEXT NOT NULL` (matches app). Incompatible existing `settings` (e.g. `key`/`value`) is a Stage A blocker — never dropped/recreated.  
**Index verification:** existing unique indexes must match name, UNIQUE flag, and ordered columns; same-name wrong definition is BLOCKED.  
**Blank/whitespace policy:** non-NULL `TRIM(col)=''` values on unique reference columns are Stage A blockers (not converted/deleted).  
**Logs:** `ADDED`/`CREATED` = DDL committed; `OK`/`SKIP` = completed no-op.  
Production preflight: `docs/phase1d-production-preflight.md`. Failure-path tests: `tests/phase1j_runner.php` (DB `zenvita_phase1j_test`).

MySQL DDL (`ALTER`/`CREATE`) **implicitly commits**. There is no safe automatic rollback of those statements. An error mid-Stage B may leave earlier DDL committed. Partial failures report completed / failed / pending steps.

| Item | Helper | Tables / objects | Dedicated migration | Rerun safe? | Pre-checks | Data conflict risk | Backup? | Rollback |
|------|--------|------------------|---------------------|-------------|------------|--------------------|---------|----------|
| Binary placement unique | `require_binary_placement_schema` (check-only) | `users.uq_users_parent_side (parent_id,binary_side)` | `2026_phase1b_uq_users_parent_side.sql` + Phase 1D readiness | Yes if index exists (skipped) | `dup_groups=0` | **High** if duplicate seats | **Required** | `DROP INDEX` (allows future dups) |
| Registration payments table | `ensure_registration_payments_table` | `registration_payments` | `migrate_payments.php` + Phase 1D readiness | Yes (`IF NOT EXISTS` / inspect) | Table missing | Low | Recommended | Drop table (data loss) |
| Reg payment columns | same | `payment_method`, `proof_path`, `notes`, `admin_note`, `reviewed_*`, `sponsor_code`, `placement_side`, status `rejected` | Phase 1D readiness (fills gap vs migrate_payments) | Yes (column exists skip) | Column inventory | Low | Recommended | Drop columns carefully |
| Order payment columns | `ensure_order_payment_schema` | `orders.payment_status`, `payment_reference`, `paystack_reference`, `paid_amount`, `paid_at` | Phase 1D readiness | Yes | Table/column exists | Low; **no unpaid→paid backfill** | Recommended | Drop columns |
| Registration payment unique | Phase 1D/1H readiness | `uq_reg_pay_reference` on **`registration_payments.reference`** | Phase 1D readiness | Yes if present | Duplicate non-empty `reference` + multiple `''` | Medium | Recommended | Drop index |
| Order payment unique indexes | same | `uq_orders_payment_reference`, `uq_orders_paystack_reference` | Phase 1D readiness | Yes if present | Duplicate non-empty refs + multiple `''` | Medium | Recommended | Drop indexes |
| Order notification unique | same | `uq_order_notifications_order` | Phase 1D readiness | Blocked if dups | Duplicate `order_id` count | Medium | Recommended | Drop index |
| Award rank | `ensure_award_rank_schema` | `users.award_rank` | Phase 1D readiness (was helper-only) | Yes | Column exists | Low | Recommended | Drop column |
| Eagle Lands wallet/withdrawal | `ensure_eagle_lands_schema` | `wallets.eagle_lands_balance`, `withdrawals.reserved_amount` | `migrate_eagle_lands.php` + Phase 1D | Yes | Column exists | Low (defaults 0) | Recommended | Drop columns |
| Store sales bonus | `ensure_store_sales_bonus_schema` | `store_sales_bonus_awards` | `migrate_store_sales_bonus.php` + Phase 1D | Yes | Table exists | Low | Recommended | Drop table |
| Product landing | `ensure_product_landing_columns` | `products.features/image_path/sort_order/show_on_landing`, `landing_features` | `migrate_landing_products.php` / SMTP+images + Phase 1D | Yes | Column/table exists | Low | Recommended | Drop cols/table |
| Package description | `ensure_package_description_column` | `packages.description` | `migrate_package_description.php` + Phase 1D | Yes | Column exists | Low | Recommended | Drop column |
| Settings TEXT | `ensure_smtp_settings` | `settings.setting_value` MODIFY TEXT | `migrate_smtp_and_product_images.php` + Phase 1D | Mostly | Current type | Low | Recommended | Re-MODIFY (lossy if truncated earlier) |

## Operations that cannot be auto-rolled back

All `ALTER TABLE` / `CREATE TABLE` statements (MySQL implicit commit).

## Not performed by Phase 1D readiness

- Changing member balances or commissions
- Rewriting historical payment/order statuses
- Converting unpaid orders to paid
- Deleting legacy receipt files
