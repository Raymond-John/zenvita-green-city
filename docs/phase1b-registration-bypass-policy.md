# Phase 1B: Registration payment bypass policy

**Status: awaiting management confirmation. Do not expand roles without approval.**

## Who can register without a paid `registration_payments` row?

| Caller | Payment required? | Notes |
|--------|-------------------|--------|
| Public (not logged in) | **Yes** | Must supply an approved (`paid`) payment reference matching email + package. |
| Role `store` | **Yes** (and blocked) | Store accounts receive HTTP 403; they cannot register members or use any bypass. |
| Role `member` | **No** (member downline workflow) | Authenticated member may create a downline under themselves only (`sponsor_id` = actor). Existing dashboard/member registration workflow — **unchanged**. |
| Role `admin` | **No** (admin create) | Administrative portal create may bypass payment. Sponsor/side rules still apply when a root already exists. |

## Confirmed controls (code)

- `registration_payment_bypass_role()` returns only `admin` or `member_downline`.
- `registration_requires_payment()` is true for public, store, and any other role.
- Store is explicitly denied before payment checks.
- Public applicants cannot impersonate a member: bypass requires a real session via `current_user()`; client-supplied role fields are not trusted.
- Ordinary members do not receive admin bypass; they only get the member-downline path.
- Member downline registration remains restricted to placing under the authenticated member (`$sponsorId = (int) $actor['id']`).

## Flag for management

Confirm whether:

1. Member downline registration should remain payment-free permanently.
2. Any additional roles (e.g. support staff) should ever bypass payment.
3. Admin bypass should require an optional audit note on every create.
