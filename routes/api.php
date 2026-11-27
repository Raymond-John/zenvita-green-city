<?php

/**
 * API route map (core PHP — each path is a file under public/api/).
 * This is documentation + a single source of truth, not a Laravel router.
 */

return [
    'auth' => [
        'POST /api/login.php' => 'Sign in (member | admin | store)',
        'POST /api/logout.php' => 'Sign out',
        'GET  /api/me.php' => 'Current session user',
    ],
    'member' => [
        'GET  /api/dashboard.php' => 'Member dashboard payload',
        'GET|POST /api/profile.php' => 'Profile',
        'GET|POST /api/orders.php' => 'Product catalog + order list',
        'POST /api/order.php' => 'Place order and start Paystack payment',
        'POST /api/order-pay.php' => 'Resume Paystack payment for an unpaid order',
        'POST /api/withdrawal.php' => 'Request withdrawal',
        'POST /api/password.php' => 'Change password',
        'POST /api/add-user.php' => 'Register downline',
        'POST /api/upgrade.php' => 'Package upgrade',
    ],
    'portal' => [
        'GET  /api/admin.php' => 'Portal office payload',
        'POST /api/admin-settings.php' => 'Save settings (incl. Hostinger SMTP)',
        'POST /api/admin-fund-wallet.php' => 'Fund member wallet (+ email)',
        'POST /api/admin-product.php' => 'Save/delete product (+ image)',
        'GET  /api/admin-products.php' => 'List products',
        'POST /api/admin-member.php' => 'Member admin actions',
        'POST /api/admin-package.php' => 'Package CRUD',
        'POST /api/admin-store.php' => 'Store CRUD',
        'POST /api/admin-withdrawal.php' => 'Approve/reject withdrawals',
        'GET  /api/admin-orders.php' => 'Portal orders/stores',
        'GET  /api/admin-payments.php' => 'Registration payments',
        'POST /api/admin-pairing.php' => 'Trigger pairing',
        'POST /api/admin-landing-feature.php' => 'Homepage features',
    ],
    'store' => [
        'GET  /api/store.php' => 'Store dashboard',
        'POST /api/store-order.php' => 'Update store order',
    ],
    'payments' => [
        'POST /api/paystack-init.php' => 'Start Paystack registration payment',
        'GET  /api/paystack-callback.php' => 'Paystack registration callback',
        'GET  /api/paystack-order-callback.php' => 'Paystack product-order callback',
        'GET  /api/paystack-status.php' => 'Payment status',
        'POST /api/registration-proof.php' => 'Upload bank/cash payment receipt',
        'POST /api/admin-payment-review.php' => 'Approve/reject transfer receipts',
    ],
];
