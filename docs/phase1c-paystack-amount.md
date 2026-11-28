# Phase 1C: Paystack verification amount and purpose

## Amount
`validate_paystack_success_payload()` / `verify_paystack_reference()` **require** `amount_kobo` in `$expects`.

- Missing, zero, or non-positive expected amount → verification fails.
- Comparison is exact integer kobo (`!==`), never floating-point naira.

Callers (`paystack-callback.php`, `paystack-status.php`, `paystack-order-callback.php`) pass `naira_to_kobo(...)` from the internal DB row.

## Purpose metadata (compatibility limitation)
When `$expects['purpose']` is set:

- Empty/missing Paystack `metadata.purpose` is **tolerated** (legacy transactions bound by merchant reference).
- A non-empty wrong purpose is **rejected**.

Management may later require non-empty purpose after a cutover date.
