<?php

/**
 * Web route map (document root = /public).
 *
 * Public
 *   GET  /                     → index.php (landing)
 *   GET  /login                → login.php (member)
 *   GET  /register             → register.php
 *
 * Portal (formerly /admin)
 *   GET  /portal/login         → portal/login.php
 *   GET  /portal/index         → portal/index.php
 *   GET  /portal/members       → portal/members.php
 *   GET  /portal/network       → portal/network.php
 *   GET  /portal/packages      → portal/packages.php
 *   GET  /portal/products      → portal/products.php
 *   GET  /portal/stores        → portal/stores.php
 *   GET  /portal/orders        → portal/orders.php
 *   GET  /portal/payments      → portal/payments.php
 *   GET  /portal/commissions   → portal/commissions.php
 *   GET  /portal/withdrawals   → portal/withdrawals.php
 *   GET  /portal/transactions  → portal/transactions.php
 *   GET  /portal/settings      → portal/settings.php
 *
 * Member dashboard
 *   GET  /dashboard/*          → dashboard/*.php
 *
 * Store
 *   GET  /store/login          → store/login.php
 *   GET  /store/*              → store/*.php
 *
 * API
 *   POST /api/login.php
 *   *    /api/*.php
 */

return [
    'portal.login' => '/portal/login',
    'portal.home' => '/portal/index',
    'member.login' => '/login',
    'store.login' => '/store/login',
];
