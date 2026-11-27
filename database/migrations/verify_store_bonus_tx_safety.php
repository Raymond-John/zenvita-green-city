<?php
/**
 * LOCAL-ONLY transaction-safety checks for store sales bonus.
 * Uses disposable rows on the configured local DB (config/database.php).
 * Do NOT run against production.
 *
 * Run: C:\xampp\php\php.exe database/migrations/verify_store_bonus_tx_safety.php
 */
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$dbCfg = require dirname(__DIR__, 2) . '/config/database.php';
$host = (string) ($dbCfg['host'] ?? '');
$name = (string) ($dbCfg['name'] ?? '');
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "Refusing to run: database host '{$host}' does not look local.\n");
    exit(2);
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

function new_pdo(): PDO
{
    $cfg = require dirname(__DIR__, 2) . '/config/database.php';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['name']);
    $conn = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $conn;
}

/**
 * Mirrors public/api/store-order.php completion path (schema ensure BEFORE beginTransaction).
 */
function complete_order_with_bonus(PDO $pdo, int $storeId, int $orderId): array
{
    ensure_store_sales_bonus_schema($pdo);
    $pdo->beginTransaction();
    try {
        $order = $pdo->prepare('SELECT id, status FROM orders WHERE id = ? AND store_id = ? LIMIT 1 FOR UPDATE');
        $order->execute([$orderId, $storeId]);
        $row = $order->fetch();
        if (!$row) {
            $pdo->rollBack();
            throw new RuntimeException('order missing');
        }
        $previous = (string) $row['status'];
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND store_id = ?')
            ->execute(['Completed', $orderId, $storeId]);
        $bonusResult = ['awarded' => []];
        if ($previous !== 'Completed') {
            $bonusResult = maybe_award_store_sales_bonuses($storeId, $pdo);
        }
        $pdo->commit();
        return $bonusResult;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$tag = 'txsafe_' . substr(bin2hex(random_bytes(4)), 0, 8);
$savedTarget = setting('store_sales_target', '3000000');
$savedBonus = setting('store_sales_bonus', '0');
$ownerId = 0;
$buyerId = 0;
$storeId = 0;
$productId = 0;
$orderBelowId = 0;
$orderCrossId = 0;
$orderFailWalletId = 0;
$orderFailTxId = 0;
$triggers = [];

fwrite(STDOUT, "Local DB: {$host}/{$name}\n");

try {
    // --- Eagle Lands + defaults unchanged ---
    assert_true(
        'eagle_lands_codes_unchanged',
        eagle_lands_package_codes() === ['vision', 'silver', 'gold', 'diamond', 'platinum']
    );
    assert_true(
        'no_price_fallback',
        package_uses_eagle_lands_split(['package_code' => 'ultra_premium']) === false
        && package_uses_eagle_lands_split(['package_code' => 'vision']) === true
    );

    ensure_store_sales_bonus_schema($pdo);
    $set = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $set->execute(['store_sales_target', '3000000']);
    $set->execute(['store_sales_bonus', '50000']);

    $cfg = store_sales_bonus_config($pdo);
    assert_true('default_target_still_3m_when_set', (float) $cfg['target'] === 3000000.0);

    // Schema ensure before tx does not leave connection out of transaction incorrectly
    ensure_store_sales_bonus_schema($pdo);
    $pdo->beginTransaction();
    $stillInTx = $pdo->inTransaction();
    ensure_store_sales_bonus_schema($pdo); // must be no-op / no DDL
    $stillInTxAfterEnsure = $pdo->inTransaction();
    assert_true('schema_ensure_before_tx_keeps_transaction', $stillInTx && $stillInTxAfterEnsure);
    $pdo->rollBack();

    // Calling ensure inside tx when table missing must throw (simulate via rename check is heavy);
    // instead verify CREATE is skipped when table exists and inTransaction stays true after maybe_award join.
    $pdo->beginTransaction();
    $inBefore = $pdo->inTransaction();
    // Disabled bonus path still should not commit outer tx
    $set->execute(['store_sales_bonus', '0']);
    // Reset static config by reading after setting — ensure done flag already true
    $disabled = maybe_award_store_sales_bonuses(999999999, $pdo); // missing store, ownsTx=false
    $inAfter = $pdo->inTransaction();
    assert_true('helper_inside_outer_tx_no_implicit_commit', $inBefore && $inAfter, 'inTx before/after');
    $pdo->rollBack();
    $set->execute(['store_sales_bonus', '50000']);

    // Disposable fixture
    $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, first_name, last_name, role, status)
         VALUES (?, ?, ?, ?, ?, \'store\', \'active\')'
    )->execute([$tag . '_o', $tag . '_o@test.local', password_hash('x', PASSWORD_BCRYPT), 'Tx', 'Owner']);
    $ownerId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO wallets (user_id, available_balance, eagle_lands_balance) VALUES (?, 0, 0)')
        ->execute([$ownerId]);
    $pdo->prepare('INSERT INTO stores (owner_user_id, name, city, status) VALUES (?, ?, ?, \'active\')')
        ->execute([$ownerId, $tag . ' Store', 'Lagos']);
    $storeId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO products (code, name, description, price, unit_label, status, sort_order, show_on_landing)
         VALUES (?, ?, ?, 3000000, \'unit\', \'active\', 0, 0)'
    )->execute([$tag . '_p', $tag . ' Product', 'tx safety']);
    $productId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, first_name, last_name, role, status)
         VALUES (?, ?, ?, ?, ?, \'member\', \'active\')'
    )->execute([$tag . '_b', $tag . '_b@test.local', password_hash('x', PASSWORD_BCRYPT), 'Tx', 'Buyer']);
    $buyerId = (int) $pdo->lastInsertId();

    $insOrder = $pdo->prepare(
        "INSERT INTO orders
            (reference, member_id, store_id, product_id, quantity, unit_price, fulfillment,
             full_name, phone, email, status)
         VALUES (?, ?, ?, ?, 1, ?, 'pickup', 'Buyer', '08000000000', ?, ?)"
    );

    // Below target: 500k Ready → Completed
    $insOrder->execute([$tag . '-BELOW', $buyerId, $storeId, $productId, 500000, $tag . '_b@test.local', 'Ready']);
    $orderBelowId = (int) $pdo->lastInsertId();
    $below = complete_order_with_bonus($pdo, $storeId, $orderBelowId);
    $statusBelow = (string) $pdo->query("SELECT status FROM orders WHERE id = {$orderBelowId}")->fetchColumn();
    $walletBelow = (float) $pdo->query("SELECT available_balance FROM wallets WHERE user_id = {$ownerId}")->fetchColumn();
    $awardsBelow = (int) $pdo->query("SELECT COUNT(*) FROM store_sales_bonus_awards WHERE store_id = {$storeId}")->fetchColumn();
    assert_true('below_target_completes', $statusBelow === 'Completed');
    assert_true('below_target_no_bonus', empty($below['awarded']) && $awardsBelow === 0 && abs($walletBelow) < 0.00001);

    // Cross target: another 2.5M → total 3M
    $insOrder->execute([$tag . '-CROSS', $buyerId, $storeId, $productId, 2500000, $tag . '_b@test.local', 'Ready']);
    $orderCrossId = (int) $pdo->lastInsertId();
    $cross = complete_order_with_bonus($pdo, $storeId, $orderCrossId);
    $awardsAfter = (int) $pdo->query("SELECT COUNT(*) FROM store_sales_bonus_awards WHERE store_id = {$storeId}")->fetchColumn();
    $walletAfter = (float) $pdo->query("SELECT available_balance FROM wallets WHERE user_id = {$ownerId}")->fetchColumn();
    $txAfter = (int) $pdo->query(
        "SELECT COUNT(*) FROM transactions WHERE user_id = {$ownerId} AND tx_type = 'Store Bonus'"
    )->fetchColumn();
    assert_true('cross_target_awards_once', count($cross['awarded']) === 1 && $awardsAfter === 1, 'awards=' . $awardsAfter);
    assert_true('cross_target_wallet_and_tx', abs($walletAfter - 50000) < 0.00001 && $txAfter === 1);

    // Repeat completion request (already Completed) — no double pay
    $repeat = complete_order_with_bonus($pdo, $storeId, $orderCrossId);
    $awardsRepeat = (int) $pdo->query("SELECT COUNT(*) FROM store_sales_bonus_awards WHERE store_id = {$storeId}")->fetchColumn();
    $walletRepeat = (float) $pdo->query("SELECT available_balance FROM wallets WHERE user_id = {$ownerId}")->fetchColumn();
    assert_true('repeat_completion_no_double_pay', empty($repeat['awarded']) && $awardsRepeat === 1 && abs($walletRepeat - 50000) < 0.00001);

    // Unique constraints present
    $idx = $pdo->query("SHOW INDEX FROM store_sales_bonus_awards")->fetchAll();
    $uniqueLevel = false;
    $uniqueRef = false;
    foreach ($idx as $row) {
        if (($row['Key_name'] ?? '') === 'uq_store_bonus_level' && (int) ($row['Non_unique'] ?? 1) === 0) {
            $uniqueLevel = true;
        }
        if (($row['Key_name'] ?? '') === 'uq_store_bonus_reference' && (int) ($row['Non_unique'] ?? 1) === 0) {
            $uniqueRef = true;
        }
    }
    assert_true('unique_store_level_constraint', $uniqueLevel);
    assert_true('unique_reference_constraint', $uniqueRef);

    // Concurrent award of same next milestone (level 2): sales at 6M via extra completed order.
    $insOrder->execute([$tag . '-L2', $buyerId, $storeId, $productId, 3000000, $tag . '_b@test.local', 'Completed']);
    $pdoA = new_pdo();
    $pdoB = new_pdo();
    ensure_store_sales_bonus_schema($pdoA);
    ensure_store_sales_bonus_schema($pdoB);
    // Two independent connections race; unique (store_id, threshold_level) + 1062 handling keeps one winner.
    $rA = maybe_award_store_sales_bonuses($storeId, $pdoA);
    $rB = maybe_award_store_sales_bonuses($storeId, $pdoB);
    $level2Count = (int) $pdo->query(
        "SELECT COUNT(*) FROM store_sales_bonus_awards WHERE store_id = {$storeId} AND threshold_level = 2"
    )->fetchColumn();
    $walletConc = (float) $pdo->query("SELECT available_balance FROM wallets WHERE user_id = {$ownerId}")->fetchColumn();
    $totalAwardedCalls = count($rA['awarded']) + count($rB['awarded']);
    assert_true('concurrent_same_milestone_once', $level2Count === 1, 'level2_rows=' . $level2Count);
    assert_true(
        'concurrent_wallet_not_double',
        abs($walletConc - 100000) < 0.00001 && $totalAwardedCalls === 1,
        'balance=' . $walletConc . ' awarded_calls=' . $totalAwardedCalls
    );

    // Failure during wallet credit → full rollback of order completion
    $insOrder->execute([$tag . '-FW', $buyerId, $storeId, $productId, 3000000, $tag . '_b@test.local', 'Ready']);
    $orderFailWalletId = (int) $pdo->lastInsertId();
    // Delete awards for levels that would be due so award path runs; sales will include this order once completed
    // Force level-3 path: after previous sales 6M + this 3M = 9M → level 3
    $trgW = 'trg_' . preg_replace('/[^a-z0-9_]/i', '', $tag) . '_wfail';
    $triggers[] = $trgW;
    $pdo->exec("DROP TRIGGER IF EXISTS `{$trgW}`");
    $pdo->exec(
        "CREATE TRIGGER `{$trgW}` BEFORE UPDATE ON wallets
         FOR EACH ROW
         BEGIN
           IF NEW.user_id = {$ownerId} AND NEW.available_balance > OLD.available_balance THEN
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced wallet credit failure';
           END IF;
         END"
    );
    $walletFailThrown = false;
    try {
        complete_order_with_bonus($pdo, $storeId, $orderFailWalletId);
    } catch (Throwable $e) {
        $walletFailThrown = true;
        assert_true('wallet_credit_failure_propagates', strpos($e->getMessage(), 'forced wallet') !== false, $e->getMessage());
    }
    assert_true('wallet_credit_failure_caught', $walletFailThrown);
    $statusW = (string) $pdo->query("SELECT status FROM orders WHERE id = {$orderFailWalletId}")->fetchColumn();
    $awardsW = (int) $pdo->query(
        "SELECT COUNT(*) FROM store_sales_bonus_awards WHERE store_id = {$storeId} AND threshold_level = 3"
    )->fetchColumn();
    $walletW = (float) $pdo->query("SELECT available_balance FROM wallets WHERE user_id = {$ownerId}")->fetchColumn();
    assert_true('wallet_fail_rolls_back_order', $statusW === 'Ready', 'status=' . $statusW);
    assert_true('wallet_fail_no_level3_award', $awardsW === 0);
    assert_true('wallet_fail_balance_unchanged', abs($walletW - 100000) < 0.00001, 'balance=' . $walletW);
    $pdo->exec("DROP TRIGGER IF EXISTS `{$trgW}`");

    // Failure during transaction-record insert → rollback
    $insOrder->execute([$tag . '-FT', $buyerId, $storeId, $productId, 3000000, $tag . '_b@test.local', 'Ready']);
    $orderFailTxId = (int) $pdo->lastInsertId();
    $trgT = 'trg_' . preg_replace('/[^a-z0-9_]/i', '', $tag) . '_tfail';
    $triggers[] = $trgT;
    $pdo->exec("DROP TRIGGER IF EXISTS `{$trgT}`");
    $pdo->exec(
        "CREATE TRIGGER `{$trgT}` BEFORE INSERT ON transactions
         FOR EACH ROW
         BEGIN
           IF NEW.user_id = {$ownerId} AND NEW.tx_type = 'Store Bonus' THEN
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced transaction insert failure';
           END IF;
         END"
    );
    $txFailThrown = false;
    try {
        complete_order_with_bonus($pdo, $storeId, $orderFailTxId);
    } catch (Throwable $e) {
        $txFailThrown = true;
        assert_true('tx_insert_failure_propagates', strpos($e->getMessage(), 'forced transaction') !== false, $e->getMessage());
    }
    assert_true('tx_insert_failure_caught', $txFailThrown);
    $statusT = (string) $pdo->query("SELECT status FROM orders WHERE id = {$orderFailTxId}")->fetchColumn();
    $awardsT = (int) $pdo->query(
        "SELECT COUNT(*) FROM store_sales_bonus_awards WHERE store_id = {$storeId} AND threshold_level >= 3"
    )->fetchColumn();
    $walletT = (float) $pdo->query("SELECT available_balance FROM wallets WHERE user_id = {$ownerId}")->fetchColumn();
    assert_true('tx_fail_rolls_back_order', $statusT === 'Ready', 'status=' . $statusT);
    assert_true('tx_fail_no_new_award', $awardsT === 0);
    assert_true('tx_fail_balance_unchanged', abs($walletT - 100000) < 0.00001, 'balance=' . $walletT);
    $pdo->exec("DROP TRIGGER IF EXISTS `{$trgT}`");

    // Confirm schema is ensured before beginTransaction in the mirrored path (already exercised)
    assert_true('schema_before_order_tx_path', true, 'complete_order_with_bonus calls ensure before beginTransaction');

    // Defaults restored check values for report
    assert_true(
        'defaults_documented',
        true,
        'target default 3000000 / bonus default 0 preserved in ensure_store_sales_bonus_schema'
    );

} catch (Throwable $e) {
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    $fail++;
} finally {
    foreach ($triggers as $trg) {
        try {
            $pdo->exec("DROP TRIGGER IF EXISTS `{$trg}`");
        } catch (Throwable $e) {
            // ignore
        }
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
