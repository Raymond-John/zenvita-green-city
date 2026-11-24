<?php

/**
 * Hostinger webmail SMTP defaults.
 * Credentials are stored in portal Settings (database), not here.
 *
 * Hostinger outgoing mail:
 *   Host: smtp.hostinger.com
 *   Port: 465 + SSL  (recommended)
 *   or:   587 + TLS
 *   Username: full mailbox address (e.g. noreply@yourdomain.com)
 *   Password: that mailbox password
 */
return [
    'provider' => 'hostinger',
    'host' => 'smtp.hostinger.com',
    'port' => 465,
    'encryption' => 'ssl',
    'from_name' => 'Cellvigor Biorevive LTD',
];
