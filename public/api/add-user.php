<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$actor = current_user();
$body = request_body();
$paymentReference = trim((string) ($body['paymentReference'] ?? ''));

/*
 * Authorization (see docs/phase1b-registration-bypass-policy.md):
 * - Public / store callers always require an approved payment.
 * - Authenticated members may create downlines under themselves without payment.
 * - Admin portal create may bypass payment.
 * - Store accounts never bypass.
 */
$actorRole = $actor ? (string) ($actor['role'] ?? '') : '';
$requiresPayment = registration_requires_payment($actor);

if ($actorRole === 'store') {
    payment_audit_log('registration.denied', [
        'outcome' => 'store_blocked',
        'role' => 'store',
    ]);
    json_out(['ok' => false, 'error' => 'Store accounts cannot register members directly. Use a referral link so the new member pays first.'], 403);
}

$pdo = db();
ensure_registration_payments_table($pdo);
ensure_award_rank_schema($pdo);

try {
    require_binary_placement_schema($pdo);
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'SCHEMA_BINARY_PLACEMENT_MISSING') {
        json_out([
            'ok' => false,
            'error' => 'Registration is temporarily unavailable. Please try again later.',
        ], 503);
    }
    throw $e;
}

$paymentReferenceToUse = $paymentReference;
if ($requiresPayment) {
    start_session();
    if ($paymentReferenceToUse === '') {
        $paymentReferenceToUse = trim((string) ($_SESSION['reg_payment_ref'] ?? ''));
    }
    if ($paymentReferenceToUse === '') {
        payment_audit_log('registration.denied', [
            'outcome' => 'payment_required',
            'role' => $actorRole !== '' ? $actorRole : 'public',
        ]);
        json_out([
            'ok' => false,
            'error' => 'Complete package payment with Paystack or get an admin-approved receipt before registering.',
        ], 422);
    }
}

$input = [
    'username' => $body['username'] ?? '',
    'password' => $body['password'] ?? '',
    'firstName' => $body['firstName'] ?? '',
    'lastName' => $body['lastName'] ?? '',
    'email' => $body['email'] ?? '',
    'phone' => $body['phone'] ?? '',
    'package' => $body['package'] ?? 'Vision',
    'side' => $body['side'] ?? '',
    'sponsor' => $body['sponsor'] ?? '',
    'paymentReference' => $paymentReferenceToUse,
];

try {
    $result = process_member_registration($pdo, $actor, $input);
    if ($paymentReferenceToUse !== '') {
        unset($_SESSION['reg_payment_ref']);
    }
    if (!$actor) {
        start_session();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $result['userId'];
        $_SESSION['role'] = 'member';
    }
    json_out([
        'ok' => true,
        'username' => $result['username'],
        'memberCode' => $result['memberCode'],
        'side' => $result['side'],
    ]);
} catch (Throwable $error) {
    $sqlState = ($error instanceof PDOException) ? (string) $error->getCode() : '';
    $driverCode = ($error instanceof PDOException && isset($error->errorInfo[1])) ? (int) $error->errorInfo[1] : 0;
    $dupKey = ($error instanceof PDOException && $driverCode === 1062) ? mysql_duplicate_key_name($error) : '';

    payment_audit_log('registration.failed', [
        'outcome' => 'rolled_back',
        'reference' => $paymentReferenceToUse !== '' ? $paymentReferenceToUse : null,
        'error_code' => $error instanceof RuntimeException ? $error->getMessage() : 'exception',
        'sql_driver' => $driverCode > 0 ? $driverCode : null,
        'duplicate_key' => $dupKey !== '' ? $dupKey : null,
    ]);

    if ($driverCode === 1213 || $sqlState === '40001') {
        json_out(['ok' => false, 'error' => 'Registration is busy. Please try again.'], 409);
    }
    if ($driverCode === 1062) {
        if ($dupKey === 'uq_users_parent_side' || str_contains($dupKey, 'parent_side')) {
            json_out(['ok' => false, 'error' => 'That placement seat was just taken. Please try again.'], 409);
        }
        if ($dupKey === 'username' || str_contains($dupKey, 'username')) {
            json_out(['ok' => false, 'error' => 'That username is already used.'], 422);
        }
        if ($dupKey === 'email' || str_contains($dupKey, 'email')) {
            json_out(['ok' => false, 'error' => 'That email is already used.'], 422);
        }
        if ($dupKey === 'member_code' || str_contains($dupKey, 'member_code')) {
            json_out(['ok' => false, 'error' => 'Could not assign a member code. Please try again.'], 409);
        }
        json_out(['ok' => false, 'error' => 'That account detail is already in use.'], 409);
    }

    $map = [
        'STORE_BLOCKED' => ['Store accounts cannot register members directly. Use a referral link so the new member pays first.', 403],
        'USERNAME_INVALID' => ['Use a username of at least 3 letters or numbers.', 422],
        'PASSWORD_INVALID' => ['Use a password of at least 8 characters.', 422],
        'FIRST_NAME_REQUIRED' => ['Enter the first name.', 422],
        'EMAIL_INVALID' => ['Enter a valid email address.', 422],
        'PHONE_INVALID' => ['Enter a phone number with at least 10 digits.', 422],
        'PACKAGE_MISSING' => ['Choose a package.', 422],
        'USERNAME_TAKEN' => ['That username is already used.', 422],
        'EMAIL_TAKEN' => ['That email is already used.', 422],
        'PAYMENT_REQUIRED' => ['Complete package payment with Paystack or get an admin-approved receipt before registering.', 422],
        'PAYMENT_MISSING' => ['Payment record not found.', 422],
        'PAYMENT_NOT_PAID' => ['Payment is not approved yet. Finish Paystack checkout or wait for admin receipt approval.', 422],
        'PAYMENT_EMAIL_MISMATCH' => ['Register with the same email used for payment.', 422],
        'PAYMENT_PACKAGE_MISMATCH' => ['Register with the same package you paid for.', 422],
        'PAYMENT_PACKAGE_INCOMPLETE' => ['Payment package details are incomplete. Contact support or pay again.', 422],
        'PAYMENT_AMOUNT_MISMATCH' => ['Payment amount does not match the selected package price.', 422],
        'PAYMENT_CONSUME_FAILED' => ['This payment was already used or could not be claimed.', 409],
        'SIDE_REQUIRED' => ['Choose the left or right team placement.', 422],
        'SPONSOR_REQUIRED' => ['A sponsor referral code or username is required.', 422],
        'SPONSOR_REQUIRED_ROOT_EXISTS' => ['Enter the sponsor username. The first member is already stored.', 422],
        'SPONSOR_NOT_FOUND' => ['That sponsor code or username was not found.', 422],
        'SPONSOR_INACTIVE' => ['That sponsor account is currently inactive.', 422],
        'PLACEMENT_PARENT_MISSING' => ['Placement parent was not found.', 422],
        'PLACEMENT_REQUIRES_TRANSACTION' => ['The account could not be created.', 500],
        'SCHEMA_BINARY_PLACEMENT_MISSING' => ['Registration is temporarily unavailable. Please try again later.', 503],
    ];
    $key = $error->getMessage();
    if (isset($map[$key])) {
        json_out(['ok' => false, 'error' => $map[$key][0]], $map[$key][1]);
    }
    if ($error instanceof RuntimeException && str_contains($error->getMessage(), 'open seat')) {
        json_out(['ok' => false, 'error' => $error->getMessage()], 422);
    }
    json_out(['ok' => false, 'error' => 'The account could not be created.'], 500);
}
