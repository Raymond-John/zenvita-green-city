<?php
require __DIR__ . '/bootstrap.php';
$user = require_user();
if (($user['role'] ?? '') === 'member') {
    ensure_award_rank_schema();
    sync_award_ranks_by_generation();
    $user = current_user() ?: $user;
}
json_out(['ok' => true, 'user' => $user]);
