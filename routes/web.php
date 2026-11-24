<?php

/**
 * Web route map (document root = /public).
 * Core PHP multipage app — Laravel-inspired folders only, not Laravel.
 *
 * Public
 *   GET  /                     → index.php (landing)
 *   GET  /login                → login.php (member)
 *   GET  /register             → register.php
 *
 * Portal (back office)
 *   GET  /portal/login
 *   GET  /portal/*
 *
 * Member dashboard
 *   GET  /dashboard/*
 *
 * Store
 *   GET  /store/login
 *   GET  /store/*
 */

return [
    'home' => '/',
    'member.login' => '/login',
    'member.register' => '/register',
    'portal.login' => '/portal/login',
    'portal.home' => '/portal/index',
    'store.login' => '/store/login',
];
