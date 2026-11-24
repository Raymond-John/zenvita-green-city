<?php

/**
 * Lightweight SMTP mailer (no Composer). Uses portal SMTP settings.
 */

function smtp_config(): array
{
    // Defaults match Hostinger webmail: smtp.hostinger.com:465 (SSL)
    $host = trim(setting('smtp_host', 'smtp.hostinger.com'));
    $port = (int) setting('smtp_port', '465');
    $encryption = strtolower(trim(setting('smtp_encryption', 'ssl')));
    if ($host === '') {
        $host = 'smtp.hostinger.com';
    }
    if ($port < 1) {
        $port = 465;
    }
    if ($encryption === '') {
        $encryption = 'ssl';
    }
    return [
        'provider' => trim(setting('smtp_provider', 'hostinger')) ?: 'hostinger',
        'host' => $host,
        'port' => $port,
        'encryption' => $encryption,
        'username' => trim(setting('smtp_username', '')),
        'password' => (string) setting('smtp_password', ''),
        'from_email' => trim(setting('smtp_from_email', setting('support_email', ''))),
        'from_name' => trim(setting('smtp_from_name', setting('site_name', 'Cellvigor Biorevive LTD'))),
    ];
}

function smtp_is_configured(): bool
{
    $cfg = smtp_config();
    // Hostinger webmail requires authenticated SMTP (full mailbox + password).
    return $cfg['host'] !== ''
        && $cfg['port'] > 0
        && $cfg['username'] !== ''
        && $cfg['password'] !== ''
        && $cfg['from_email'] !== ''
        && filter_var($cfg['from_email'], FILTER_VALIDATE_EMAIL);
}

function app_public_url(string $path = ''): string
{
    $base = rtrim((string) (require dirname(__DIR__, 2) . '/config/app.php')['url'] ?? '', '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        // /.../api → project public root
        $base = $scheme . '://' . $host . preg_replace('#/api$#', '', $script);
    }
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function send_mail(string $to, string $subject, string $htmlBody, string $textBody = ''): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email.'];
    }
    if (!smtp_is_configured()) {
        return ['ok' => false, 'error' => 'SMTP is not configured in portal Settings.'];
    }

    $cfg = smtp_config();
    if ($textBody === '') {
        $textBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody)), ENT_QUOTES, 'UTF-8'));
    }

    $boundary = 'b_' . bin2hex(random_bytes(8));
    $fromName = encode_mail_header($cfg['from_name']);
    $subjectEnc = encode_mail_header($subject);
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $fromName . ' <' . $cfg['from_email'] . '>',
        'Reply-To: ' . $cfg['from_email'],
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: Zenvita-Mailer',
    ];

    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($textBody))
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($htmlBody))
        . '--' . $boundary . "--\r\n";

    try {
        smtp_send_raw($cfg, $to, $subjectEnc, implode("\r\n", $headers), $body);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function encode_mail_header(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_send_raw(array $cfg, string $to, string $subject, string $headers, string $body): void
{
    $host = $cfg['host'];
    $port = (int) $cfg['port'];
    $encryption = $cfg['encryption'];
    $timeout = 30;

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException('Could not connect to SMTP server: ' . $errstr);
    }
    stream_set_timeout($socket, $timeout);

    smtp_expect($socket, [220]);
    $ehloHost = preg_replace('/:\d+$/', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
    smtp_command($socket, 'EHLO ' . $ehloHost, [250]);

    if ($encryption === 'tls') {
        smtp_command($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP STARTTLS failed.');
        }
        smtp_command($socket, 'EHLO ' . $ehloHost, [250]);
    }

    if ($cfg['username'] !== '') {
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($cfg['username']), [334]);
        smtp_command($socket, base64_encode($cfg['password']), [235]);
    }

    smtp_command($socket, 'MAIL FROM:<' . $cfg['from_email'] . '>', [250]);
    smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
    smtp_command($socket, 'DATA', [354]);

    $message = 'Subject: ' . $subject . "\r\n"
        . 'To: <' . $to . ">\r\n"
        . $headers . "\r\n\r\n"
        . $body;
    // Dot-stuff lines that start with .
    $message = preg_replace('/^\./m', '..', $message);
    fwrite($socket, $message . "\r\n.\r\n");
    smtp_expect($socket, [250]);
    smtp_command($socket, 'QUIT', [221, 250]);
    fclose($socket);
}

function smtp_command($socket, string $command, array $okCodes): void
{
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $okCodes);
}

