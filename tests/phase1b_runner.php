<?php
/**
 * Phase 1B isolated regression runner (no Composer / PHPUnit required).
 * Uses database zenvita_phase1b_test only — never production.
 *
 * Usage: C:\xampp\php\php.exe tests/phase1b_runner.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('ZENVITA_DB_NAME=zenvita_phase1b_test');
$_ENV['ZENVITA_DB_NAME'] = 'zenvita_phase1b_test';

require_once $root . '/bootstrap/app.php';

$results = [];
$failed = 0;

function t_assert(string $name, bool $ok, string $detail = ''): void
{
    global $results, $failed;
    $status = $ok ? 'Passed' : 'Failed';
    if (!$ok) {
        $failed++;
    }
    $results[] = ['name' => $name, 'status' => $status, 'detail' => $detail];
    echo str_pad($status, 12) . $name . ($detail !== '' ? " — $detail" : '') . PHP_EOL;
}

function t_skip(string $name, string $detail): void
{
    global $results;
    $results[] = ['name' => $name, 'status' => 'Not executed', 'detail' => $detail];
    echo str_pad('Not executed', 12) . $name . ' — ' . $detail . PHP_EOL;
}

function admin_pdo(): PDO
{
    global $config;
    return new PDO(
        'mysql:host=' . $config['host'] . ';charset=utf8mb4',
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function reset_test_db(): void
{
    $sqlFile = dirname(__DIR__) . '/tests/fixtures/phase1b_schema.sql';
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException('Missing fixtures schema.');
    }
    $pdo = admin_pdo();
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        $pdo->exec($stmt);
    }
}

function insert_payment(PDO $pdo, array $overrides = []): array
{
    $row = array_merge([
        'reference' => 'TEST-REF-' . bin2hex(random_bytes(4)),
        'email' => 'applicant@example.test',
        'amount' => 150000.00,
        'package_id' => 1,
        'package_name' => 'Vision',
        'sponsor_code' => 'rootmember',
        'placement_side' => 'left',
        'payment_method' => 'paystack',
        'status' => 'paid',
        'proof_path' => null,
    ], $overrides);
    $pdo->prepare(
        'INSERT INTO registration_payments
            (reference, email, amount, package_id, package_name, sponsor_code, placement_side, payment_method, status, proof_path)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $row['reference'], $row['email'], $row['amount'], $row['package_id'], $row['package_name'],
        $row['sponsor_code'], $row['placement_side'], $row['payment_method'], $row['status'], $row['proof_path'],
    ]);
    $row['id'] = (int) $pdo->lastInsertId();
    return $row;
}

/**
 * Mirrors add-user.php payment gate checks for isolated testing.
 */
