# Phase 1 / 1B: Registration receipt storage migration

## Change
New transfer receipts are stored under `storage/receipts/` (outside the public webroot), not under `public/assets/uploads/receipts/`.

Admins view receipts only through the authenticated endpoint:
`GET /api/admin-receipt.php?reference=...`

Legacy directory protection:
- `public/assets/uploads/receipts/.htaccess` → `Require all denied`
- `storage/receipts/.htaccess` → `Require all denied`

## Existing records
- Do **not** delete existing publicly stored files automatically.
- `resolve_registration_receipt_path()` still resolves legacy paths such as `uploads/receipts/...` and private `storage/receipts/...`.
- Duplicate-payment uniqueness audit: `storage/reports/phase1_payment_uniqueness_report.json`.

## Migration utility (local / staging only)

```text
C:\xampp\php\php.exe scripts/migrate_legacy_receipts.php --dry-run
C:\xampp\php\php.exe scripts/migrate_legacy_receipts.php --apply
```

Behaviour:
1. Copies existing receipts from `public/assets/uploads/receipts/` to `storage/receipts/`.
2. Verifies size/hash after copy.
3. Updates `registration_payments.proof_path` only after successful verification.
4. Preserves original files (never deletes).
5. Safe to re-run; identical copies are skipped.
6. Writes a JSON report under `storage/reports/phase1b_receipt_migration_*.json`.

**Do not execute this migration on Hostinger/production without an explicit ops decision.**

## Optional later cleanup (manual / ops)
Only after migration reports are verified and the authenticated endpoint serves all needed files, remove public copies manually.
