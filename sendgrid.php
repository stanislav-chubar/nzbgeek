<?php
/**
 * MPass - SendGrid Email Helper
 */

if (basename($_SERVER['PHP_SELF']) === 'sendgrid.php') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config.php';

/**
 * Send an email via SendGrid v3 API
 */
function sendgrid_send(string $to_email, string $to_name, string $subject, string $html_body): bool {
    $payload = [
        'personalizations' => [[
            'to' => [['email' => $to_email, 'name' => $to_name]],
            'subject' => $subject,
        ]],
        'from' => [
            'email' => SENDGRID_FROM_EMAIL,
            'name'  => SENDGRID_FROM_NAME,
        ],
        'content' => [[
            'type'  => 'text/html',
            'value' => $html_body,
        ]],
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SENDGRID_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code >= 200 && $http_code < 300;
}

/**
 * Send welcome email after registration
 */
function send_welcome_email(string $email, string $username, string $key): bool {
    $site = e(SITE_NAME);
    $user = e($username);
    $pass = e($key);
    $url = e(SITE_URL);

    $subject = "Welcome to {$site}";
    $html = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #2a2a2a; color: #ccc; padding: 30px; border-radius: 6px;">
    <h2 style="color: #00bfff;">Welcome to {$site} &amp; thank you for joining.</h2>
    <p><strong style="color: #fff;">Logon Details:</strong></p>
    <p>username: <strong style="color: #00bfff;">{$user}</strong></p>
    <p>user key: <strong style="color: #00bfff;">{$pass}</strong></p>
    <p>The first time you logon to <strong style="color: #fff;">{$site}</strong> please use the username and user key (password) provided.</p>
    <p>From <strong style="color: #ff6600;">my account</strong> you can set a personal password and enable 2FA authentication on your account if you wish.</p>
    <p><a href="{$url}/login.php" style="color: #00bfff;">Login here</a></p>
</div>
HTML;

    return sendgrid_send($email, $username, $subject, $html);
}

function send_recovery_email(string $email, string $username, string $new_key): bool {
    $site = e(SITE_NAME);
    $user = e($username);
    $pass = e($new_key);
    $url = e(SITE_URL);

    $subject = "{$site} - Account Recovery";
    $html = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #2a2a2a; color: #ccc; padding: 30px; border-radius: 6px;">
    <h2 style="color: #00bfff;">Account Recovery</h2>
    <p>Hi <strong style="color: #fff;">{$user}</strong>, your account credentials have been reset.</p>
    <p><strong style="color: #fff;">New Logon Details:</strong></p>
    <p>username: <strong style="color: #00bfff;">{$user}</strong></p>
    <p>user key: <strong style="color: #00bfff;">{$pass}</strong></p>
    <p>Please login and set a personal password from <strong style="color: #ff6600;">my account</strong>.</p>
    <p><a href="{$url}/login.php" style="color: #00bfff;">Login here</a></p>
</div>
HTML;

    return sendgrid_send($email, $username, $subject, $html);
}

function send_close_account_email(string $email, string $username, string $token): bool {
    $site = e(SITE_NAME);
    $user = e($username);
    $confirm_url = SITE_URL . '/account.php?action=confirm_close&token=' . urlencode($token);

    $subject = "{$site} - Account Closure Verification";
    $html = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #2a2a2a; color: #ccc; padding: 30px; border-radius: 6px;">
    <h2 style="color: #ff6600;">Account Closure Request</h2>
    <p>Hi <strong style="color: #fff;">{$user}</strong>, you have requested to close your account.</p>
    <p>Closing your account requires additional verification by you.</p>
    <p>Once approved all records are removed from our system.</p>
    <p><a href="{$confirm_url}" style="display: inline-block; padding: 12px 30px; background: #ff6600; color: #fff; text-decoration: none; border-radius: 4px; margin: 10px 0;">Confirm Account Closure</a></p>
    <p style="color: #999; font-size: 12px;">If you did not request this, please ignore this email.</p>
</div>
HTML;

    return sendgrid_send($email, $username, $subject, $html);
}
