<?php
require __DIR__ . '/bootstrap.php';

require_user('admin');
$pdo = db();
ensure_eagle_lands_schema($pdo);

$rows = $pdo->query(
    'SELECT u.id, u.member_code, u.email, u.phone, u.first_name, u.last_name, u.status,
            u.personal_pv, u.left_pv, u.right_pv, u.binary_side, u.parent_id, u.created_at,
            p.name AS package_name, s.member_code AS sponsor_code, par.member_code AS parent_code
     FROM users u
     LEFT JOIN packages p ON p.id = u.package_id
     LEFT JOIN users s ON s.id = u.sponsor_id
     LEFT JOIN users par ON par.id = u.parent_id
     WHERE u.role = \'member\'
     ORDER BY u.created_at DESC, u.id DESC'
)->fetchAll();

$byId = [];
foreach ($rows as $row) {
    $byId[(int) $row['id']] = $row;
}

$generationOf = function (array $row) use ($byId): int {
    $count = 0;
    $current = $row;
    $guard = 0;
    while (!empty($current['parent_id']) && $guard < 30) {
        $count++;
        $parent = $byId[(int) $current['parent_id']] ?? null;
        if (!$parent) {
            break;
        }
        $current = $parent;
        $guard++;
    }
    return $count;
};

$members = [];
$rootId = null;
foreach ($rows as $row) {
    $generation = $generationOf($row);
    if ($generation === 0 && $rootId === null) {
        $rootId = (int) $row['id'];
    }
    $members[] = [
        'id' => $row['member_code'],
        'name' => trim($row['first_name'] . ' ' . $row['last_name']),
        'email' => $row['email'] ?: '',
        'phone' => $row['phone'] ?: '',
        'package' => $row['package_name'] ?: '',
        'pv' => (int) $row['personal_pv'],
        'status' => ucfirst($row['status']),
        'joined' => date('d M Y', strtotime($row['created_at'])),
        'joinedAt' => date('c', strtotime($row['created_at'])),
        'sponsor' => $row['sponsor_code'] ?: '—',
        'parent' => $row['parent_code'] ?: '—',
        'position' => $generation === 0 ? 'Root' : ucfirst((string) $row['binary_side']),
        'generation' => $generation,
        'leftPv' => (int) $row['left_pv'],
        'rightPv' => (int) $row['right_pv'],
    ];
}

$commissionRows = $pdo->query(
    'SELECT c.earned_on, c.source_label, c.commission_type, c.package_name, c.pv, c.amount, c.status,
            u.first_name, u.last_name
     FROM commissions c
     INNER JOIN users u ON u.id = c.user_id
     ORDER BY c.earned_on DESC, c.id DESC'
)->fetchAll();
$commissions = [];
$mix = [];
foreach ($commissionRows as $row) {
    $amount = (float) $row['amount'];
    $mix[$row['commission_type']] = ($mix[$row['commission_type']] ?? 0) + $amount;
    $commissions[] = [
        'date' => date('d M Y', strtotime($row['earned_on'])),
        'member' => trim($row['first_name'] . ' ' . $row['last_name']),
        'source' => $row['source_label'],
        'type' => $row['commission_type'],
        'package' => $row['package_name'],
        'pv' => (int) $row['pv'],
        'amount' => $amount,
        'status' => $row['status'],
    ];
}

$withdrawalRows = $pdo->query(
    'SELECT w.id, w.amount, w.reserved_amount, w.bank_name, w.account_number, w.account_name, w.status, w.requested_on,
            u.first_name, u.last_name
     FROM withdrawals w
     INNER JOIN users u ON u.id = w.user_id
     ORDER BY w.requested_on DESC, w.id DESC'
)->fetchAll();
$withdrawals = [];
$pending = 0;
foreach ($withdrawalRows as $row) {
    if ($row['status'] === 'Pending') {
        $pending++;
    }
    $withdrawals[] = [
        'id' => (int) $row['id'],
        'member' => trim($row['first_name'] . ' ' . $row['last_name']),
        'amount' => (float) $row['amount'],
        'reserved' => (float) ($row['reserved_amount'] ?? 0),
        'bank' => $row['bank_name'],
        'account' => $row['account_number'],
        'name' => $row['account_name'],
        'date' => date('d M Y', strtotime($row['requested_on'])),
        'status' => $row['status'],
    ];
}

