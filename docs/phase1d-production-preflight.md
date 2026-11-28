# Phase 1D / 1F Production Preflight Runbook

Operational runbook for schema readiness migration against a **reviewed production Hostinger environment**.

This document is **source-only guidance**. It does not deploy code, connect from CI, or authorize apply by itself.

---

## 1. Purpose and scope

Bring production schema to Phase 1D readiness (payment columns/indexes, binary placement unique index, idempotency/settings tables) using:

`database/migrations/2026_phase1d_schema_readiness.php`

Out of scope: commission policy, withdrawals, awards, store-bonus rules, payment status flips, user/order data edits.

---

## 2. Local dry-run does not prove production readiness

A successful dry-run against a local or staging database **does not** establish production schema readiness.

You must repeat Stage A (and identity verification) against the **actual production database** after verifying application path and connection target.

---

## 3. Hostinger connection and application path verification

**Read-only / verification (no DDL):**

1. Confirm the Hostinger account and document root that serve this application.
2. Confirm PHP entry is `public/` (or the configured document root that points at `public/`).
3. Confirm production uses the intended `config/database.php` (or server env) — not a developer laptop DSN.
4. Confirm you are SSH’d / panel-connected to the correct site before any CLI work.

Do **not** assume local `config/app.local.php` or local MySQL credentials apply on Hostinger.

---

## 4. Production database identity verification

The migrator is **fail-closed** on identity.

| Variable | Required | Behavior |
|----------|----------|----------|
| `ZENVITA_MIGRATE_EXPECT_DB` | **Required for `--apply`**. Recommended for dry-run. | Must exactly match `SELECT DATABASE()` on the connected PDO. No silent default. Missing → apply refused; dry-run reports `not_configured` or fails if mismatched values are somehow present. |
| `ZENVITA_MIGRATE_EXPECT_HOST` | Optional | When set, must match the **configured** connection host from `config/database.php` (case-insensitive). Localhost/`127.0.0.1` is allowed when that is the expected Hostinger socket host. |

Mismatch or missing expected DB for apply → **non-zero exit, no DDL**.

**Verification sequence:** (1) startup identity on the migrator PDO, (2) Stage A read-only checks, (3) **identity re-verified on the same PDO immediately before the first Stage B DDL**. CLI flags cannot override identity.

The migrator does **not** print credentials or full connection strings.

---

## 5. Backup procedure before any migration

**Before `--apply`:**

1. Take a full MySQL dump of the production database (Hostinger backup / `mysqldump`).
2. Store the dump off-host with a timestamped name.
3. Record the dump file checksum (SHA-256).
4. Do not proceed until the dump completes without error.

Example placeholders (replace; never commit real credentials):

```bash
# READ-MODIFYING backup tool — creates a dump file; does not alter live rows
mysqldump -h <HOST> -u <USER> -p <DATABASE> > backup_<DATABASE>_YYYYMMDD_HHMMSS.sql
```

---

## 6. Database backup integrity verification

**Read-only checks on the dump:**

```bash
# Verify file non-empty and checksum
ls -la backup_<DATABASE>_YYYYMMDD_HHMMSS.sql
sha256sum backup_<DATABASE>_YYYYMMDD_HHMMSS.sql
# Optional: dry parse / restore into an isolated scratch DB (never overwrite production)
```

Confirm dump size is plausible vs known DB size. Do not delete the dump until post-migration verification succeeds.

---

## 7. Required environment configuration

On the production host for the migrator process only:

```bash
export ZENVITA_MIGRATE_EXPECT_DB='<exact_production_database_name>'
# Optional but recommended when host is known:
export ZENVITA_MIGRATE_EXPECT_HOST='<exact_configured_host>'
```

Application runtime:

- Production `ZENVITA_APP_ENV=production` (or omit; defaults are production-safe).
- Runtime DDL must remain **disabled** in production (`allow_runtime_ddl` false).
- Do not set `ZENVITA_ALLOW_BINARY_INDEX_DDL` in the web SAPI.

---

## 8. Confirmation that `app.local.php` must not be deployed

`config/app.local.php` is for **local developer overrides only** (gitignored).

**Do not** upload, rsync, or copy `app.local.php` to Hostinger. Production must rely on `config/app.php` defaults + server environment variables.

---

## 9. Read-only schema preflight

**Read-only** (default dry-run — no DDL):

```bash
cd /path/to/zenvita_green_city
export ZENVITA_MIGRATE_EXPECT_DB='<exact_production_database_name>'
export ZENVITA_MIGRATE_EXPECT_HOST='<exact_configured_host>'
php database/migrations/2026_phase1d_schema_readiness.php --dry-run
```

Inspect inventory lines (`table:`, `column:`, `index:`) and identity status.

---

## 10. Duplicate-data checks

Stage A (always before any DDL) reports **counts only** — no personal or payment identifiers:

- Duplicate `users(parent_id, binary_side)` groups → `uq_users_parent_side`
- Duplicate `order_notifications.order_id` groups → `uq_order_notifications_order`
- Duplicate non-blank `orders.payment_reference` → `uq_orders_payment_reference`
- Duplicate non-blank `orders.paystack_reference` → `uq_orders_paystack_reference`
- Duplicate non-blank `registration_payments.reference` → `uq_reg_pay_reference` (column is **`reference`**, not `payment_reference`)
- Blank / whitespace-only reference values (`TRIM(col)=''`, including `''`, spaces, tabs) on those columns — blockers; migrator never converts or deletes them
- Existing unique indexes must match required column lists (same name + wrong columns/non-unique → blocked)
- `settings` must be absent (will create `setting_key` / `setting_value`) or already app-compatible; incompatible shapes are blocked

