<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$user = require_user('member');
ensure_eagle_lands_schema();
$body = request_body();
$amount = (float) ($body['amount'] ?? 0);
$bank = trim((string) ($body['bank'] ?? ''));
$accountName = trim((string) ($body['accountName'] ?? ''));
$accountNumber = preg_replace('/\D+/', '', (string) ($body['accountNumber'] ?? ''));

$policy = withdrawal_policy();
if (!$policy['isWithdrawDay']) {
    json_out([
        'ok' => false,
        'error' => 'Withdrawals are only allowed on Saturdays (Africa/Lagos). Today is ' . $policy['today'] . '.',
    ], 422);
}

$minimum = (float) setting('min_withdrawal', '1000');
if ($amount < $minimum) {
    json_out(['ok' => false, 'error' => 'Enter at least ₦' . number_format($minimum, 0) . '.'], 422);
}
if ($bank === '' || $accountName === '') {
    json_out(['ok' => false, 'error' => 'Enter the bank and account name.'], 422);
}
if (strlen($accountNumber) !== 10) {
    json_out(['ok' => false, 'error' => 'Enter a 10-digit account number.'], 422);
}

$split = withdrawal_split($amount);
$pdo = db();
$pdo->beginTransaction();
try {
    $wallet = $pdo->prepare('SELECT available_balance, eagle_lands_balance FROM wallets WHERE user_id = ? FOR UPDATE');
    $wallet->execute([$user['id']]);
    $walletRow = $wallet->fetch();
    $available = (float) ($walletRow['available_balance'] ?? 0);
    $eagle = (float) ($walletRow['eagle_lands_balance'] ?? 0);

    $maxCash = round($available * ((float) $policy['cashPercent'] / 100), 2);
    if ($amount > $maxCash + 0.00001) {
        $pdo->rollBack();
        json_out([
            'ok' => false,
            'error' => 'You can withdraw only ' . (int) $policy['cashPercent'] . '% of your balance (max ₦' . number_format($maxCash, 0) . '). The remaining ' . (int) $policy['reservePercent'] . '% goes to Eagle Lands & Properties.',
        ], 422);
    }
    if ($split['total'] > $available + 0.00001) {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => 'Not enough balance for the cash withdrawal plus Eagle Lands reserve.'], 422);
    }

    $pdo->prepare(
        'UPDATE wallets
         SET available_balance = available_balance - ?,
             eagle_lands_balance = eagle_lands_balance + ?
         WHERE user_id = ?'
    )->execute([$split['total'], $split['reserved'], $user['id']]);

    $pdo->prepare(
        'INSERT INTO withdrawals (user_id, amount, reserved_amount, bank_name, account_number, account_name, status, requested_on)
         VALUES (?, ?, ?, ?, ?, ?, \'Pending\', CURDATE())'
    )->execute([$user['id'], $split['cash'], $split['reserved'], $bank, $accountNumber, $accountName]);

    $cashRef = 'TX-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    $eagleRef = 'TX-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

    $pdo->prepare(
        'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
         VALUES (?, ?, \'Withdrawal\', ?, ?, \'Pending\', CURDATE())'
    )->execute([$user['id'], $cashRef, 'Cash withdrawal request (40%)', -$split['cash']]);

    $pdo->prepare(
        'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
         VALUES (?, ?, \'Eagle Lands\', ?, ?, \'Approved\', CURDATE())'
    )->execute([
        $user['id'],
        $eagleRef,
        'Reserved for Eagle Lands & Properties (60%)',
        -$split['reserved'],
    ]);

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['ok' => false, 'error' => 'The withdrawal could not be saved.'], 500);
}

json_out([
    'ok' => true,
    'available' => round($available - $split['total'], 2),
    'eagleLands' => round($eagle + $split['reserved'], 2),
    'withdrawable' => round(($available - $split['total']) * ((float) $policy['cashPercent'] / 100), 2),
    'withdrawal' => [
        'date' => date('d M Y'),
        'amount' => $split['cash'],
        'reserved' => $split['reserved'],
        'bank' => $bank,
        'account' => $accountNumber,
        'name' => $accountName,
        'status' => 'Pending',
    ],
    'transaction' => [
        'date' => date('d M Y'),
        'id' => $cashRef,
        'type' => 'Withdrawal',
        'description' => 'Cash withdrawal request (40%)',
        'amount' => -$split['cash'],
        'status' => 'Pending',
    ],
    'eagleTransaction' => [
        'date' => date('d M Y'),
        'id' => $eagleRef,
        'type' => 'Eagle Lands',
        'description' => 'Reserved for Eagle Lands & Properties (60%)',
        'amount' => -$split['reserved'],
        'status' => 'Approved',
    ],
]);