$txRows = $pdo->query(
    'SELECT t.tx_date, t.reference, t.tx_type, t.description, t.amount, t.status, u.first_name, u.last_name
     FROM transactions t
     INNER JOIN users u ON u.id = t.user_id
     ORDER BY t.tx_date DESC, t.id DESC'
)->fetchAll();
$transactions = [];
foreach ($txRows as $row) {
    $transactions[] = [
        'date' => date('d M Y', strtotime($row['tx_date'])),
        'id' => $row['reference'],
        'member' => trim($row['first_name'] . ' ' . $row['last_name']),
        'type' => $row['tx_type'],
        'description' => $row['description'],
        'amount' => (float) $row['amount'],
        'status' => $row['status'],
    ];
}

ensure_package_description_column($pdo);
$packageRows = $pdo->query('SELECT code, name, description, price, bottles, pv, direct_bonus, daily_pairs, pair_amount, daily_cap FROM packages ORDER BY price')->fetchAll();
$packages = [];
foreach ($packageRows as $row) {
    $price = (float) $row['price'];
    $packages[] = [
        'key' => $row['code'],
        'name' => $row['name'],
        'description' => (string) ($row['description'] ?? ''),
        'price' => $price,
        'pv' => (int) $row['pv'],
        'bottles' => (int) $row['bottles'],
        'referral' => $price > 0 ? (int) round(((float) $row['direct_bonus'] / $price) * 100) : 0,
        'pairing' => (float) $row['pair_amount'],
        'dailyCap' => (float) $row['daily_cap'],
        'matches' => (int) $row['daily_pairs'],
        'bonus' => (float) $row['direct_bonus'],
    ];
}

$growthRows = $pdo->query(
    'SELECT DATE_FORMAT(created_at, \'%b %Y\') AS label, COUNT(*) AS total
     FROM users WHERE role = \'member\'
     GROUP BY YEAR(created_at), MONTH(created_at)
     ORDER BY MIN(created_at)'
)->fetchAll();
$growth = ['labels' => [], 'values' => []];
foreach ($growthRows as $row) {
    $growth['labels'][] = $row['label'];
    $growth['values'][] = (int) $row['total'];
}

$activeMembers = 0;
$totalPv = 0;
foreach ($members as $member) {
    $totalPv += $member['pv'];
    if ($member['status'] === 'Active') {
        $activeMembers++;
    }
}

$activity = [];
foreach (array_slice($transactions, 0, 4) as $row) {
    $activity[] = $row['date'] . ' · ' . $row['member'] . ' · ' . $row['description'];
}
if ($pending > 0) {
    array_unshift($activity, $pending . ' withdrawal ' . ($pending === 1 ? 'request is' : 'requests are') . ' waiting.');
}

json_out([
    'ok' => true,
    'members' => $members,
    'commissions' => $commissions,
    'withdrawals' => $withdrawals,
    'transactions' => $transactions,
    'packages' => $packages,
    'tree' => $rootId ? member_tree($rootId) : [],
    'settings' => (static function (): array {
        ensure_smtp_settings();
        return [
            'siteName' => setting('site_name', 'Cellvigor Biorevive LTD'),
            'currency' => setting('currency', 'NGN'),
            'supportEmail' => setting('support_email', ''),
            'directRate' => setting('direct_rate', '30'),
            'pairPv' => setting('pair_pv', '60'),
            'binaryQualification' => setting('binary_qualification', '1') === '1',
            'minWithdrawal' => setting('min_withdrawal', '1000'),
            'emailAlerts' => setting('email_alerts', '1') === '1',
            'smsAlerts' => setting('sms_alerts', '0') === '1',
            'paystackPublicKey' => setting('paystack_public_key', ''),
            'paystackSecretSet' => setting('paystack_secret_key', '') !== '',
            'smtpProvider' => setting('smtp_provider', 'hostinger') ?: 'hostinger',
            'smtpHost' => setting('smtp_host', 'smtp.hostinger.com') ?: 'smtp.hostinger.com',
            'smtpPort' => setting('smtp_port', '465') ?: '465',
            'smtpEncryption' => setting('smtp_encryption', 'ssl') ?: 'ssl',
            'smtpUsername' => setting('smtp_username', ''),
            'smtpPasswordSet' => setting('smtp_password', '') !== '',
            'smtpFromEmail' => setting('smtp_from_email', ''),
            'smtpFromName' => setting('smtp_from_name', 'Cellvigor Biorevive LTD'),
            'smtpConfigured' => smtp_is_configured(),
        ];
    })(),
    'stats' => [
        'members' => count($members),
        'active' => $activeMembers,
        'pv' => $totalPv,
        'commissions' => array_sum($mix),
        'pending' => $pending,
        'transactions' => count($transactions),
        'mix' => [
            $mix['Direct Referral'] ?? 0,
            $mix['Indirect Referral'] ?? 0,
            $mix['Pairing'] ?? 0,
            $mix['Upgrade'] ?? 0,
        ],
    ],
    'growth' => $growth,
    'activity' => $activity,
]);