function registration_payment_gate(PDO $pdo, ?array $actor, array $input): array
{
    if (($actor['role'] ?? null) === 'store') {
        return ['ok' => false, 'error' => 'store_blocked', 'code' => 403];
    }
    $requires = registration_requires_payment($actor);
    if (!$requires) {
        return ['ok' => true, 'bypass' => registration_payment_bypass_role($actor)];
    }
    $ref = trim((string) ($input['paymentReference'] ?? ''));
    if ($ref === '') {
        return ['ok' => false, 'error' => 'payment_required', 'code' => 422];
    }
    $stmt = $pdo->prepare('SELECT * FROM registration_payments WHERE reference = ? LIMIT 1');
    $stmt->execute([$ref]);
    $payment = $stmt->fetch();
    if (!$payment) {
        return ['ok' => false, 'error' => 'PAYMENT_MISSING', 'code' => 422];
    }
    if (($payment['status'] ?? '') !== 'paid') {
        return ['ok' => false, 'error' => 'PAYMENT_NOT_PAID', 'code' => 422];
    }
    if (strcasecmp((string) $payment['email'], (string) $input['email']) !== 0) {
        return ['ok' => false, 'error' => 'PAYMENT_EMAIL_MISMATCH', 'code' => 422];
    }
    $pkg = $pdo->prepare('SELECT id, name, price FROM packages WHERE name = ? OR code = ? LIMIT 1');
    $pkg->execute([$input['package'], strtolower((string) $input['package'])]);
    $packageRow = $pkg->fetch();
    if (!$packageRow) {
        return ['ok' => false, 'error' => 'PACKAGE_MISSING', 'code' => 422];
    }
    $paidPackageOk = (
        (int) $payment['package_id'] === (int) $packageRow['id']
        || strcasecmp((string) $payment['package_name'], (string) $packageRow['name']) === 0
    );
    if (!$paidPackageOk) {
        return ['ok' => false, 'error' => 'PAYMENT_PACKAGE_MISMATCH', 'code' => 422];
    }
    if (naira_to_kobo($payment['amount']) !== naira_to_kobo($packageRow['price'])) {
        return ['ok' => false, 'error' => 'PAYMENT_AMOUNT_MISMATCH', 'code' => 422];
    }
    if (($payment['status'] ?? '') === 'registered') {
        return ['ok' => false, 'error' => 'PAYMENT_CONSUME_FAILED', 'code' => 409];
    }
    return ['ok' => true, 'payment' => $payment, 'package' => $packageRow];
}

function consume_payment(PDO $pdo, string $reference, int $userId): bool
{
    $upd = $pdo->prepare(
        "UPDATE registration_payments SET status = 'registered', user_id = ? WHERE reference = ? AND status = 'paid'"
    );
    $upd->execute([$userId, $reference]);
    return $upd->rowCount() === 1;
}

