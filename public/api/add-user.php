<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$actor = current_user();
$body = request_body();
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');
$first = trim((string) ($body['firstName'] ?? ''));
$last = trim((string) ($body['lastName'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$packageName = trim((string) ($body['package'] ?? 'Vision'));
$side = strtolower(trim((string) ($body['side'] ?? '')));
$sponsorName = trim((string) ($body['sponsor'] ?? ''));
$paymentReference = trim((string) ($body['paymentReference'] ?? ''));
// Public self-registration must pay first. Portal admin / logged-in member downline signup can bypass.
$requiresPayment = !$actor;

if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
    json_out(['ok' => false, 'error' => 'Use a username of at least 3 letters or numbers.'], 422);
}
if (strlen($password) < 8) {
    json_out(['ok' => false, 'error' => 'Use a password of at least 8 characters.'], 422);
}
if ($first === '') {
    json_out(['ok' => false, 'error' => 'Enter the first name.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
    json_out(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
}
if (strlen(preg_replace('/\D+/', '', $phone)) < 10 || strlen($phone) > 30) {
    json_out(['ok' => false, 'error' => 'Enter a phone number with at least 10 digits.'], 422);
}

$pdo = db();
ensure_registration_payments_table($pdo);
ensure_award_rank_schema($pdo);
$package = $pdo->prepare('SELECT id, name, price, pv, direct_bonus FROM packages WHERE name = ? OR code = ? LIMIT 1');
$package->execute([$packageName, strtolower($packageName)]);
$packageRow = $package->fetch();
if (!$packageRow) {
    json_out(['ok' => false, 'error' => 'Choose a package.'], 422);
}

$paymentRow = null;
if ($requiresPayment) {
    start_session();
    if ($paymentReference === '') {
        $paymentReference = trim((string) ($_SESSION['reg_payment_ref'] ?? ''));
    }
    if ($paymentReference === '') {
        json_out([
            'ok' => false,
            'error' => 'Complete package payment with Paystack or get an admin-approved receipt before registering.',
        ], 422);
    }
    $payStmt = $pdo->prepare('SELECT * FROM registration_payments WHERE reference = ? LIMIT 1');
    $payStmt->execute([$paymentReference]);
    $paymentRow = $payStmt->fetch();
    if (!$paymentRow || $paymentRow['status'] !== 'paid') {
        json_out([
            'ok' => false,
            'error' => 'Payment is not approved yet. Finish Paystack checkout or wait for admin receipt approval.',
        ], 422);
    }
    if (strcasecmp((string) $paymentRow['email'], $email) !== 0) {
        json_out(['ok' => false, 'error' => 'Register with the same email used for payment.'], 422);
    }
    if (
        strcasecmp((string) $paymentRow['package_name'], $packageRow['name']) !== 0
        && (int) $paymentRow['package_id'] !== (int) $packageRow['id']
    ) {
        json_out(['ok' => false, 'error' => 'Register with the same package you paid for.'], 422);
    }
}

$taken = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$taken->execute([$username]);
if ($taken->fetch()) {
    json_out(['ok' => false, 'error' => 'That username is already used.'], 422);
}
$emailTaken = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$emailTaken->execute([$email]);
if ($emailTaken->fetch()) {
    json_out(['ok' => false, 'error' => 'That email is already used.'], 422);
}

$sponsorId = null;
$parentId = null;
$binarySide = null;

if ($actor && $actor['role'] === 'member') {
    if (!in_array($side, ['left', 'right'], true)) {
        json_out(['ok' => false, 'error' => 'Choose the left or right side.'], 422);
    }
    $sponsorId = (int) $actor['id'];
    $placed = open_seat($pdo, $sponsorId, $side);
    $parentId = $placed['parent_id'];
    $binarySide = $placed['side'];
} elseif ($actor && $actor['role'] === 'admin') {
    $root = $pdo->query("SELECT id FROM users WHERE role = 'member' AND parent_id IS NULL LIMIT 1")->fetch();
    if ($sponsorName === '') {
        if ($root) {
            json_out(['ok' => false, 'error' => 'Enter the sponsor username. The first member is already stored.'], 422);
        }
    } else {
        if (!in_array($side, ['left', 'right'], true)) {
            json_out(['ok' => false, 'error' => 'Choose the left or right side.'], 422);
        }
        $sponsor = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR member_code = ?) AND role = 'member' LIMIT 1");
        $sponsor->execute([$sponsorName, $sponsorName]);
        $sponsorId = (int) $sponsor->fetchColumn();
        if (!$sponsorId) {
            json_out(['ok' => false, 'error' => 'That sponsor username or ID was not found.'], 422);
        }
        $placed = open_seat($pdo, $sponsorId, $side);
        $parentId = $placed['parent_id'];
        $binarySide = $placed['side'];
    }
} else {
    // Public registration via referral link
    if ($sponsorName === '') {
        json_out(['ok' => false, 'error' => 'A sponsor referral code or username is required.'], 422);
    }
    if (!in_array($side, ['left', 'right'], true)) {
        json_out(['ok' => false, 'error' => 'Choose the left or right team placement.'], 422);
    }
    $sponsor = $pdo->prepare("SELECT id, status FROM users WHERE (username = ? OR member_code = ?) AND role = 'member' LIMIT 1");
    $sponsor->execute([$sponsorName, $sponsorName]);
    $sponsorRow = $sponsor->fetch();
    if (!$sponsorRow) {
        json_out(['ok' => false, 'error' => 'That sponsor code or username was not found.'], 422);
    }
    if ($sponsorRow['status'] !== 'active') {
        json_out(['ok' => false, 'error' => 'That sponsor account is currently inactive.'], 422);
    }
    $sponsorId = (int) $sponsorRow['id'];
    $placed = open_seat($pdo, $sponsorId, $side);
    $parentId = $placed['parent_id'];
    $binarySide = $placed['side'];
}

// Award rank follows binary generation from root (first) to the deepest end.
$generation = 0;
if ($parentId) {
    $genStmt = $pdo->prepare('SELECT parent_id FROM users WHERE id = ?');
    $walk = (int) $parentId;
    $guard = 0;
    $generation = 1;
    while ($walk > 0 && $guard < 50) {
        $genStmt->execute([$walk]);
        $nextParent = $genStmt->fetchColumn();
        if ($nextParent === false || $nextParent === null || (int) $nextParent < 1) {
            break;
        }
        $generation++;
        $walk = (int) $nextParent;
        $guard++;
    }
}
$awardRank = award_rank_for_generation($generation);

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO users (member_code, username, email, phone, password_hash, first_name, last_name, role, package_id, award_rank, sponsor_id, parent_id, binary_side, status, personal_pv, country)
         VALUES (NULL, ?, ?, ?, ?, ?, ?, \'member\', ?, ?, ?, ?, ?, \'active\', ?, \'Nigeria\')'
    )->execute([
        $username,
        $email,
        $phone,
        password_hash($password, PASSWORD_DEFAULT),
        $first,
        $last,
        $packageRow['id'],
        $awardRank,
        $sponsorId,
        $parentId,
        $binarySide,
        (int) $packageRow['pv'],
    ]);
    $userId = (int) $pdo->lastInsertId();
    $code = 'ZGV' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE users SET member_code = ? WHERE id = ?')->execute([$code, $userId]);
    $pdo->prepare('INSERT INTO wallets (user_id, available_balance) VALUES (?, 0)')->execute([$userId]);

    if ($paymentRow) {
        $pdo->prepare(
            "UPDATE registration_payments
             SET status = 'registered', user_id = ?, updated_at = NOW()
             WHERE reference = ? AND status = 'paid'"
        )->execute([$userId, $paymentRow['reference']]);
        unset($_SESSION['reg_payment_ref']);
    }

    if ($sponsorId) {
        add_leg_pv($pdo, $parentId, $binarySide, (int) $packageRow['pv']);
        process_upline_pairing($pdo, $parentId);
        $bonus = (float) $packageRow['direct_bonus'];
        $label = trim($first . ' ' . $last);
        $pdo->prepare(
            'INSERT INTO commissions (user_id, source_label, commission_type, package_name, pv, amount, status, earned_on)
             VALUES (?, ?, \'Direct Referral\', ?, ?, ?, \'Approved\', CURDATE())'
        )->execute([$sponsorId, $label, $packageRow['name'], (int) $packageRow['pv'], $bonus]);
        $reference = 'TX-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $pdo->prepare(
            'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
             VALUES (?, ?, \'Commission\', ?, ?, \'Approved\', CURDATE())'
        )->execute([$sponsorId, $reference, 'Direct referral', $bonus]);
        $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?')
            ->execute([$bonus, $sponsorId]);
        pay_indirect($pdo, $userId, $label, $sponsorId, $packageRow);
    }

    $pdo->commit();

    if (!$actor) {
        start_session();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'member';
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['ok' => false, 'error' => 'The account could not be created.'], 500);
}

json_out([
    'ok' => true,
    'username' => $username,
    'memberCode' => $code,
    'side' => $binarySide,
]);

function open_seat(PDO $pdo, int $startId, string $side): array
{
    $current = $startId;
    $guard = 0;
    $stmt = $pdo->prepare('SELECT id FROM users WHERE parent_id = ? AND binary_side = ? LIMIT 1');
    while ($guard < 50) {
        $stmt->execute([$current, $side]);
        $child = $stmt->fetchColumn();
        if (!$child) {
            return ['parent_id' => $current, 'side' => $side];
        }
        $current = (int) $child;
        $guard++;
    }
    json_out(['ok' => false, 'error' => 'That side has no open seat.'], 422);
}

function pay_indirect(PDO $pdo, int $newUserId, string $newName, int $sponsorId, array $packageRow): void
{
    // Gen 1–6 upline of the sponsor receive indirect (unilevel) commission.
    $rates = indirect_generation_rates();
    $upline = $pdo->prepare('SELECT sponsor_id FROM users WHERE id = ?');
    $status = $pdo->prepare('SELECT status FROM users WHERE id = ?');
    $current = $sponsorId;
    foreach ($rates as $index => $rate) {
        $upline->execute([$current]);
        $ancestorId = (int) $upline->fetchColumn();
        if ($ancestorId < 1) {
            break;
        }
        $status->execute([$ancestorId]);
        $ancestorStatus = (string) $status->fetchColumn();
        $amount = round(((float) $packageRow['price']) * $rate / 100, 2);
        if ($ancestorStatus !== 'suspended' && $amount > 0) {
            $pdo->prepare(
                'INSERT INTO commissions (user_id, source_label, commission_type, package_name, pv, amount, status, earned_on)
                 VALUES (?, ?, \'Indirect Referral\', ?, ?, ?, \'Approved\', CURDATE())'
            )->execute([$ancestorId, $newName, $packageRow['name'], (int) $packageRow['pv'], $amount]);
            $reference = sprintf('ZGV-I%d-%05d-%05d', $index + 1, $newUserId, $ancestorId);
            $pdo->prepare(
                'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
                 VALUES (?, ?, \'Commission\', ?, ?, \'Approved\', CURDATE())'
            )->execute([$ancestorId, $reference, 'Indirect referral', $amount]);
            $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?')
                ->execute([$amount, $ancestorId]);
        }
        $current = $ancestorId;
    }
}

function add_leg_pv(PDO $pdo, int $parentId, string $side, int $pv): void
{
    $update = $pdo->prepare('UPDATE users SET left_pv = left_pv + ? WHERE id = ?');
    $updateRight = $pdo->prepare('UPDATE users SET right_pv = right_pv + ? WHERE id = ?');
    $parent = $pdo->prepare('SELECT parent_id, binary_side FROM users WHERE id = ?');
    $current = $parentId;
    $leg = $side;
    $guard = 0;
    while ($current && $guard < 50) {
        if ($leg === 'left') {
            $update->execute([$pv, $current]);
        } else {
            $updateRight->execute([$pv, $current]);
        }
        $parent->execute([$current]);
        $row = $parent->fetch();
        if (!$row || empty($row['parent_id'])) {
            break;
        }
        $leg = $row['binary_side'] ?: $leg;
        $current = (int) $row['parent_id'];
        $guard++;
    }
}
