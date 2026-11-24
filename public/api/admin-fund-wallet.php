<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$admin = require_user('admin');
ensure_registration_payments_table();
ensure_smtp_settings();
$body = request_body();
$action = strtolower(trim((string) ($body['action'] ?? 'fund_payment')));
$pdo = db();

function notify_wallet_funded(int $userId, float $amount, string $reference, string $note): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT u.id, u.email, u.first_name, u.last_name, u.username, u.member_code,
                COALESCE(w.available_balance, 0) AS available_balance
         FROM users u
         LEFT JOIN wallets w ON w.user_id = u.id
         WHERE u.id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['ok' => false, 'error' => 'Member not found for email notice.'];
    }
    return send_wallet_funded_email(
        $user,
        $amount,
        $reference,
        $note,
        (float) $user['available_balance']
    );
}

if ($action === 'fund_payment') {
    $paymentId = (int) ($body['paymentId'] ?? 0);
    if ($paymentId < 1) {
        json_out(['ok' => false, 'error' => 'Choose a payment to fund.'], 422);
    }

    $reference = '';
    $amount = 0.0;
    $userId = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM registration_payments WHERE id = ? FOR UPDATE');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment) {
            $pdo->rollBack();
            json_out(['ok' => false, 'error' => 'Payment not found.'], 404);
        }
        if ($payment['status'] === 'funded') {
            $pdo->rollBack();
            json_out(['ok' => false, 'error' => 'This payment was already funded.'], 422);
        }
        if ($payment['status'] !== 'registered' || empty($payment['user_id'])) {
            $pdo->rollBack();
            json_out(['ok' => false, 'error' => 'The member must finish registration before the wallet can be funded.'], 422);
        }

        $userId = (int) $payment['user_id'];
        $amount = round((float) $payment['amount'], 2);
        $wallet = $pdo->prepare('SELECT available_balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $wallet->execute([$userId]);
        if ($wallet->fetch() === false) {
            $pdo->prepare('INSERT INTO wallets (user_id, available_balance) VALUES (?, 0)')->execute([$userId]);
        }
        $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?')
            ->execute([$amount, $userId]);

        $reference = 'TX-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $note = 'Admin funded wallet from Paystack payment ' . $payment['reference'];
        $pdo->prepare(
            'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
             VALUES (?, ?, \'Deposit\', ?, ?, \'Approved\', CURDATE())'
        )->execute([
            $userId,
            $reference,
            $note,
            $amount,
        ]);

        $pdo->prepare(
            "UPDATE registration_payments
             SET status = 'funded', funded_by = ?, funded_at = NOW(), updated_at = NOW()
             WHERE id = ?"
        )->execute([(int) $admin['id'], $paymentId]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_out(['ok' => false, 'error' => 'Wallet funding failed.'], 500);
    }

    $mail = notify_wallet_funded($userId, $amount, $reference, $note ?? 'Wallet deposit');
    json_out([
        'ok' => true,
        'amount' => $amount,
        'userId' => $userId,
        'emailSent' => !empty($mail['ok']),
        'emailError' => $mail['ok'] ? null : ($mail['error'] ?? 'Email not sent.'),
    ]);
}

if ($action === 'manual_fund') {
    $memberCode = trim((string) ($body['memberCode'] ?? $body['member'] ?? ''));
    $amount = round((float) ($body['amount'] ?? 0), 2);
    $note = trim((string) ($body['note'] ?? 'Admin wallet deposit'));
    if ($memberCode === '') {
        json_out(['ok' => false, 'error' => 'Enter a member ID or username.'], 422);
    }
    if ($amount < 1) {
        json_out(['ok' => false, 'error' => 'Enter an amount of at least ₦1.'], 422);
    }

    $member = $pdo->prepare(
        "SELECT id, member_code, username, email, first_name, last_name
         FROM users
         WHERE (member_code = ? OR username = ?) AND role = 'member'
         LIMIT 1"
    );
    $member->execute([$memberCode, $memberCode]);
    $row = $member->fetch();
    if (!$row) {
        json_out(['ok' => false, 'error' => 'Member not found.'], 404);
    }

    $reference = '';
    $pdo->beginTransaction();
    try {
        $userId = (int) $row['id'];
        $wallet = $pdo->prepare('SELECT available_balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $wallet->execute([$userId]);
        if ($wallet->fetch() === false) {
            $pdo->prepare('INSERT INTO wallets (user_id, available_balance) VALUES (?, 0)')->execute([$userId]);
        }
        $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?')
            ->execute([$amount, $userId]);
        $reference = 'TX-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $pdo->prepare(
            'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
             VALUES (?, ?, \'Deposit\', ?, ?, \'Approved\', CURDATE())'
        )->execute([$userId, $reference, $note !== '' ? $note : 'Admin wallet deposit', $amount]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_out(['ok' => false, 'error' => 'Manual funding failed.'], 500);
    }

    $mail = notify_wallet_funded((int) $row['id'], $amount, $reference, $note !== '' ? $note : 'Admin wallet deposit');
    json_out([
        'ok' => true,
        'amount' => $amount,
        'memberCode' => $row['member_code'],
        'emailSent' => !empty($mail['ok']),
        'emailError' => $mail['ok'] ? null : ($mail['error'] ?? 'Email not sent.'),
    ]);
}

json_out(['ok' => false, 'error' => 'Unknown action.'], 422);
