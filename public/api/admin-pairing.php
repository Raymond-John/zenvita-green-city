<?php
require __DIR__ . '/bootstrap.php';

require_user('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $members = $pdo->query("SELECT id FROM users WHERE role = 'member' AND status = 'active' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $settled = [];
    $pdo->beginTransaction();
    try {
        foreach ($members as $id) {
            $res = settle_binary_pairing($pdo, (int) $id);
            if ($res) {
                $settled[] = $res;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_out(['ok' => false, 'error' => 'Pairing settlement failed: ' . $e->getMessage()], 500);
    }
    json_out(['ok' => true, 'settled' => $settled, 'count' => count($settled)]);
}

// GET preview
$pairPv = (int) setting('pair_pv', '60');
if ($pairPv <= 0) {
    $pairPv = 60;
}

$rows = $pdo->query(
    "SELECT u.id, u.member_code, u.first_name, u.last_name, u.left_pv, u.right_pv,
            p.name AS package_name, p.pair_amount, p.daily_pairs, p.daily_cap
     FROM users u
     LEFT JOIN packages p ON p.id = u.package_id
     WHERE u.role = 'member' AND u.status = 'active'
     ORDER BY u.id"
)->fetchAll();

$pending = [];
foreach ($rows as $r) {
    $qual = is_binary_qualified($pdo, (int) $r['id']);
    $possible = (int) floor(min((int) $r['left_pv'], (int) $r['right_pv']) / $pairPv);
    if ($possible > 0) {
        $pending[] = [
            'id' => (int) $r['id'],
            'memberCode' => $r['member_code'],
            'name' => trim($r['first_name'] . ' ' . $r['last_name']),
            'package' => $r['package_name'],
            'leftPv' => (int) $r['left_pv'],
            'rightPv' => (int) $r['right_pv'],
            'qualified' => $qual,
            'pairs' => $possible,
            'pairPv' => $pairPv,
            'amount' => $possible * (float) $r['pair_amount'],
        ];
    }
}

json_out(['ok' => true, 'pending' => $pending, 'pairPv' => $pairPv]);

