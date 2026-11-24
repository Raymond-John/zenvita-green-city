<?php
require __DIR__ . '/bootstrap.php';

$user = require_user('member');
$pdo = db();
ensure_eagle_lands_schema($pdo);

function flatten_team(array $node, array &$list): void
{
    foreach (['left', 'right'] as $side) {
        if (empty($node[$side]) || !is_array($node[$side])) {
            continue;
        }
        $list[] = $node[$side];
        flatten_team($node[$side], $list);
    }
}

$tree = member_tree((int) $user['id']);
$team = [];
flatten_team($tree, $team);

$wallet = $pdo->prepare('SELECT available_balance, eagle_lands_balance FROM wallets WHERE user_id = ?');
$wallet->execute([$user['id']]);
$walletRow = $wallet->fetch() ?: ['available_balance' => 0, 'eagle_lands_balance' => 0];
$available = (float) ($walletRow['available_balance'] ?? 0);
$eagleLands = (float) ($walletRow['eagle_lands_balance'] ?? 0);
$policy = withdrawal_policy($user);
$withdrawable = round($available * ((float) $policy['cashPercent'] / 100), 2);

$recent = $pdo->prepare(
    'SELECT earned_on, source_label, commission_type, package_name, pv, amount, status
     FROM commissions WHERE user_id = ? ORDER BY earned_on DESC, id DESC'
);
$recent->execute([$user['id']]);
$commissions = [];
$byType = [];
foreach ($recent as $row) {
    $amount = (float) $row['amount'];
    $byType[$row['commission_type']] = ($byType[$row['commission_type']] ?? 0) + $amount;
    $commissions[] = [
        'date' => date('d M Y', strtotime($row['earned_on'])),
        'member' => $row['source_label'],
        'type' => $row['commission_type'],
        'package' => $row['package_name'],
        'pv' => (int) $row['pv'],
        'amount' => $amount,
        'status' => $row['status'],
    ];
}

$tx = $pdo->prepare(
    'SELECT tx_date, reference, tx_type, description, amount, status
     FROM transactions WHERE user_id = ? ORDER BY tx_date DESC, id DESC'
);
$tx->execute([$user['id']]);
$transactions = [];
foreach ($tx as $row) {
    $transactions[] = [
        'date' => date('d M Y', strtotime($row['tx_date'])),
        'id' => $row['reference'],
        'type' => $row['tx_type'],
        'description' => $row['description'],
        'amount' => (float) $row['amount'],
        'status' => $row['status'],
    ];
}

$wd = $pdo->prepare(
    'SELECT requested_on, amount, reserved_amount, bank_name, account_number, account_name, status
     FROM withdrawals WHERE user_id = ? ORDER BY requested_on DESC, id DESC'
);
$wd->execute([$user['id']]);
$withdrawals = [];
foreach ($wd as $row) {
    $withdrawals[] = [
        'date' => date('d M Y', strtotime($row['requested_on'])),
        'amount' => (float) $row['amount'],
        'reserved' => (float) ($row['reserved_amount'] ?? 0),
        'bank' => $row['bank_name'],
        'account' => $row['account_number'],
        'name' => $row['account_name'],
        'status' => $row['status'],
    ];
}

$active = 0;
$leftCount = 0;
$rightCount = 0;
$direct = 0;
foreach ($team as $person) {
    if (($person['status'] ?? '') === 'active') {
        $active++;
    }
    if (($person['side'] ?? '') === 'left') {
        $leftCount++;
    }
    if (($person['side'] ?? '') === 'right') {
        $rightCount++;
    }
    if ((int) ($person['generation'] ?? 0) === 1) {
        $direct++;
    }
}

$pvSeries = $pdo->prepare(
    'SELECT DATE_FORMAT(earned_on, \'%b %Y\') AS label, SUM(pv) AS total
     FROM commissions WHERE user_id = ?
     GROUP BY YEAR(earned_on), MONTH(earned_on)
     ORDER BY MIN(earned_on)'
);
$pvSeries->execute([$user['id']]);
$pvChart = ['labels' => [], 'values' => []];
foreach ($pvSeries as $row) {
    $pvChart['labels'][] = $row['label'];
    $pvChart['values'][] = (int) $row['total'];
}

$teamSeries = $pdo->prepare(
    'SELECT DATE_FORMAT(created_at, \'%b %Y\') AS label, COUNT(*) AS total
     FROM users
     WHERE role = \'member\' AND parent_id IS NOT NULL
     GROUP BY YEAR(created_at), MONTH(created_at)
     ORDER BY MIN(created_at)'
);
$teamSeries->execute();
$teamChart = ['labels' => [], 'values' => []];
$running = 0;
foreach ($teamSeries as $row) {
    $running += (int) $row['total'];
    $teamChart['labels'][] = $row['label'];
    $teamChart['values'][] = $running;
}

ensure_package_description_column($pdo);
$packageRows = $pdo->query('SELECT code, name, description, price, bottles, pv, direct_bonus, daily_pairs, pair_amount, daily_cap FROM packages ORDER BY price');
$packages = [];
foreach ($packageRows as $row) {
    $packages[] = [
        'key' => $row['code'],
        'name' => $row['name'],
        'description' => (string) ($row['description'] ?? ''),
        'price' => (float) $row['price'],
        'bottles' => (int) $row['bottles'],
        'pv' => (int) $row['pv'],
        'bonus' => (float) $row['direct_bonus'],
        'matches' => (int) $row['daily_pairs'],
        'perMatch' => (float) $row['pair_amount'],
        'dailyMax' => (float) $row['daily_cap'],
    ];
}

json_out([
    'ok' => true,
    'user' => current_user(),
    'available' => $available,
    'withdrawable' => $withdrawable,
    'eagleLands' => $eagleLands,
    'withdrawalPolicy' => $policy,
    'min_withdrawal' => (float) setting('min_withdrawal', '1000'),
    'earnings_total' => array_sum($byType),
    'direct' => $byType['Direct Referral'] ?? 0,
    'indirect' => $byType['Indirect Referral'] ?? 0,
    'pairing' => $byType['Pairing'] ?? 0,
    'upgrade' => $byType['Upgrade'] ?? 0,
    'left_pv' => (int) $user['left_pv'],
    'right_pv' => (int) $user['right_pv'],
    'personal_pv' => (int) $user['personal_pv'],
    'team_count' => count($team),
    'active_count' => $active,
    'inactive_count' => count($team) - $active,
    'direct_referrals' => $direct,
    'left_count' => $leftCount,
    'right_count' => $rightCount,
    'member_code' => $user['member_code'],
    'tree' => $tree,
    'commissions' => $commissions,
    'transactions' => $transactions,
    'withdrawals' => $withdrawals,
    'packages' => $packages,
    'charts' => [
        'pv' => $pvChart,
        'team' => $teamChart,
    ],
]);
