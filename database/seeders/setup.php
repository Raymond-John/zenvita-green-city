<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=zenvita;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$memberHash = password_hash('Member12345', PASSWORD_DEFAULT);
$adminHash = password_hash('Admin12345', PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE role = 'member'")->execute([$memberHash]);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE role = 'admin'")->execute([$adminHash]);

$check = $pdo->prepare('SELECT username, password_hash, role FROM users WHERE username IN (\'adaeze\', \'admin\')');
$check->execute();
foreach ($check as $row) {
    $plain = $row['role'] === 'admin' ? 'Admin12345' : 'Member12345';
    if (!password_verify($plain, $row['password_hash'])) {
        fwrite(STDERR, "Password check failed for {$row['username']}\n");
        exit(1);
    }
}

$counts = $pdo->query('SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM commissions) AS commissions, (SELECT COUNT(*) FROM transactions) AS transactions')->fetch(PDO::FETCH_ASSOC);
echo "ready users={$counts['users']} commissions={$counts['commissions']} transactions={$counts['transactions']}\n";