function smtp_expect($socket, array $okCodes): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException('SMTP error (' . $code . '): ' . trim($response));
    }
    return $response;
}

function email_layout(string $title, string $innerHtml): string
{
    $site = htmlspecialchars(setting('site_name', 'Cellvigor Biorevive LTD'), ENT_QUOTES, 'UTF-8');
    $logo = htmlspecialchars(app_public_url('assets/images/cellvigor-logo.png'), ENT_QUOTES, 'UTF-8');
    $year = date('Y');
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f3f6f2;font-family:Segoe UI,Arial,sans-serif;color:#1a2e1a;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6f2;padding:32px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(26,46,26,.08);">'
        . '<tr><td style="background:linear-gradient(135deg,#1f5d3a,#2f7a4d);padding:24px 28px;text-align:center;">'
        . '<img src="' . $logo . '" alt="' . $site . '" width="56" height="56" style="display:inline-block;border-radius:12px;background:#fff;padding:6px;">'
        . '<div style="margin-top:12px;color:#fff;font-size:18px;font-weight:600;letter-spacing:.04em;">' . $site . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 28px 8px;font-size:22px;font-weight:650;color:#163222;">'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</td></tr>'
        . '<tr><td style="padding:8px 28px 28px;font-size:15px;line-height:1.6;color:#334433;">'
        . $innerHtml
        . '</td></tr>'
        . '<tr><td style="padding:18px 28px;background:#f7faf6;font-size:12px;color:#6b7b6b;text-align:center;">'
        . '© ' . $year . ' ' . $site . '. This is an automated message.'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function send_wallet_funded_email(array $user, float $amount, string $reference, string $note, float $newBalance): array
{
    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '') {
        return ['ok' => false, 'error' => 'Member has no email address.'];
    }

    $name = trim(((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? '')));
    if ($name === '') {
        $name = (string) ($user['username'] ?? 'Member');
    }
    $memberCode = (string) ($user['member_code'] ?? '');
    $site = setting('site_name', 'Cellvigor Biorevive LTD');
    $amountFmt = '₦' . number_format($amount, 2);
    $balanceFmt = '₦' . number_format($newBalance, 2);
    $safeNote = htmlspecialchars($note !== '' ? $note : 'Wallet deposit', ENT_QUOTES, 'UTF-8');

    $inner = '<p style="margin:0 0 16px;">Hello <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
        . '<p style="margin:0 0 16px;">Your wallet has been credited by the ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . ' portal.</p>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 18px;border:1px solid #e2ebe2;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;width:40%;">Amount credited</td>'
        . '<td style="padding:12px 16px;font-weight:700;color:#1f5d3a;font-size:18px;">' . htmlspecialchars($amountFmt, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">New balance</td>'
        . '<td style="padding:12px 16px;font-weight:600;">' . htmlspecialchars($balanceFmt, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">Reference</td>'
        . '<td style="padding:12px 16px;font-family:Consolas,monospace;">' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . ($memberCode !== ''
            ? '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">Member ID</td>'
              . '<td style="padding:12px 16px;">' . htmlspecialchars($memberCode, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            : '')
        . '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">Note</td>'
        . '<td style="padding:12px 16px;">' . $safeNote . '</td></tr>'
        . '</table>'
        . '<p style="margin:0;">You can sign in to your member dashboard to view this deposit.</p>';

    $html = email_layout('Wallet funded', $inner);
    $subject = 'Wallet credited — ' . $site . ' (' . $amountFmt . ')';
    return send_mail($email, $subject, $html);
}

function login_client_meta(): array
{
    $ip = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($ip !== '') {
        $ip = trim(explode(',', $ip)[0]);
    }
    if ($ip === '') {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
    }
    $agent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device'));
    if (mb_strlen($agent) > 220) {
        $agent = mb_substr($agent, 0, 217) . '...';
    }
    $now = new DateTimeImmutable('now', app_timezone());
    return [
        'ip' => $ip !== '' ? $ip : 'Unknown',
        'agent' => $agent !== '' ? $agent : 'Unknown device',
        'when' => $now->format('d M Y, h:i A') . ' (WAT)',
    ];
}

function login_role_label(string $role): string
{
    return match (strtolower($role)) {
        'admin' => 'Portal admin',
        'store' => 'Store account',
        default => 'Member',
    };
}

function admin_alert_emails(): array
{
    $emails = [];
    $support = trim(setting('support_email', ''));
    if ($support !== '' && filter_var($support, FILTER_VALIDATE_EMAIL)) {
        $emails[strtolower($support)] = $support;
    }
    try {
        $rows = db()->query(
            "SELECT email FROM users
             WHERE role = 'admin' AND status = 'active'
               AND email IS NOT NULL AND email <> ''"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[strtolower($email)] = $email;
            }
        }
    } catch (Throwable $e) {
        // Keep support email only if the query fails.
    }
    return array_values($emails);
}

function build_login_alert_html(array $user, array $meta, string $audience): string
{
    $site = setting('site_name', 'Cellvigor Biorevive LTD');
    $name = trim(((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? '')));
    if ($name === '') {
        $name = (string) ($user['username'] ?? 'Account');
    }
    $roleLabel = login_role_label((string) ($user['role'] ?? 'member'));
    $username = (string) ($user['username'] ?? '');
    $memberCode = (string) ($user['member_code'] ?? '');
    $email = (string) ($user['email'] ?? '');

    if ($audience === 'self') {
        $intro = '<p style="margin:0 0 16px;">Hello <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p style="margin:0 0 16px;">Your ' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8')
            . ' account just signed in to ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '.</p>';
        $title = 'New sign-in on your account';
        $footer = '<p style="margin:16px 0 0;">If this was you, no action is needed. If you did not sign in, change your password and contact support immediately.</p>';
    } else {
        $intro = '<p style="margin:0 0 16px;">Hello Admin,</p>'
            . '<p style="margin:0 0 16px;">A <strong>' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8')
            . '</strong> account just signed in to ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '.</p>';
        $title = 'Login alert — ' . $roleLabel;
        $footer = '<p style="margin:16px 0 0;">This is an automated security notice from the portal.</p>';
    }

    $inner = $intro
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 18px;border:1px solid #e2ebe2;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;width:38%;">Account</td>'
        . '<td style="padding:12px 16px;font-weight:600;">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . ($username !== ''
            ? '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">Username</td>'
              . '<td style="padding:12px 16px;">' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            : '')
        . ($memberCode !== ''
            ? '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">Member ID</td>'
              . '<td style="padding:12px 16px;">' . htmlspecialchars($memberCode, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            : '')
        . ($email !== ''
            ? '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">Email</td>'
              . '<td style="padding:12px 16px;">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            : '')
        . '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">Role</td>'
        . '<td style="padding:12px 16px;">' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">Time</td>'
        . '<td style="padding:12px 16px;">' . htmlspecialchars($meta['when'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">IP address</td>'
        . '<td style="padding:12px 16px;font-family:Consolas,monospace;">' . htmlspecialchars($meta['ip'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td style="padding:12px 16px;background:#f7faf6;font-size:12px;color:#6b7b6b;">Device</td>'
        . '<td style="padding:12px 16px;">' . htmlspecialchars($meta['agent'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '</table>'
        . $footer;

    return email_layout($title, $inner);
}

/**
 * Email the signed-in account and portal admins about a successful login.
 */
function notify_login_alerts(array $user): array
{
    if (!smtp_is_configured()) {
        return ['ok' => false, 'sent' => 0, 'error' => 'SMTP is not configured.'];
    }

    $meta = login_client_meta();
    $site = setting('site_name', 'Cellvigor Biorevive LTD');
    $roleLabel = login_role_label((string) ($user['role'] ?? 'member'));
    $sent = 0;
    $errors = [];
    $seen = [];

    $userEmail = trim((string) ($user['email'] ?? ''));
    if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        $key = strtolower($userEmail);
        $seen[$key] = true;
        $html = build_login_alert_html($user, $meta, 'self');
        $subject = 'Login alert — ' . $site;
        $result = send_mail($userEmail, $subject, $html);
        if (!empty($result['ok'])) {
            $sent++;
        } else {
            $errors[] = $result['error'] ?? 'Could not email the signed-in user.';
        }
    }

    $adminSubject = 'Login alert — ' . $roleLabel . ' signed in (' . $site . ')';
    $adminHtml = build_login_alert_html($user, $meta, 'admin');
    foreach (admin_alert_emails() as $adminEmail) {
        $key = strtolower($adminEmail);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $result = send_mail($adminEmail, $adminSubject, $adminHtml);
        if (!empty($result['ok'])) {
            $sent++;
        } else {
            $errors[] = $result['error'] ?? ('Could not email admin ' . $adminEmail);
        }
    }

    return [
        'ok' => $sent > 0,
        'sent' => $sent,
        'error' => $sent > 0 ? null : ($errors[0] ?? 'No login alert emails were sent.'),
    ];
}
