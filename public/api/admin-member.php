<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Use POST.'], 405);
}

require_user('admin');
$body = request_body();
$code = trim((string) ($body['memberCode'] ?? ''));
$name = trim((string) ($body['name'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$packageName = trim((string) ($body['package'] ?? ''));
$status = strtolower(trim((string) ($body['status'] ?? '')));

if ($code === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Enter the member name and a valid email.'], 422);
}
if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
    json_out(['ok' => false, 'error' => 'Choose a valid status.'], 422);
}

$pdo = db();
ensure_award_rank_schema($pdo);

$member = $pdo->prepare('SELECT id, parent_id FROM users WHERE member_code = ? AND role = \'member\'');
$member->execute([$code]);
$memberRow = $member->fetch();
if (!$memberRow) {
    json_out(['ok' => false, 'error' => 'That member was not found.'], 404);
}
$userId = (int) $memberRow['id'];

$package = $pdo->prepare('SELECT id, pv FROM packages WHERE name = ?');
$package->execute([$packageName]);
$packageRow = $package->fetch();
if (!$packageRow) {
    json_out(['ok' => false, 'error' => 'Choose a package from the list.'], 422);
}

$taken = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
$taken->execute([$email, $userId]);
if ($taken->fetch()) {
    json_out(['ok' => false, 'error' => 'That email is already used by another account.'], 422);
}

$parts = preg_split('/\s+/', $name, 2);
$first = $parts[0];
$last = $parts[1] ?? '';

// Award rank stays automated from generation (not manually overridden here).
$generation = 0;
if (!empty($memberRow['parent_id'])) {
    $genStmt = $pdo->prepare('SELECT parent_id FROM users WHERE id = ?');
    $walk = (int) $memberRow['parent_id'];
    $generation = 1;
    $guard = 0;
    while ($walk > 0 && $guard < 50) {
        $genStmt->execute([$walk]);
        $nextParent = $genStmt->fetchColumn();
        if ($nextParent === false || $nextParent === null || (int) $nextParent < 1) {
            break;
        }
        $generation++;
        $walk = (int) $nextParent;
        $guard++;
    }
}
$awardRank = award_rank_for_generation($generation);

$pdo->prepare(
    'UPDATE users
     SET first_name = ?, last_name = ?, email = ?, phone = ?, package_id = ?, personal_pv = ?, award_rank = ?, status = ?
     WHERE id = ?'
)->execute([$first, $last, $email, $phone, $packageRow['id'], $packageRow['pv'], $awardRank, $status, $userId]);

json_out(['ok' => true, 'awardRank' => $awardRank, 'generation' => $generation]);
