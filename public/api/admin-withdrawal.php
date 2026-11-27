<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

require_user('admin');
$body = request_body();
$id = (int) ($body['id'] ?? 0);
$status = trim((string) ($body['status'] ?? ''));
if ($id < 1 || !in_array($status, ['Approved', 'Rejected'], true)) {
    json_out(['ok' => false, 'error' => 'Choose approve or reject.'], 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'SELECT id, user_id, amount, reserved_amount, status, requested_on
         FROM withdrawals WHERE id = ? FOR UPDATE'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => 'That withdrawal was not found.'], 404);
    }
    if ($row['status'] !== 'Pending') {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => 'Only a pending request can be updated.'], 422);
    }

    $pdo->prepare('UPDATE withdrawals SET status = ? WHERE id = ?')->execute([$status, $id]);

    $cash = (float) $row['amount'];
    $reserved = (float) ($row['reserved_amount'] ?? 0);

    if ($status === 'Rejected') {
        // Return cash + reserved portion to available wallet; reverse eagle reserve.
        $pdo->prepare(
            'UPDATE wallets
             SET available_balance = available_balance + ?,
                 eagle_lands_balance = GREATEST(0, eagle_lands_balance - ?)
             WHERE user_id = ?'
        )->execute([$cash + $reserved, $reserved, $row['user_id']]);

        if ($reserved > 0) {
            $refundRef = 'TX-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $pdo->prepare(
                'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
                 VALUES (?, ?, \'Eagle land and Homes LTD\', ?, ?, \'Approved\', CURDATE())'
            )->execute([
                $row['user_id'],
                $refundRef,
                'Eagle land and Homes LTD reserve returned after withdrawal rejection',
                $reserved,
            ]);
        }
    }

    $pdo->prepare(
        'UPDATE transactions
         SET status = ?
         WHERE user_id = ? AND tx_type = \'Withdrawal\' AND amount = ? AND status = \'Pending\' AND tx_date = ?
         LIMIT 1'
    )->execute([$status, $row['user_id'], -1 * $cash, $row['requested_on']]);

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['ok' => false, 'error' => 'The withdrawal could not be updated.'], 500);
}

json_out(['ok' => true]);