Missing prerequisites (required tables/columns for Stage B) are reported separately from a clean check and also block DDL.

If any blocker or missing prerequisite exists: exit code `2`, **no DDL**. Resolve manually with product/ops review. Do not delete production rows from this runbook without a separate approved remediation plan.

---

## 11. Migration execution order

1. Code deploy of Phase 1D/1F sources (separate approved change) — **not** automatic from this runbook.
2. Backup + integrity check.
3. Set identity env vars.
4. Dry-run Stage A/B plan.
5. Explicit human authorization to apply.
6. Apply Stage B DDL.
7. Post-migration verification (section 16–22).

---

## 12. Exact command examples (placeholders only)

**Read-only dry-run:**

```bash
export ZENVITA_MIGRATE_EXPECT_DB='<DATABASE>'
export ZENVITA_MIGRATE_EXPECT_HOST='<HOST>'
php database/migrations/2026_phase1d_schema_readiness.php --dry-run
```

**Modifying apply (only after backup + written approval):**

```bash
export ZENVITA_MIGRATE_EXPECT_DB='<DATABASE>'
export ZENVITA_MIGRATE_EXPECT_HOST='<HOST>'
php database/migrations/2026_phase1d_schema_readiness.php --apply --i-understand-ddl
```

Never paste real passwords into shell history files or tickets.

---

## 13. How to stop when a blocker is detected

- Exit code `1`: CLI/identity/config refusal — fix env or args; no DDL ran.
- Exit code `2`: Stage A uniqueness blockers — fix data; no DDL ran.
- Exit code `3`: Stage B error after DDL started — assume partial apply; see §15.

Do not re-run `--apply` until you understand the failure. Prefer dry-run first.

---

## 14. MySQL implicit-commit behavior

MySQL **DDL statements cause an implicit commit**. Stage B is **not** a single transactional unit.

An error after the first successful `ALTER`/`CREATE` may leave earlier statements committed while later ones never ran.

---

## 15. Partial migration failure recovery

1. Capture migrator stderr/stdout, including the Stage B partial-failure report (`DONE` / `Failed step` / `PENDING`).
2. Distinguish log lines: `ADDED`/`CREATED` mean DDL committed; `OK`/`SKIP` are completed no-ops (no new DDL).
3. Treat exit code `3` as possible partial apply when any `ADDED`/`CREATED` occurred.
4. Re-run **dry-run** to inventory actual schema (do not blindly re-apply).
5. Compare against backup dump.
6. Prefer completing remaining idempotent DDL only after identity + Stage A still clear.
7. If schema is inconsistent with application expectations, restore from the verified dump (see §22) under a separate approved change window.

Do **not** assume nothing changed if earlier steps completed successfully — MySQL DDL implicitly commits.

---

## 16. Post-migration schema verification

**Read-only:**

```bash
export ZENVITA_MIGRATE_EXPECT_DB='<DATABASE>'
php database/migrations/2026_phase1d_schema_readiness.php --dry-run
```

Expect required columns/indexes `present`, Stage A uniqueness `clear`, identity `passed`.

Optionally inspect `information_schema` for the same objects (read-only).

---

## 17. Runtime DDL and debug verification

**Read-only / config check:**

- Confirm production resolved config has `allow_runtime_ddl === false` and `debug === false`.
- Confirm registration paths call `require_binary_placement_schema()` (check-only) and do not ALTER under HTTP.
- Confirm `apply_binary_placement_unique_index()` is gated: CLI + `ZENVITA_ALLOW_BINARY_INDEX_DDL=1` only.

---

## 18. Production document root verification

Confirm the public web root is the application `public/` directory (or equivalent) so `storage/`, `config/`, `database/`, and `app/` are not directly web-served.

---

## 19. Apache `.htaccess` storage protection

If Apache is used, confirm deny rules for `storage/` (and other private trees) remain in place so audit logs and backups are not publicly fetchable.

---

## 20. Nginx server-level storage restrictions

If Nginx is used, confirm `location` blocks deny HTTP access to `/storage/`, `/config/`, `/database/`, and similar paths at the server config level (not only application PHP).

---

## 21. Private receipt access verification

Confirm receipt/private file routes require authenticated authorization and do not expose raw storage paths. Spot-check one authorized and one unauthorized request (read-only functional check).

---

## 22. Backup restoration limitations and rollback strategy

- Point-in-time rollback of **schema + data** generally means restore from the pre-migration dump.
- Restoring overwrites all changes since the dump (including legitimate new orders/payments) — schedule a maintenance window.
- Dropping individual unique indexes is possible but does not undo column adds; prefer full restore for severe inconsistency.
- Application rollback (code) is independent of schema restore.

---

## 23. Explicit authorization requirements before production apply

Do **not** run `--apply` until **all** of the following are true:

1. Written approval from the owner/ops lead for this change window.
2. Verified backup + checksum recorded.
3. `ZENVITA_MIGRATE_EXPECT_DB` (and preferably host) set and dry-run identity `passed`.
4. Stage A uniqueness clear on production.
5. Operators understand MySQL DDL is non-transactional (§14).
6. Both flags present: `--apply` **and** `--i-understand-ddl`.

This runbook does **not** include automated deploy or migrate-without-approval commands.

---

## Command class summary

| Command class | Modifies DB? |
|---------------|--------------|
| Dry-run migrator | No |
| `information_schema` / SHOW INDEX inspection | No |
| `mysqldump` | No (reads; writes dump file) |
| Migrator `--apply --i-understand-ddl` | **Yes — DDL** |
| Dump restore into production | **Yes — destructive** |

---

*Phase 1F source hardening. No production access is implied by the existence of this document.*
