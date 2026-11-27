# Phase 1: Registration receipt storage migration

## Change
New transfer receipts are stored under `storage/receipts/` (outside the public webroot), not under `public/assets/uploads/receipts/`.

Admins view receipts only through the authenticated endpoint:
`GET /api/admin-receipt.php?reference=...`

## Existing records
- Do **not** delete existing publicly stored files.
- `resolve_registration_receipt_path()` still resolves legacy paths such as `uploads/receipts/...`.
- Duplicate-payment uniqueness audit: `storage/reports/phase1_payment_uniqueness_report.json`.

## Optional later migration (manual / ops)
1. Copy files from `public/assets/uploads/receipts/` to `storage/receipts/`.
2. Update `registration_payments.proof_path` from `uploads/receipts/NAME` to `storage/receipts/NAME`.
3. Keep the old public files until all paths are verified.
4. Only then remove public copies.

No automatic delete/merge is performed by application code.
