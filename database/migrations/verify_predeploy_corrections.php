<?php
/**
 * Pre-deploy verification for store-bonus 1062 handling + Eagle Lands package gate.
 * Creates disposable rows, then cleans them up. Does not deploy.
 *
 * Run: C:\xampp\php\php.exe database/migrations/verify_predeploy_corrections.php
 */
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = db();
$pass = 0;
$fail = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        fwrite(STDOUT, "PASS  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n");
    } else {
        $fail++;
        fwrite(STDOUT, "FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n");
    }
}

$tag = 'predeploy_' . substr(bin2hex(random_bytes(4)), 0, 8);
$ownerUsername = $tag . '_owner';
$memberUsername = $tag . '_member';
$savedTarget = setting('store_sales_target', '3000000');
$savedBonus = setting('store_sales_bonus', '0');
$ownerId = 0;
$buyerId = 0;
$storeId = 0;
$orderId = 0;
$productId = 0;
$triggerName = '';

try {
    // --- Policy: Vision–Platinum = 40/60; others = 100/0 ---
    foreach (['vision', 'silver', 'gold', 'diamond', 'platinum'] as $code) {
        $policy = withdrawal_policy(['package_code' => $code]);
        assert_true(
            "eagle_lands_40_60_{$code}",
            !empty($policy['eagleLandsEligible'])
                && (float) $policy['cashPercent'] === 40.0
                && (float) $policy['reservePercent'] === 60.0
                && package_uses_eagle_lands_split(['package_code' => $code]) === true,
            'cash=' . $policy['cashPercent'] . ' reserve=' . $policy['reservePercent']
        );
    }
    foreach (['starter', 'basic', 'elite', 'titanium', 'ultra_premium', ''] as $code) {
        $user = ['package_code' => $code];
        $policy = withdrawal_policy($user);
        $label = $code === '' ? 'empty' : $code;
        assert_true(
            "eagle_lands_100_0_{$label}",
            empty($policy['eagleLandsEligible'])
                && (float) $policy['cashPercent'] === 100.0
                && (float) $policy['reservePercent'] === 0.0
                && package_uses_eagle_lands_split($user) === false,
            'cash=' . $policy['cashPercent'] . ' reserve=' . $policy['reservePercent']
        );
    }
    assert_true(
        'no_price_fallback_high_code',
        package_uses_eagle_lands_split(['package_code' => 'ultra_premium']) === false
    );

    ensure_store_sales_bonus_schema($pdo);

    $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, first_name, last_name, role, status)
         VALUES (?, ?, ?, ?, ?, \'store\', \'active\')'
    )->execute([$ownerUsername, $ownerUsername . '@test.local', password_hash('x', PASSWORD_BCRYPT), 'Pre', 'Deploy']);
    $ownerId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO wallets (user_id, available_balance, eagle_lands_balance) VALUES (?, 0, 0)')
        ->execute([$ownerId]);

    $pdo->prepare(
        'INSERT INTO stores (owner_user_id, name, city, status) VALUES (?, ?, ?, \'active\')'
    )->execute([$ownerId, $tag . ' Store', 'Lagos']);
    $storeId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO products (code, name, description, price, unit_label, status, sort_order, show_on_landing)
         VALUES (?, ?, ?, 3000000, \'unit\', \'active\', 0, 0)'
    )->execute([$tag . '_p', $tag . ' Product', 'predeploy verify']);
    $productId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, first_name, last_name, role, status)
         VALUES (?, ?, ?, ?, ?, \'member\', \'active\')'
    )->execute([$memberUsername, $memberUsername . '@test.local', password_hash('x', PASSWORD_BCRYPT), 'Buyer', 'Test']);
    $buyerId = (int) $pdo->lastInsertId();

    $orderRef = 'ORD-' . strtoupper(substr($tag, -8));
    $pdo->prepare(
        "INSERT INTO orders
            (reference, member_id, store_id, product_id, quantity, unit_price, fulfillment,
             full_name, phone, email, status)
         VALUES (?, ?, ?, ?, 1, 3000000, 'pickup', 'Buyer Test', '08000000000', ?, 'Completed')"
    )->execute([$orderRef, $buyerId, $storeId, $productId, $memberUsername . '@test.local']);
    $orderId = (int) $pdo->lastInsertId();

    // --- Bonus disabled when amount is 0 ---
    $setSetting = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $setSetting->execute(['store_sales_target', '3000000']);
    $setSetting->execute(['store_sales_bonus', '0']);

    $disabled = maybe_award_store_sales_bonuses($storeId, $pdo);
    assert_true(
        'bonus_disabled_when_zero',
        empty($disabled['awarded']) && (float) ($disabled['config']['bonus'] ?? -1) === 0.0,
        'awarded=' . count($disabled['awarded'] ?? [])
    );
    $walletBefore = (float) $pdo->query("SELECT available_balance FROM wallets WHERE user_id = {$ownerId}")->fetchColumn();
    assert_true('bonus_zero_no_wallet_credit', abs($walletBefore) < 0.00001, 'balance=' . $walletBefore);

    $setSetting->execute(['store_sales_bonus', '50000']);

    $first = maybe_award_store_sales_bonuses($storeId, $pdo);
    assert_true(
        'milestone_awarded_once_first_call',
        count($first['awarded']) === 1 && (int) $first['awarded'][0]['level'] === 1,
        'awarded=' . count($first['awarded'])
    );
    $walletAfterFirst = (float) $pdo->query("SELECT available_balance FROM wallets WHERE user_id = {$ownerId}")->fetchColumn();
    assert_true('wallet_credited_once', abs($walletAfterFirst - 50000) < 0.00001, 'balance=' . $walletAfterFirst);

    $second = maybe_award_store_sales_bonuses($storeId, $pdo);
    assert_true(
        'milestone_not_reawarded',
        count($second['awarded']) === 0,
        'awarded=' . count($second['awarded'])
    );
    $walletAfterSecond = (float) $pdo->query("SELECT available_balance FROM wallets WHERE user_id = {$ownerId}")->fetchColumn();
    assert_true('wallet_unchanged_on_repeat', abs($walletAfterSecond - 50000) < 0.00001, 'balance=' . $walletAfterSecond);

    $awardCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM store_sales_bonus_awards WHERE store_id = {$storeId} AND threshold_level = 1"
    )->fetchColumn();
    assert_true('single_award_row', $awardCount === 1, 'rows=' . $awardCount);

    try {
        maybe_award_store_sales_bonuses($storeId, $pdo);
        assert_true('duplicate_1062_swallowed', true);
    } catch (Throwable $e) {
        assert_true('duplicate_1062_swallowed', false, $e->getMessage());
    }

    // --- Genuine DB failure rolls back order completion + bonus ---
    $pdo->prepare("UPDATE orders SET status = 'Ready' WHERE id = ?")->execute([$orderId]);
    $pdo->prepare('DELETE FROM store_sales_bonus_awards WHERE store_id = ?')->execute([$storeId]);
    $pdo->prepare("DELETE FROM transactions WHERE user_id = ? AND tx_type = 'Store Bonus'")->execute([$ownerId]);
    $pdo->prepare('UPDATE wallets SET available_balance = 0 WHERE user_id = ?')->execute([$ownerId]);

    $triggerName = 'trg_' . preg_replace('/[^a-z0-9_]/i', '', $tag) . '_fail';
    $pdo->exec("DROP TRIGGER IF EXISTS `{$triggerName}`");
    $pdo->exec(
        "CREATE TRIGGER `{$triggerName}` BEFORE INSERT ON store_sales_bonus_awards
         FOR EACH ROW
         BEGIN
           IF NEW.store_id = {$storeId} THEN
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced predeploy verification failure';
           END IF;
         END"
    );

    $rolledBack = false;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE id = ? AND store_id = ?")
            ->execute([$orderId, $storeId]);
        maybe_award_store_sales_bonuses($storeId, $pdo);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $rolledBack = true;
        $msg = $e->getMessage();
        assert_true(
            'genuine_db_failure_rethrows',
            strpos($msg, 'forced predeploy') !== false
                || (isset($e->errorInfo[0]) && (string) $e->errorInfo[0] === '45000'),
            $msg
        );
    }

    assert_true('order_completion_rolled_back', $rolledBack);
    $statusAfter = (string) $pdo->query("SELECT status FROM orders WHERE id = {$orderId}")->fetchColumn();
    assert_true('order_still_ready', $statusAfter === 'Ready', 'status=' . $statusAfter);
    $awardsAfterFail = (int) $pdo->query(
        "SELECT COUNT(*) FROM store_sales_bonus_awards WHERE store_id = {$storeId}"
    )->fetchColumn();
    assert_true('no_award_after_rollback', $awardsAfterFail === 0, 'rows=' . $awardsAfterFail);
    $walletAfterFail = (float) $pdo->query("SELECT available_balance FROM wallets WHERE user_id = {$ownerId}")->fetchColumn();
    assert_true('no_wallet_credit_after_rollback', abs($walletAfterFail) < 0.00001, 'balance=' . $walletAfterFail);

} catch (Throwable $e) {
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    $fail++;
} finally {
    try {
        if ($triggerName !== '') {
            $pdo->exec("DROP TRIGGER IF EXISTS `{$triggerName}`");
        }
    } catch (Throwable $e) {
        // ignore
    }

    if ($storeId > 0) {
        $pdo->prepare('DELETE FROM store_sales_bonus_awards WHERE store_id = ?')->execute([$storeId]);
        $pdo->prepare('DELETE FROM order_notifications WHERE store_id = ?')->execute([$storeId]);
        $pdo->prepare('DELETE FROM orders WHERE store_id = ?')->execute([$storeId]);
        $pdo->prepare('DELETE FROM stores WHERE id = ?')->execute([$storeId]);
    }
    if ($productId > 0) {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
    }
    if ($ownerId > 0) {
        $pdo->prepare("DELETE FROM transactions WHERE user_id = ? AND tx_type = 'Store Bonus'")->execute([$ownerId]);
        $pdo->prepare('DELETE FROM wallets WHERE user_id = ?')->execute([$ownerId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$ownerId]);
    }
    if ($buyerId > 0) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$buyerId]);
    }

    $restore = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $restore->execute(['store_sales_target', $savedTarget]);
    $restore->execute(['store_sales_bonus', $savedBonus]);
}

fwrite(STDOUT, "\nSummary: {$pass} passed, {$fail} failed\n");
exit($fail > 0 ? 1 : 0);
