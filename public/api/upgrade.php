<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

$user = require_user('member');
$body = request_body();
$targetKey = strtolower(trim((string) ($body['packageKey'] ?? $body['package'] ?? '')));

if ($targetKey === '') {
    json_out(['ok' => false, 'error' => 'Please select an upgrade package.'], 422);
}

$pdo = db();

// Fetch current package
$currStmt = $pdo->prepare(
    'SELECT p.id, p.code, p.name, p.price, p.pv, p.direct_bonus
     FROM packages p
     WHERE p.id = ?'
);
$currStmt->execute([$user['package_id']]);
$currentPkg = $currStmt->fetch();

// Fetch target package
$targetStmt = $pdo->prepare(
    'SELECT p.id, p.code, p.name, p.price, p.pv, p.direct_bonus
     FROM packages p
     WHERE p.code = ? OR LOWER(p.name) = ?
     LIMIT 1'
);
$targetStmt->execute([$targetKey, $targetKey]);
$targetPkg = $targetStmt->fetch();

if (!$targetPkg) {
    json_out(['ok' => false, 'error' => 'Target package not found.'], 404);
}

$currentPrice = (float) ($currentPkg['price'] ?? 0);
$targetPrice = (float) $targetPkg['price'];

if ($targetPrice <= $currentPrice) {
    json_out(['ok' => false, 'error' => 'You can only upgrade to a higher-tier package.'], 422);
}

$priceDiff = round($targetPrice - $currentPrice, 2);
$currentPv = (int) ($currentPkg['pv'] ?? 0);
$targetPv = (int) $targetPkg['pv'];
$pvDiff = max(0, $targetPv - $currentPv);

$pdo->beginTransaction();
try {
    // Lock wallet and check available balance
    $wStmt = $pdo->prepare('SELECT available_balance FROM wallets WHERE user_id = ? FOR UPDATE');
    $wStmt->execute([$user['id']]);
    $available = (float) ($wStmt->fetchColumn() ?: 0);

    if ($available < $priceDiff) {
        $pdo->rollBack();
        json_out([
            'ok' => false,
            'error' => 'Insufficient wallet balance. You need ₦' . number_format($priceDiff, 2) . ' to upgrade, but your available balance is ₦' . number_format($available, 2) . '.'
        ], 422);
    }

    // Deduct price difference from wallet
    $pdo->prepare('UPDATE wallets SET available_balance = available_balance - ? WHERE user_id = ?')
        ->execute([$priceDiff, $user['id']]);

    // Record upgrade deduction transaction
    $refUpgrade = 'TX-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    $pdo->prepare(
        'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
         VALUES (?, ?, \'Package\', ?, ?, \'Approved\', CURDATE())'
    )->execute([
        $user['id'],
        $refUpgrade,
        "Package upgrade to {$targetPkg['name']}",
        -$priceDiff
    ]);

    // Update member package and personal PV
    $pdo->prepare('UPDATE users SET package_id = ?, personal_pv = personal_pv + ? WHERE id = ?')
        ->execute([$targetPkg['id'], $pvDiff, $user['id']]);

    // Add incremental PV to upline binary legs
    if (!empty($user['parent_id']) && !empty($user['binary_side']) && $pvDiff > 0) {
        add_leg_pv($pdo, (int) $user['parent_id'], (string) $user['binary_side'], $pvDiff);
        process_upline_pairing($pdo, (int) $user['parent_id']);
    }

    // Pay sponsor upgrade commission
    if (!empty($user['sponsor_id'])) {
        $sponsorId = (int) $user['sponsor_id'];
        $directRate = (float) setting('direct_rate', '30');
        $upgradeBonus = round($priceDiff * ($directRate / 100), 2);
        $memberName = trim($user['first_name'] . ' ' . $user['last_name']);

        if ($upgradeBonus > 0) {
            $pdo->prepare(
                'INSERT INTO commissions (user_id, source_label, commission_type, package_name, pv, amount, status, earned_on)
                 VALUES (?, ?, \'Upgrade\', ?, ?, ?, \'Approved\', CURDATE())'
            )->execute([$sponsorId, $memberName, $targetPkg['name'], $pvDiff, $upgradeBonus]);

            $refBonus = 'TX-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $pdo->prepare(
                'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
                 VALUES (?, ?, \'Commission\', ?, ?, \'Approved\', CURDATE())'
            )->execute([$sponsorId, $refBonus, "Upgrade referral bonus ({$targetPkg['name']})", $upgradeBonus]);

            $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?')
                ->execute([$upgradeBonus, $sponsorId]);
        }

        // Pay 5-generation indirect upgrade bonus on price difference
        $indirectRates = [2, 1, 1, 0.5, 0.5];
        $uplineStmt = $pdo->prepare('SELECT sponsor_id FROM users WHERE id = ?');
        $statusStmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
        $currSponsor = $sponsorId;

        foreach ($indirectRates as $idx => $rate) {
            $uplineStmt->execute([$currSponsor]);
            $ancestorId = (int) $uplineStmt->fetchColumn();
            if ($ancestorId < 1) {
                break;
            }
            $statusStmt->execute([$ancestorId]);
            $ancestorStatus = (string) $statusStmt->fetchColumn();
            $indBonus = round($priceDiff * ($rate / 100), 2);

            if ($ancestorStatus !== 'suspended' && $indBonus > 0) {
                $pdo->prepare(
                    'INSERT INTO commissions (user_id, source_label, commission_type, package_name, pv, amount, status, earned_on)
                     VALUES (?, ?, \'Indirect Referral\', ?, ?, ?, \'Approved\', CURDATE())'
                )->execute([$ancestorId, $memberName, $targetPkg['name'], $pvDiff, $indBonus]);

                $indRef = sprintf('ZGV-U%d-%05d-%05d', $idx + 1, (int) $user['id'], $ancestorId);
                $pdo->prepare(
                    'INSERT INTO transactions (user_id, reference, tx_type, description, amount, status, tx_date)
                     VALUES (?, ?, \'Commission\', ?, ?, \'Approved\', CURDATE())'
                )->execute([$ancestorId, $indRef, "Indirect upgrade bonus ({$targetPkg['name']})", $indBonus]);

                $pdo->prepare('UPDATE wallets SET available_balance = available_balance + ? WHERE user_id = ?')
                    ->execute([$indBonus, $ancestorId]);
            }
            $currSponsor = $ancestorId;
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['ok' => false, 'error' => 'Upgrade could not be completed: ' . $e->getMessage()], 500);
}

json_out([
    'ok' => true,
    'message' => "Successfully upgraded to {$targetPkg['name']}!",
    'package' => $targetPkg['name'],
    'pvAdded' => $pvDiff,
    'pricePaid' => $priceDiff,
    'newBalance' => $available - $priceDiff,
]);