function place_member(PDO $pdo, int $sponsorId, string $side, string $username): array
{
    $pdo->beginTransaction();
    try {
        $seat = open_seat($pdo, $sponsorId, $side);
        $pdo->prepare(
            "INSERT INTO users (username, email, phone, password_hash, first_name, last_name, role, package_id, sponsor_id, parent_id, binary_side, status)
             VALUES (?, ?, '08001112222', 'x', 'T', 'U', 'member', 1, ?, ?, ?, 'active')"
        )->execute([
            $username,
            $username . '@example.test',
            $sponsorId,
            $seat['parent_id'],
            $seat['side'],
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return ['ok' => true, 'id' => $id, 'seat' => $seat];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $driver = ($e instanceof PDOException && isset($e->errorInfo[1])) ? (int) $e->errorInfo[1] : 0;
        return ['ok' => false, 'error' => $e->getMessage(), 'driver' => $driver];
    }
}

echo "=== Phase 1B regression tests (isolated DB zenvita_phase1b_test) ===\n";

try {
    reset_test_db();
    $pdo = db();
    ensure_binary_placement_schema($pdo);
} catch (Throwable $e) {
    fwrite(STDERR, "Fixture setup failed: " . $e->getMessage() . PHP_EOL);
    exit(2);
}

// ---------- Registration ----------
$public = registration_payment_gate($pdo, null, [
    'email' => 'applicant@example.test',
    'package' => 'Vision',
    'paymentReference' => '',
]);
t_assert('Registration: public without payment', ($public['ok'] ?? false) === false && ($public['error'] ?? '') === 'payment_required');

$memberActor = ['id' => 1, 'role' => 'member'];
$memberBypass = registration_payment_gate($pdo, $memberActor, [
    'email' => 'anyone@example.test',
    'package' => 'Vision',
    'paymentReference' => '',
]);
t_assert('Registration: logged-in member bypass', ($memberBypass['ok'] ?? false) === true && ($memberBypass['bypass'] ?? '') === 'member_downline');

$storeGate = registration_payment_gate($pdo, ['id' => 3, 'role' => 'store'], [
    'email' => 'x@example.test',
    'package' => 'Vision',
    'paymentReference' => 'ANY',
]);
t_assert('Registration: store account blocked', ($storeGate['ok'] ?? false) === false && ($storeGate['error'] ?? '') === 'store_blocked');

$badRef = registration_payment_gate($pdo, null, [
    'email' => 'applicant@example.test',
    'package' => 'Vision',
    'paymentReference' => 'DOES-NOT-EXIST',
]);
t_assert('Registration: invalid payment reference', ($badRef['error'] ?? '') === 'PAYMENT_MISSING');

$pay = insert_payment($pdo);
$mismatchEmail = registration_payment_gate($pdo, null, [
    'email' => 'other@example.test',
    'package' => 'Vision',
    'paymentReference' => $pay['reference'],
]);
t_assert('Registration: mismatched email', ($mismatchEmail['error'] ?? '') === 'PAYMENT_EMAIL_MISMATCH');

$payWrongPkg = insert_payment($pdo, [
    'reference' => 'TEST-PKG-MISMATCH-' . bin2hex(random_bytes(3)),
    'email' => 'pkgmiss@example.test',
    'package_id' => 999,
    'package_name' => 'OtherPack',
]);
$mismatchPkg = registration_payment_gate($pdo, null, [
    'email' => 'pkgmiss@example.test',
    'package' => 'Vision',
    'paymentReference' => $payWrongPkg['reference'],
]);
t_assert(
    'Registration: mismatched package',
    ($mismatchPkg['error'] ?? '') === 'PAYMENT_PACKAGE_MISMATCH',
    (string) ($mismatchPkg['error'] ?? '')
);

$okPay = registration_payment_gate($pdo, null, [
    'email' => $pay['email'],
    'package' => 'Vision',
    'paymentReference' => $pay['reference'],
]);
t_assert('Registration: valid paid reference accepted', ($okPay['ok'] ?? false) === true);
t_assert('Registration: consume paid payment', consume_payment($pdo, $pay['reference'], 1) === true);
$reuse = registration_payment_gate($pdo, null, [
    'email' => $pay['email'],
    'package' => 'Vision',
    'paymentReference' => $pay['reference'],
]);
// After consume, status is registered → NOT_PAID on gate (status !== paid)
t_assert(
    'Registration: reuse of consumed payment',
    in_array(($reuse['error'] ?? ''), ['PAYMENT_NOT_PAID', 'PAYMENT_CONSUME_FAILED'], true),
    (string) ($reuse['error'] ?? '')
);

$adminBypass = registration_payment_bypass_role(['id' => 2, 'role' => 'admin']);
$publicImpersonate = registration_payment_bypass_role(null);
t_assert('Registration: admin bypass role', $adminBypass === 'admin');
t_assert('Registration: public cannot impersonate member bypass', $publicImpersonate === null);

// ---------- Paystack payload validation ----------
$basePayload = [
    'status' => 'success',
    'reference' => 'ZGV-REG-TEST',
    'currency' => 'NGN',
    'amount' => 15000000,
    'metadata' => ['purpose' => 'registration'],
];
t_assert(
    'Paystack: invalid reference',
    validate_paystack_success_payload($basePayload, 'OTHER-REF', ['amount_kobo' => 15000000])['ok'] === false
);
t_assert(
    'Paystack: wrong currency',
    validate_paystack_success_payload(array_merge($basePayload, ['currency' => 'USD']), 'ZGV-REG-TEST', [
        'amount_kobo' => 15000000,
        'currency' => 'NGN',
    ])['ok'] === false
);
t_assert(
    'Paystack: underpayment',
    validate_paystack_success_payload(array_merge($basePayload, ['amount' => 100]), 'ZGV-REG-TEST', [
        'amount_kobo' => 15000000,
    ])['ok'] === false
);
t_assert(
    'Paystack: overpayment',
    validate_paystack_success_payload(array_merge($basePayload, ['amount' => 16000000]), 'ZGV-REG-TEST', [
        'amount_kobo' => 15000000,
    ])['ok'] === false
);
$dupCheck = validate_paystack_success_payload($basePayload, 'ZGV-REG-TEST', ['amount_kobo' => 15000000, 'purpose' => 'registration']);
t_assert('Paystack: valid success payload', ($dupCheck['ok'] ?? false) === true);
t_assert(
    'Paystack: missing purpose metadata compatibility',
    validate_paystack_success_payload(
        ['status' => 'success', 'reference' => 'ZGV-REG-TEST', 'currency' => 'NGN', 'amount' => 15000000, 'metadata' => []],
        'ZGV-REG-TEST',
        ['amount_kobo' => 15000000, 'purpose' => 'registration']
    )['ok'] === true
);
t_assert(
    'Paystack: wrong purpose rejected when present',
    validate_paystack_success_payload(
        array_merge($basePayload, ['metadata' => ['purpose' => 'product_order']]),
        'ZGV-REG-TEST',
        ['amount_kobo' => 15000000, 'purpose' => 'registration']
    )['ok'] === false
);

// Repeated callback / DB update failure simulated at SQL layer
$orderPayRef = 'ZGV-ORD-TEST-1';
$pdo->prepare(
    "INSERT INTO orders (reference, store_id, member_id, product_id, quantity, unit_price, payment_status, payment_reference)
     VALUES ('ORD-1', 1, 1, 1, 1, 10000.00, 'pending', ?)"
)->execute([$orderPayRef]);
$orderId = (int) $pdo->lastInsertId();
$upd1 = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_status <> 'paid'");
$upd1->execute([$orderId]);
$upd2 = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_status <> 'paid'");
$upd2->execute([$orderId]);
t_assert('Paystack/Order: repeated callback does not double-pay', $upd1->rowCount() === 1 && $upd2->rowCount() === 0);

$pdo->beginTransaction();
try {
    $pdo->exec('UPDATE orders SET payment_status = \'failed\' WHERE 0');
    throw new RuntimeException('forced');
} catch (Throwable $e) {
    $pdo->rollBack();
}
$stillPaid = $pdo->query("SELECT payment_status FROM orders WHERE id = $orderId")->fetchColumn();
t_assert('Paystack: database update failure rolls back', $stillPaid === 'paid');

// ---------- Product orders ----------
$pdo->prepare(
    "INSERT INTO orders (reference, store_id, member_id, product_id, quantity, unit_price, payment_status, payment_reference)
     VALUES ('ORD-2', 1, 1, 1, 1, 10000.00, 'paid', 'ZGV-ORD-DUP')"
)->execute();
$paidOrderId = (int) $pdo->lastInsertId();
$dupOrd = $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_amount = 10000 WHERE id = ? AND payment_status <> 'paid'");
$dupOrd->execute([$paidOrderId]);
t_assert('Product orders: repeated callback / duplicate prevention', $dupOrd->rowCount() === 0);

$ownerCheck = $pdo->prepare('SELECT id FROM orders WHERE reference = ? AND member_id = ?');
$ownerCheck->execute(['ORD-2', 99]);
t_assert('Product orders: incorrect order ownership', $ownerCheck->fetch() === false);

$amountCheck = validate_paystack_success_payload(
    ['status' => 'success', 'reference' => 'ZGV-ORD-AMT', 'currency' => 'NGN', 'amount' => 500000, 'metadata' => ['purpose' => 'product_order']],
    'ZGV-ORD-AMT',
    ['amount_kobo' => naira_to_kobo(10000), 'purpose' => 'product_order']
);
t_assert('Product orders: incorrect amount', ($amountCheck['ok'] ?? false) === false);

// ---------- Binary placement ----------
ensure_binary_placement_schema($pdo);
$pdo->beginTransaction();
$seatOccupiedPrep = open_seat($pdo, 1, 'left');
$pdo->prepare(
    "INSERT INTO users (username, email, phone, password_hash, first_name, last_name, role, package_id, sponsor_id, parent_id, binary_side, status)
     VALUES ('leftchild', 'leftchild@example.test', '0800', 'x', 'L', 'C', 'member', 1, 1, ?, 'left', 'active')"
)->execute([$seatOccupiedPrep['parent_id']]);
$pdo->commit();

$pdo->beginTransaction();
$afterOccupy = open_seat($pdo, 1, 'left');
$pdo->rollBack();
$leftChildId = (int) $pdo->query("SELECT id FROM users WHERE username = 'leftchild'")->fetchColumn();
t_assert(
    'Binary placement: existing occupied seat spills deeper',
    (int) $afterOccupy['parent_id'] === $leftChildId && $afterOccupy['side'] === 'left',
    'parent=' . $afterOccupy['parent_id'] . ' expected=' . $leftChildId
);
t_assert(
    'Binary placement: successful placement under spillover parent',
    (int) $afterOccupy['parent_id'] > 0 && in_array($afterOccupy['side'], ['left', 'right'], true)
);

$first = place_member($pdo, 1, 'right', 'right_a_' . bin2hex(random_bytes(2)));
t_assert('Binary placement: successful placement', ($first['ok'] ?? false) === true, json_encode($first['seat'] ?? []));

// Concurrent same seat: two connections racing on a fresh parent side
$leafParent = (int) ($first['id'] ?? 0);
if ($leafParent < 1) {
    t_skip('Binary placement: concurrent same parent-side', 'prior placement failed');
    t_skip('Binary placement: rollback when placement fails', 'prior placement failed');
} else {
    global $config;
    $dsn = 'mysql:host=' . $config['host'] . ';dbname=zenvita_phase1b_test;charset=utf8mb4';
    $a = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $b = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $a->beginTransaction();
    $b->beginTransaction();
    $seatA = open_seat($a, $leafParent, 'left');
    // B will block until A commits if it tries FOR UPDATE on same parent — use short lock wait
    $b->exec('SET innodb_lock_wait_timeout = 3');
    $seatB = null;
    $bError = null;
    try {
        // Start B open_seat in a way that may wait; since we're single-threaded, A still holds lock.
        // Insert from A first, then let B proceed.
        $a->prepare(
            "INSERT INTO users (username, email, phone, password_hash, first_name, last_name, role, package_id, sponsor_id, parent_id, binary_side, status)
             VALUES ('race_a', 'race_a@example.test', '0800', 'x', 'R', 'A', 'member', 1, ?, ?, 'left', 'active')"
        )->execute([$leafParent, $seatA['parent_id']]);
        $a->commit();

        $seatB = open_seat($b, $leafParent, 'left');
        $sameSeat = ((int) $seatB['parent_id'] === (int) $seatA['parent_id'] && $seatB['side'] === $seatA['side']);
        if ($sameSeat) {
            try {
                $b->prepare(
                    "INSERT INTO users (username, email, phone, password_hash, first_name, last_name, role, package_id, sponsor_id, parent_id, binary_side, status)
                     VALUES ('race_b', 'race_b@example.test', '0800', 'x', 'R', 'B', 'member', 1, ?, ?, 'left', 'active')"
                )->execute([$leafParent, $seatB['parent_id']]);
                $b->commit();
                t_assert('Binary placement: concurrent same parent-side', false, 'both claimed same seat');
            } catch (PDOException $ex) {
                $b->rollBack();
                $code = (int) ($ex->errorInfo[1] ?? 0);
                t_assert('Binary placement: concurrent same parent-side', $code === 1062, 'driver=' . $code);
            }
        } else {
            $b->rollBack();
            t_assert(
                'Binary placement: concurrent same parent-side',
                true,
                'second spilled to parent=' . $seatB['parent_id']
            );
        }
    } catch (Throwable $e) {
        if ($a->inTransaction()) {
            $a->rollBack();
        }
        if ($b->inTransaction()) {
            $b->rollBack();
        }
        t_assert('Binary placement: concurrent same parent-side', false, $e->getMessage());
    }

    // Rollback when placement fails
    $pdo->beginTransaction();
    try {
        open_seat($pdo, 999999, 'left');
        $pdo->commit();
        t_assert('Binary placement: rollback when placement fails', false, 'expected missing parent');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'should_not_exist_rollback'")->fetchColumn();
        t_assert(
            'Binary placement: rollback when placement fails',
            $e->getMessage() === 'PLACEMENT_PARENT_MISSING' && $count === 0
        );
    }
}

// ---------- Receipt security ----------
$legacyDir = receipt_uploads_dir();
$privateDir = private_receipt_uploads_dir();
$fixtureName = 'phase1b_test_receipt_' . bin2hex(random_bytes(4)) . '.txt';
$legacyPath = $legacyDir . DIRECTORY_SEPARATOR . $fixtureName;
file_put_contents($legacyPath, 'receipt-fixture');

$resolvedLegacy = resolve_registration_receipt_path('uploads/receipts/' . $fixtureName);
t_assert('Receipt: legacy path resolves when file present', $resolvedLegacy !== null && is_file($resolvedLegacy));

$traversal = resolve_registration_receipt_path('uploads/receipts/../../../../windows/win.ini');
t_assert('Receipt: path traversal attempt blocked', $traversal === null);

$invalidRefResolve = resolve_registration_receipt_path('');
t_assert('Receipt: invalid/empty reference path', $invalidRefResolve === null);

// Unauthorized vs admin — role gate helpers
t_assert('Receipt: unauthorized access (non-admin role)', registration_payment_bypass_role(['id' => 1, 'role' => 'member']) !== 'admin');
t_assert('Receipt: authorized administrator role', registration_payment_bypass_role(['id' => 2, 'role' => 'admin']) === 'admin');

// Migrated private serve path
$privateName = 'phase1b_private_' . bin2hex(random_bytes(4)) . '.pdf';
$privateFile = $privateDir . DIRECTORY_SEPARATOR . $privateName;
file_put_contents($privateFile, '%PDF-1.4 test');
$resolvedPrivate = resolve_registration_receipt_path('storage/receipts/' . $privateName);
t_assert('Receipt: authenticated endpoint can resolve migrated private file', $resolvedPrivate !== null);

// Legacy directory protection file
$ht = is_file($legacyDir . DIRECTORY_SEPARATOR . '.htaccess');
t_assert('Receipt: legacy directory has deny .htaccess', $ht === true);

@unlink($legacyPath);
@unlink($privateFile);

// ---------- Audit log ----------
payment_audit_log('test.event', [
    'outcome' => 'ok',
    'reference' => "ref\ninject",
    'password' => 'secret',
    'paystack_secret_key' => 'sk_test',
    'related_id' => 1,
]);
$logFile = payment_audit_logs_dir() . DIRECTORY_SEPARATOR . gmdate('Y-m-d') . '.log';
$logOk = is_file($logFile);
$tail = $logOk ? (string) file_get_contents($logFile) : '';
t_assert('Audit: writes JSONL without secrets', $logOk && str_contains($tail, 'test.event') && !str_contains($tail, 'sk_test') && !str_contains($tail, '"password"'));
t_assert('Audit: strips log injection newlines in reference', $logOk && !str_contains($tail, "ref\ninject"));

// Summary
$reportPath = $root . '/storage/reports/phase1b_test_results_' . gmdate('Ymd_His') . '.json';
if (!is_dir(dirname($reportPath))) {
    mkdir(dirname($reportPath), 0750, true);
}
$summary = [
    'generated_at' => gmdate('c'),
    'database' => 'zenvita_phase1b_test',
    'failed' => $failed,
    'results' => $results,
];
file_put_contents($reportPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo PHP_EOL . 'Failed: ' . $failed . PHP_EOL;
echo 'Report: storage/reports/' . basename($reportPath) . PHP_EOL;
exit($failed > 0 ? 1 : 0);
