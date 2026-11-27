<?php
require __DIR__ . '/bootstrap.php';

require_user('admin');
ensure_registration_payments_table();
$pdo = db();

$rows = $pdo->query(
    "SELECT rp.id, rp.reference, rp.email, rp.amount, rp.package_name, rp.status,
            rp.payment_method, rp.proof_path, rp.notes, rp.admin_note,
            rp.created_at, rp.funded_at, rp.reviewed_at, u.member_code, u.username,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS member_name,
            w.available_balance
     FROM registration_payments rp
     LEFT JOIN users u ON u.id = rp.user_id
     LEFT JOIN wallets w ON w.user_id = rp.user_id
     ORDER BY rp.id DESC
     LIMIT 200"
)->fetchAll();

$payments = array_map(static function (array $row): array {
    $proof = trim((string) ($row['proof_path'] ?? ''));
    return [
        'id' => (int) $row['id'],
        'reference' => $row['reference'],
        'email' => $row['email'],
        'amount' => (float) $row['amount'],
        'package' => $row['package_name'],
        'status' => $row['status'],
        'method' => $row['payment_method'] ?: 'paystack',
        'proofPath' => $proof !== '' ? ('../assets/' . ltrim($proof, '/')) : '',
        'notes' => $row['notes'] ?: '',
        'adminNote' => $row['admin_note'] ?: '',
        'createdAt' => $row['created_at'],
        'fundedAt' => $row['funded_at'],
        'reviewedAt' => $row['reviewed_at'],
        'memberCode' => $row['member_code'] ?: '',
        'username' => $row['username'] ?: '',
        'memberName' => trim((string) $row['member_name']),
        'walletBalance' => $row['available_balance'] !== null ? (float) $row['available_balance'] : null,
        'canReview' => $row['status'] === 'pending' && (
            ($row['payment_method'] ?? '') === 'transfer' || trim((string) ($row['proof_path'] ?? '')) !== ''
        ),
    ];
}, $rows);

json_out(['ok' => true, 'payments' => $payments]);
