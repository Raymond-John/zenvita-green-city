<?php
/**
 * Phase 1N regression runner (isolated DB zenvita_phase1b_test).
 * Usage: C:\xampp\php\php.exe tests/phase1n_runner.php
 *
 * Covers: six-gen commissions, registration payment binding, Paystack payload
 * validation, transfer-receipt access, order payment idempotency helpers,
 * migration dry-run (no DDL).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('ZENVITA_DB_NAME=zenvita_phase1b_test');
$_ENV['ZENVITA_DB_NAME'] = 'zenvita_phase1b_test';
putenv('ZENVITA_APP_ENV=local');
$_ENV['ZENVITA_APP_ENV'] = 'local';

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
    $sql = file_get_contents(dirname(__DIR__) . '/tests/fixtures/phase1b_schema.sql');
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

function seed_sponsor_chain(PDO $pdo, int $depth): array
{
    // ids[0] = root (existing id 1). Create depth additional members under sponsor chain.
    $ids = [1];
    $parentId = 1;
    for ($i = 1; $i <= $depth; $i++) {
        $username = 'gen' . $i . 'user';
        $email = 'gen' . $i . '@example.test';
        $side = ($i % 2 === 0) ? 'right' : 'left';
        $pdo->prepare(
            "INSERT INTO users (member_code, username, email, phone, password_hash, first_name, last_name, role, package_id, award_rank, sponsor_id, parent_id, binary_side, status, personal_pv)
             VALUES (?, ?, ?, '0801111000{$i}', 'x', 'Gen', ?, 'member', 1, 'Member', ?, ?, ?, 'active', 150)"
        )->execute([
            'ZGVG' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            $username,
            $email,
            (string) $i,
            $ids[$i - 1],
            $parentId,
            $side,
        ]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO wallets (user_id, available_balance) VALUES (?, 0)')->execute([$newId]);
        $ids[] = $newId;
        $parentId = $newId;
    }
    return $ids;
}

echo "=== Phase 1N regression tests ===\n";

try {
    reset_test_db();
    $pdo = db();
    require_binary_placement_schema($pdo);
} catch (Throwable $e) {
    fwrite(STDERR, "Fixture setup failed: " . $e->getMessage() . PHP_EOL);
    exit(2);
}

// ----- Rates authoritative -----
$rates = indirect_generation_rates();
t_assert('Commissions: six generation rates defined', count($rates) === 6, json_encode($rates));
t_assert(
    'Commissions: exact rate table',
    $rates === [2.0, 1.0, 1.0, 0.5, 0.5, 0.5] || $rates === [2, 1, 1, 0.5, 0.5, 0.5],
    json_encode($rates)
);

// ----- Six-generation payout -----
$chain = seed_sponsor_chain($pdo, 6);
// chain: [root=1, g1, g2, g3, g4, g5, g6] — new member sponsored by g6 pays indirect to g5..root (6 gens)
$packageRow = ['name' => 'Vision', 'price' => 150000.00, 'pv' => 150, 'direct_bonus' => 45000];
$newUserId = 900001;
$sponsorId = $chain[6]; // deepest
$pdo->beginTransaction();
try {
    registration_pay_indirect($pdo, $newUserId, 'New Member', $sponsorId, $packageRow);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    t_assert('Commissions: six-gen payout runs', false, $e->getMessage());
}

$expectedAmounts = [
    $chain[5] => round(150000 * 2 / 100, 2),   // gen1 above sponsor = g5
    $chain[4] => round(150000 * 1 / 100, 2),
    $chain[3] => round(150000 * 1 / 100, 2),
    $chain[2] => round(150000 * 0.5 / 100, 2),
    $chain[1] => round(150000 * 0.5 / 100, 2),
    $chain[0] => round(150000 * 0.5 / 100, 2), // gen6 = root
];

$indirectRows = $pdo->query(
    "SELECT user_id, amount FROM commissions WHERE commission_type = 'Indirect Referral' AND source_label = 'New Member' ORDER BY id"
)->fetchAll();
t_assert('Commissions: six indirect rows created', count($indirectRows) === 6, 'count=' . count($indirectRows));

foreach ($expectedAmounts as $uid => $amt) {
    $got = null;
    foreach ($indirectRows as $row) {
        if ((int) $row['user_id'] === (int) $uid) {
            $got = (float) $row['amount'];
            break;
        }
    }
    t_assert(
        'Commissions: amount for user ' . $uid,
        $got !== null && abs($got - $amt) < 0.001,
        'expected=' . $amt . ' got=' . var_export($got, true)
    );
    $bal = (float) $pdo->query('SELECT available_balance FROM wallets WHERE user_id = ' . (int) $uid)->fetchColumn();
    t_assert(
        'Commissions: wallet credited user ' . $uid,
        abs($bal - $amt) < 0.001,
        'balance=' . $bal
    );
}

$txCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM transactions WHERE description LIKE 'Indirect referral gen %' AND user_id IN (" . implode(',', array_map('intval', array_keys($expectedAmounts))) . ')'
)->fetchColumn();
t_assert('Commissions: six transaction rows', $txCount === 6, 'count=' . $txCount);

// ----- Suspended ancestor skipped, walk continues -----
reset_test_db();
$pdo = db();
$chain2 = seed_sponsor_chain($pdo, 3);
$pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$chain2[2]]);
$pdo->beginTransaction();
registration_pay_indirect($pdo, 900002, 'Susp Test', $chain2[3], $packageRow);
$pdo->commit();
$paidSuspended = (int) $pdo->query(
    "SELECT COUNT(*) FROM commissions WHERE user_id = " . (int) $chain2[2] . " AND commission_type = 'Indirect Referral'"
)->fetchColumn();
$paidAbove = (int) $pdo->query(
    "SELECT COUNT(*) FROM commissions WHERE user_id = " . (int) $chain2[1] . " AND commission_type = 'Indirect Referral'"
)->fetchColumn();
t_assert('Commissions: suspended ancestor unpaid', $paidSuspended === 0);
t_assert('Commissions: walk continues past suspended', $paidAbove === 1);

// ----- Missing upline stops early -----
reset_test_db();
$pdo = db();
$pdo->beginTransaction();
registration_pay_indirect($pdo, 900003, 'Root Child', 1, $packageRow); // root has no sponsor
$pdo->commit();
$none = (int) $pdo->query(
    "SELECT COUNT(*) FROM commissions WHERE source_label = 'Root Child' AND commission_type = 'Indirect Referral'"
)->fetchColumn();
t_assert('Commissions: missing upline pays nobody', $none === 0);

// ----- Phase 1O: wallet integrity (direct + indirect) -----
reset_test_db();
$pdo = db();
$chainDirect = seed_sponsor_chain($pdo, 1); // [root=1, g1]
$sponsorDirect = (int) $chainDirect[1];
$parentDirect = (int) $chainDirect[1];
$beforeDirectBal = (float) $pdo->query('SELECT available_balance FROM wallets WHERE user_id = ' . $sponsorDirect)->fetchColumn();
$pdo->beginTransaction();
try {
    registration_apply_sponsor_rewards(
        $pdo,
        900010,
        'Direct',
        'Credit',
        $sponsorDirect,
        $parentDirect,
        'left',
        $packageRow
    );
    $pdo->commit();
    $afterDirectBal = (float) $pdo->query('SELECT available_balance FROM wallets WHERE user_id = ' . $sponsorDirect)->fetchColumn();
    $directComm = (int) $pdo->query(
        "SELECT COUNT(*) FROM commissions WHERE user_id = {$sponsorDirect} AND commission_type = 'Direct Referral' AND source_label = 'Direct Credit'"
    )->fetchColumn();
    $directTx = (int) $pdo->query(
        "SELECT COUNT(*) FROM transactions WHERE user_id = {$sponsorDirect} AND description = 'Direct referral'"
    )->fetchColumn();
    t_assert(
        'Wallet integrity: direct commission credits sponsor wallet',
        $directComm === 1
            && $directTx === 1
            && abs($afterDirectBal - ($beforeDirectBal + (float) $packageRow['direct_bonus'])) < 0.001,
        'bal_before=' . $beforeDirectBal . ' bal_after=' . $afterDirectBal
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    t_assert('Wallet integrity: direct commission credits sponsor wallet', false, $e->getMessage());
}

// Missing direct sponsor wallet → fail; rollback leaves no orphan commission/tx
reset_test_db();
$pdo = db();
$chainMissingDirect = seed_sponsor_chain($pdo, 1);
$sponsorMissing = (int) $chainMissingDirect[1];
$parentMissing = (int) $chainMissingDirect[1];
$pdo->prepare('DELETE FROM wallets WHERE user_id = ?')->execute([$sponsorMissing]);
$commBefore = (int) $pdo->query('SELECT COUNT(*) FROM commissions')->fetchColumn();
$txBefore = (int) $pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
$pdo->beginTransaction();
$directMissingThrew = false;
$directMissingCode = '';
try {
    registration_apply_sponsor_rewards(
        $pdo,
        900011,
        'Missing',
        'Wallet',
        $sponsorMissing,
        $parentMissing,
        'left',
        $packageRow
    );
    $pdo->commit();
} catch (RuntimeException $e) {
    $directMissingThrew = true;
    $directMissingCode = $e->getMessage();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $directMissingCode = $e->getMessage();
}
t_assert(
    'Wallet integrity: missing direct sponsor wallet fails',
    $directMissingThrew && $directMissingCode === 'SPONSOR_WALLET_MISSING',
    $directMissingCode
);
$commAfter = (int) $pdo->query('SELECT COUNT(*) FROM commissions')->fetchColumn();
$txAfter = (int) $pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
t_assert(
    'Wallet integrity: missing direct wallet leaves no orphan commission/tx',
    $commAfter === $commBefore && $txAfter === $txBefore,
    "comm {$commBefore}->{$commAfter} tx {$txBefore}->{$txAfter}"
);

// Missing indirect ancestor wallet → fail before commission/tx for that ancestor; rollback cleans prior writes
reset_test_db();
$pdo = db();
$chainMissingInd = seed_sponsor_chain($pdo, 3); // root, g1, g2, g3
$missingAncestor = (int) $chainMissingInd[2]; // first paid gen above g3
$pdo->prepare('DELETE FROM wallets WHERE user_id = ?')->execute([$missingAncestor]);
$commBeforeInd = (int) $pdo->query('SELECT COUNT(*) FROM commissions')->fetchColumn();
$txBeforeInd = (int) $pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
$pdo->beginTransaction();
$indMissingThrew = false;
$indMissingCode = '';
try {
    registration_pay_indirect($pdo, 900012, 'Ind Missing', (int) $chainMissingInd[3], $packageRow);
    $pdo->commit();
} catch (RuntimeException $e) {
    $indMissingThrew = true;
    $indMissingCode = $e->getMessage();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $indMissingCode = $e->getMessage();
}
t_assert(
    'Wallet integrity: missing indirect ancestor wallet fails',
    $indMissingThrew && $indMissingCode === 'INDIRECT_WALLET_MISSING',
    $indMissingCode
);
$commAfterInd = (int) $pdo->query('SELECT COUNT(*) FROM commissions')->fetchColumn();
$txAfterInd = (int) $pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
t_assert(
    'Wallet integrity: missing indirect wallet leaves no orphan commission/tx',
    $commAfterInd === $commBeforeInd && $txAfterInd === $txBeforeInd,
    "comm {$commBeforeInd}->{$commAfterInd} tx {$txBeforeInd}->{$txAfterInd}"
);

// Duplicate ancestor in sponsor cycle is not paid twice
reset_test_db();
$pdo = db();
$cycle = seed_sponsor_chain($pdo, 2); // [1, g1, g2] with sponsors: g1->1, g2->g1
// Create a cycle g1 <-> g2 so walk from g2 hits g1, then g2, then g1 again.
$pdo->prepare('UPDATE users SET sponsor_id = ? WHERE id = ?')->execute([(int) $cycle[2], (int) $cycle[1]]);
$pdo->beginTransaction();
registration_pay_indirect($pdo, 900013, 'Dup Cycle', (int) $cycle[2], $packageRow);
$pdo->commit();
$dupRows = $pdo->query(
    "SELECT user_id, COUNT(*) AS c FROM commissions
     WHERE commission_type = 'Indirect Referral' AND source_label = 'Dup Cycle'
     GROUP BY user_id"
)->fetchAll();
$dupOk = true;
foreach ($dupRows as $row) {
    if ((int) $row['c'] > 1) {
        $dupOk = false;
        break;
    }
}
t_assert('Wallet integrity: duplicate ancestors not paid twice', $dupOk && count($dupRows) >= 1, json_encode($dupRows));

// ----- Registration payment binding / tamper -----
reset_test_db();
$pdo = db();
$pay = insert_payment($pdo, [
    'email' => 'tamper@example.test',
    'sponsor_code' => 'rootmember',
    'placement_side' => 'left',
]);
try {
    process_member_registration($pdo, null, [
        'username' => 'tamperuser',
        'password' => 'password1',
        'firstName' => 'T',
        'lastName' => 'U',
        'email' => 'tamper@example.test',
        'phone' => '08012345678',
        'package' => 'Vision',
        'side' => 'right',
        'sponsor' => 'rootmember',
        'paymentReference' => $pay['reference'],
    ]);
    t_assert('Registration: side tamper rejected', false);
} catch (RuntimeException $e) {
    t_assert('Registration: side tamper rejected', $e->getMessage() === 'PAYMENT_SIDE_TAMPER', $e->getMessage());
}

$pay2 = insert_payment($pdo, [
    'reference' => 'TEST-SP-' . bin2hex(random_bytes(3)),
    'email' => 'spontamper@example.test',
    'sponsor_code' => 'rootmember',
    'placement_side' => 'left',
]);
try {
    process_member_registration($pdo, null, [
        'username' => 'spontamper',
        'password' => 'password1',
        'firstName' => 'S',
        'lastName' => 'T',
        'email' => 'spontamper@example.test',
        'phone' => '08012345679',
        'package' => 'Vision',
        'side' => 'left',
        'sponsor' => 'nosuchsponsor',
        'paymentReference' => $pay2['reference'],
    ]);
    t_assert('Registration: sponsor tamper rejected', false);
} catch (RuntimeException $e) {
    t_assert('Registration: sponsor tamper rejected', $e->getMessage() === 'PAYMENT_SPONSOR_TAMPER', $e->getMessage());
}

$pay3 = insert_payment($pdo, [
    'reference' => 'TEST-INC-' . bin2hex(random_bytes(3)),
    'email' => 'incomplete@example.test',
    'sponsor_code' => '',
    'placement_side' => 'left',
]);
try {
    process_member_registration($pdo, null, [
        'username' => 'incompleteu',
        'password' => 'password1',
        'firstName' => 'I',
        'lastName' => 'C',
        'email' => 'incomplete@example.test',
        'phone' => '08012345680',
        'package' => 'Vision',
        'side' => 'left',
        'sponsor' => 'rootmember',
        'paymentReference' => $pay3['reference'],
    ]);
    t_assert('Registration: incomplete placement rejected', false);
} catch (RuntimeException $e) {
    t_assert('Registration: incomplete placement rejected', $e->getMessage() === 'PAYMENT_PLACEMENT_INCOMPLETE', $e->getMessage());
}

$payOk = insert_payment($pdo, [
    'reference' => 'TEST-OKN-' . bin2hex(random_bytes(3)),
    'email' => 'okn@example.test',
    'sponsor_code' => 'rootmember',
    'placement_side' => 'right',
]);
try {
    $created = process_member_registration($pdo, null, [
        'username' => 'oknuser',
        'password' => 'password1',
        'firstName' => 'Ok',
        'lastName' => 'N',
        'email' => 'okn@example.test',
        'phone' => '08012345681',
        'package' => 'Vision',
        'side' => 'right',
        'sponsor' => 'rootmember',
        'paymentReference' => $payOk['reference'],
    ]);
    t_assert('Registration: valid payment succeeds', !empty($created['userId']));
    $st = $pdo->prepare('SELECT status FROM registration_payments WHERE reference = ?');
    $st->execute([$payOk['reference']]);
    t_assert('Registration: payment consumed to registered', $st->fetchColumn() === 'registered');
} catch (Throwable $e) {
    t_assert('Registration: valid payment succeeds', false, $e->getMessage());
}

// Admin bypass still works without payment
try {
    $adminCreated = process_member_registration($pdo, ['id' => 2, 'role' => 'admin'], [
        'username' => 'adminreg1',
        'password' => 'password1',
        'firstName' => 'Admin',
        'lastName' => 'Reg',
        'email' => 'adminreg1@example.test',
        'phone' => '08012345682',
        'package' => 'Vision',
        'side' => 'left',
        'sponsor' => 'rootmember',
        'paymentReference' => '',
    ]);
    t_assert('Registration: admin bypass preserved', !empty($adminCreated['userId']));
} catch (Throwable $e) {
    t_assert('Registration: admin bypass preserved', false, $e->getMessage());
}

// ----- Paystack payload validation -----
$base = [
    'status' => 'success',
    'reference' => 'ZGV-REG-TEST1',
    'currency' => 'NGN',
    'amount' => 15000000,
    'metadata' => ['purpose' => 'registration'],
];
$ok = validate_paystack_success_payload($base, 'ZGV-REG-TEST1', [
    'reference' => 'ZGV-REG-TEST1',
    'amount_kobo' => 15000000,
    'currency' => 'NGN',
    'purpose' => 'registration',
]);
t_assert('Paystack: valid payload accepted', !empty($ok['ok']));

$badRef = validate_paystack_success_payload($base, 'OTHER', [
    'reference' => 'OTHER',
    'amount_kobo' => 15000000,
]);
t_assert('Paystack: wrong reference rejected', empty($badRef['ok']));

$badCur = validate_paystack_success_payload(array_merge($base, ['currency' => 'USD']), 'ZGV-REG-TEST1', [
    'amount_kobo' => 15000000,
    'currency' => 'NGN',
]);
t_assert('Paystack: wrong currency rejected', empty($badCur['ok']));

$badAmt = validate_paystack_success_payload($base, 'ZGV-REG-TEST1', [
    'amount_kobo' => 999,
    'currency' => 'NGN',
]);
t_assert('Paystack: wrong amount rejected', empty($badAmt['ok']));

$failedPayload = validate_paystack_success_payload(array_merge($base, ['status' => 'failed']), 'ZGV-REG-TEST1', [
    'amount_kobo' => 15000000,
]);
t_assert('Paystack: failed status rejected', empty($failedPayload['ok']));

$badPurpose = validate_paystack_success_payload($base, 'ZGV-REG-TEST1', [
    'amount_kobo' => 15000000,
    'purpose' => 'product_order',
]);
t_assert('Paystack: wrong purpose rejected', empty($badPurpose['ok']));

// ----- Receipt access -----
$unauth = registration_receipt_access(null, 'ANY');
t_assert('Receipt: unauthenticated denied', empty($unauth['ok']) && ($unauth['code'] ?? 0) === 401);
$memberDenied = registration_receipt_access(['id' => 1, 'role' => 'member'], 'ANY');
t_assert('Receipt: member denied', empty($memberDenied['ok']) && ($memberDenied['code'] ?? 0) === 403);

$storage = $root . '/storage/receipts';
if (!is_dir($storage)) {
    mkdir($storage, 0775, true);
}
$receiptName = 'phase1n_' . bin2hex(random_bytes(8)) . '.png';
$receiptAbs = $storage . '/' . $receiptName;
file_put_contents($receiptAbs, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$payReceipt = insert_payment($pdo, [
    'reference' => 'TEST-RCPT-' . bin2hex(random_bytes(3)),
    'email' => 'receipt@example.test',
    'status' => 'pending',
    'payment_method' => 'transfer',
    'proof_path' => $receiptName,
]);
$adminOk = registration_receipt_access(['id' => 2, 'role' => 'admin'], $payReceipt['reference']);
t_assert('Receipt: admin can resolve valid proof', !empty($adminOk['ok']) && !empty($adminOk['absolute']));

$trav = registration_receipt_access(['id' => 2, 'role' => 'admin'], '../etc/passwd');
t_assert('Receipt: path-like reference rejected or missing', empty($trav['ok']));

@unlink($receiptAbs);

// ----- Order notification uniqueness + conditional paid transition -----
$idx = $pdo->query('SHOW INDEX FROM order_notifications')->fetchAll(PDO::FETCH_ASSOC);
$hasUniqueOrder = false;
foreach ($idx as $row) {
    if (($row['Key_name'] ?? '') === 'uq_order_notifications_order'
        && (int) ($row['Non_unique'] ?? 1) === 0
        && ($row['Column_name'] ?? '') === 'order_id'
    ) {
        $hasUniqueOrder = true;
        break;
    }
}
t_assert('Orders: order_notifications unique on order_id', $hasUniqueOrder);

$pdo->prepare(
    "INSERT INTO orders (reference, store_id, member_id, product_id, quantity, unit_price, payment_status, payment_reference)
     VALUES ('ORD-N1', 1, 1, 1, 1, 10000, 'pending', 'ZGV-ORD-N1')"
)->execute();
$orderId = (int) $pdo->lastInsertId();
$upd1 = $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_amount = 10000, paid_at = NOW() WHERE id = ? AND payment_status <> 'paid'");
$upd1->execute([$orderId]);
t_assert('Orders: first paid transition affects one row', $upd1->rowCount() === 1);
$upd2 = $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_amount = 10000, paid_at = NOW() WHERE id = ? AND payment_status <> 'paid'");
$upd2->execute([$orderId]);
t_assert('Orders: repeated paid transition affects zero rows', $upd2->rowCount() === 0);

$pdo->prepare('INSERT IGNORE INTO order_notifications (store_id, order_id, is_read) VALUES (1, ?, 0)')->execute([$orderId]);
$pdo->prepare('INSERT IGNORE INTO order_notifications (store_id, order_id, is_read) VALUES (1, ?, 0)')->execute([$orderId]);
$notifCount = (int) $pdo->query('SELECT COUNT(*) FROM order_notifications WHERE order_id = ' . $orderId)->fetchColumn();
t_assert('Orders: duplicate notification prevented', $notifCount === 1, 'count=' . $notifCount);

// ----- Migration dry-run (no DDL) -----
$phpBin = (defined('PHP_BINARY') && PHP_BINARY) ? PHP_BINARY : 'C:\\xampp\\php\\php.exe';
putenv('ZENVITA_MIGRATE_EXPECT_DB=zenvita_phase1b_test');
$beforeTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($root . '/database/migrations/migrate_payments.php');
exec($cmd . ' 2>&1', $outPay, $codePay);
t_assert('Migration: migrate_payments dry-run exit 0', $codePay === 0, implode("\n", $outPay));
t_assert(
    'Migration: migrate_payments dry-run mentions no DDL',
    stripos(implode("\n", $outPay), 'No DDL executed') !== false,
    implode(' | ', $outPay)
);
$afterTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
t_assert('Migration: dry-run did not change table list', $beforeTables === $afterTables);

$outStore = [];
$cmd2 = escapeshellarg($phpBin) . ' ' . escapeshellarg($root . '/database/migrations/migrate_store_orders.php');
exec($cmd2 . ' 2>&1', $outStore, $codeStore);
t_assert('Migration: migrate_store_orders dry-run exit 0', $codeStore === 0, implode("\n", $outStore));

// Wrong identity blocked
putenv('ZENVITA_MIGRATE_EXPECT_DB=definitely_not_this_db');
$outBad = [];
exec($cmd . ' 2>&1', $outBad, $codeBad);
t_assert('Migration: wrong DB identity blocked', $codeBad !== 0, implode("\n", $outBad));
putenv('ZENVITA_MIGRATE_EXPECT_DB=zenvita_phase1b_test');

// Apply without confirm refused
$outApply = [];
exec($cmd . ' --apply 2>&1', $outApply, $codeApply);
t_assert(
    'Migration: apply without confirm refused',
    $codeApply !== 0 && stripos(implode("\n", $outApply), 'i-understand-ddl') !== false,
    implode("\n", $outApply)
);

// ----- paystack-init BOM -----
$initPath = $root . '/public/api/paystack-init.php';
$bytes = file_get_contents($initPath);
t_assert(
    'Paystack-init: no UTF-8 BOM',
    $bytes !== false && !(strlen($bytes) >= 3 && substr($bytes, 0, 3) === "\xEF\xBB\xBF")
);

// ----- Runtime DDL gate still present -----
t_assert('DDL gate: app_allows_runtime_ddl callable', function_exists('app_allows_runtime_ddl'));

echo "\n=== Summary: {$failed} failed of " . count($results) . " ===\n";

$reportDir = $root . '/storage/reports';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0775, true);
}
$reportPath = $reportDir . '/phase1n_test_results_' . date('Ymd_His') . '.json';
file_put_contents($reportPath, json_encode([
    'phase' => '1N',
    'failed' => $failed,
    'results' => $results,
    'generated_at' => date('c'),
], JSON_PRETTY_PRINT));
echo "Report: {$reportPath}\n";

exit($failed > 0 ? 1 : 0);
