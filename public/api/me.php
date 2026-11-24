<?php
require __DIR__ . '/bootstrap.php';
$user = require_user();
json_out(['ok' => true, 'user' => $user]);
