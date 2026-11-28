<?php
/**
 * Concurrent placement worker (separate process).
 * Args: db_name parent_id side username ready_file go_file result_file
 */
declare(strict_types=1);

if ($argc < 7) {
    fwrite(STDERR, "usage: claim_seat_worker.php db parent side username ready go result\n");
    exit(2);
}

$dbName = $argv[1];
$parentId = (int) $argv[2];
$side = $argv[3];
$username = $argv[4];
$readyFile = $argv[5];
$goFile = $argv[6];
$resultFile = $argv[7] ?? ($argv[6] . '.out');

putenv('ZENVITA_DB_NAME=' . $dbName);
$_ENV['ZENVITA_DB_NAME'] = $dbName;

require dirname(__DIR__, 2) . '/bootstrap/app.php';

file_put_contents($readyFile, '1');
$deadline = microtime(true) + 10;
while (!is_file($goFile) && microtime(true) < $deadline) {
    usleep(20000);
}
if (!is_file($goFile)) {
    file_put_contents($resultFile, json_encode(['ok' => false, 'error' => 'go_timeout']));
    exit(1);
}

$pdo = db();
$out = ['ok' => false];
try {
    require_binary_placement_schema($pdo);
    $pdo->beginTransaction();
    $seat = open_seat($pdo, $parentId, $side);
    // Hold briefly so the sibling process overlaps while this TX is open.
    usleep(150000);
    $pdo->prepare(
        "INSERT INTO users (username, email, phone, password_hash, first_name, last_name, role, package_id, sponsor_id, parent_id, binary_side, status)
         VALUES (?, ?, '08001112222', 'x', 'W', 'T', 'member', 1, ?, ?, ?, 'active')"
    )->execute([
        $username,
        $username . '@example.test',
        $parentId,
        $seat['parent_id'],
        $seat['side'],
    ]);
    $id = (int) $pdo->lastInsertId();
    $pdo->commit();
    $out = [
        'ok' => true,
        'id' => $id,
        'parent_id' => (int) $seat['parent_id'],
        'side' => $seat['side'],
    ];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $driver = ($e instanceof PDOException && isset($e->errorInfo[1])) ? (int) $e->errorInfo[1] : 0;
    $out = [
        'ok' => false,
        'error' => $e->getMessage(),
        'driver' => $driver,
        'dup_key' => ($e instanceof PDOException && $driver === 1062) ? mysql_duplicate_key_name($e) : '',
    ];
}

file_put_contents($resultFile, json_encode($out));
exit(!empty($out['ok']) ? 0 : 1);
