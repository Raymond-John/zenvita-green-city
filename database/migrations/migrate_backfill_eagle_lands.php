<?php
/**
 * Backfill missing Eagle Lands 60% reserves on older withdrawals.
 * Run: C:\xampp\php\php.exe database/migrations/migrate_backfill_eagle_lands.php
 */
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ensure_eagle_lands_schema();
$pdo = db();
$policy = withdrawal_policy();
$cashPercent = (float) $policy['cashPercent'];
$reservePercent = (float) $policy['reservePercent'];
if ($cashPercent <= 0) {
    $cashPercent = 40;
}
if ($reservePercent <= 0) {
    $reservePercent = 60;
}

$pdo->beginTransaction();
try {
    $rows = $pdo->query(
        "SELECT id, user_id, amount, reserved_amount, status, requested_on
         FROM withdrawals
         WHERE reserved_amount IS NULL OR reserved_amount <= 0"
    )->fetchAll(PDO::FETCH_ASSOC);

    $update = $pdo->prepare('UPDATE withdrawals SET reserved_amount = ? WHERE id = ?');
    $txInsert = $pdo->prepare(
        'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
         VALUES (?, ?, \'Eagle Lands\', ?, ?, \'Approved\', ?)'
    );
    $txExists = $pdo->prepare(
        "SELECT id FROM transactions
         WHERE user_id = ?
           AND tx_type = 'Eagle Lands'
           AND ABS(amount - ?) < 0.009
           AND tx_date = ?
         LIMIT 1"
    );

    $fixed = 0;
    foreach ($rows as $row) {
        if (($row['status'] ?? '') === 'Rejected') {
            continue;
        }
        $cash = (float) $row['amount'];
        $reserved = round($cash * ($reservePercent / $cashPercent), 2);
        if ($reserved <= 0) {
            continue;
        }
        $update->execute([$reserved, (int) $row['id']]);

        $txDate = $row['requested_on'] ?: date('Y-m-d');
        $txExists->execute([(int) $row['user_id'], -1 * $reserved, $txDate]);
        if (!$txExists->fetch()) {
            $ref = 'TX-EL-' . (int) $row['id'] . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $txInsert->execute([
                (int) $row['user_id'],
                $ref,
                'Reserved for Eagle Lands & Properties (60%)',
                -1 * $reserved,
                $txDate,
            ]);
        }
        $fixed++;
    }

    // Rebuild eagle_lands_balance from active (non-rejected) withdrawal reserves.
    $pdo->exec('UPDATE wallets SET eagle_lands_balance = 0');
    $pdo->exec(
        "UPDATE wallets w
         INNER JOIN (
            SELECT user_id, COALESCE(SUM(reserved_amount), 0) AS total_reserved
            FROM withdrawals
            WHERE status IN ('Pending', 'Approved', 'Paid')
            GROUP BY user_id
         ) x ON x.user_id = w.user_id
         SET w.eagle_lands_balance = x.total_reserved"
    );

    $pdo->commit();
    fwrite(STDOUT, "Backfilled reserved_amount on {$fixed} withdrawal(s).\n");

    $summary = $pdo->query(
        'SELECT user_id, available_balance, eagle_lands_balance
         FROM wallets
         WHERE eagle_lands_balance > 0
         ORDER BY user_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($summary as $row) {
        fwrite(
            STDOUT,
            'user ' . $row['user_id'] . ' eagle_lands=' . $row['eagle_lands_balance'] . "\n"
        );
    }
    fwrite(STDOUT, "Migration complete.\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Backfill failed: ' . $e->getMessage() . "\n");
    exit(1);
}
