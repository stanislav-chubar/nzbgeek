<?php
/**
 * MPass - Registration Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sendgrid.php';
ensure_session();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1 || $step > 3) $step = 1;

if ($step === 2 && empty($_SESSION['terms_agreed'])) {
    header('Location: register.php?step=1');
    exit;
}
if ($step === 3 && empty($_SESSION['reg_username'])) {
    header('Location: register.php?step=1');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {

        if ($step === 1) {
            $_SESSION['terms_agreed'] = true;
            header('Location: register.php?step=2');
            exit;
        }

        if ($step === 2) {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($username === '') {
                $error = 'Username is required.';
            } elseif (strlen($username) < 3 || strlen($username) > 50) {
                $error = 'Username must be between 3 and 50 characters.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $error = 'Username can only contain letters, numbers, and underscores.';
            }

            if (!$error && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            }

            if (!$error) {
                $db = get_db();
                $check = $db->prepare('SELECT id FROM users WHERE username = ?');
                $check->execute([$username]);
                if ($check->fetch()) {
                    $error = 'This username is already taken.';
                }
            }

            if (!$error) {
                $key = generate_random_key();
                $password_hash = password_hash($key, PASSWORD_BCRYPT);

                $db = get_db();
                $status_stmt = $db->prepare('SELECT id FROM member_statuses WHERE name = ?');
                $status_stmt->execute(['active_trial']);
                $status_id = $status_stmt->fetchColumn();

                if (!$status_id) {
                    $error = 'System configuration error. Please contact an administrator.';
                } else {
                    $now = new DateTime('now', new DateTimeZone('UTC'));
                    $expires = clone $now;
                    $expires->modify('+' . TRIAL_HOURS . ' hours');

                    $insert = $db->prepare('
                        INSERT INTO users (username, email, password_hash, is_using_generated_password, status_id, registered_at, expires_at)
                        VALUES (?, ?, ?, 1, ?, ?, ?)
                    ');
                    $insert->execute([
                        $username, $email, $password_hash, $status_id,
                        $now->format('Y-m-d H:i:s'), $expires->format('Y-m-d H:i:s'),
                    ]);
                }

                if (!$error) {
                    $_SESSION['reg_username'] = $username;
                    $_SESSION['reg_key'] = $key;
                    unset($_SESSION['terms_agreed']);
                    try {
                        send_welcome_email($email, $username, $key);
                    } catch (\Throwable $e) {
                        // Email send is non-blocking
                    }

                    header('Location: register.php?step=3');
                    exit;
                }
            }
        }
    }
}

$reg_username = $_SESSION['reg_username'] ?? '';
$reg_key = $_SESSION['reg_key'] ?? '';

if ($step === 3) {
    unset($_SESSION['reg_username'], $_SESSION['reg_key']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #3e3e3e;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #fff;
        }
        a { text-decoration: none; }
        .auth-container {
            width: 100%;
            max-width: <?= $step === 1 ? '720px' : '440px' ?>;
            padding: 80px 20px 20px;
        }
        .auth-card {
            background: #1a1a1a;
            border: 1px solid #00bfff;
            box-shadow: 0 0 15px rgba(0, 191, 255, 0.3), 0 0 30px rgba(0, 191, 255, 0.1);
            border-radius: 6px;
            padding: <?= $step === 1 ? '90px 45px 35px' : '90px 45px 40px' ?>;
            text-align: center;
            position: relative;
        }
        .logo-wrapper {
            position: absolute;
            top: -75px;
            left: 50%;
            transform: translateX(-50%);
        }
        .logo-wrapper img {
            width: 150px;
            height: auto;
        }
        .flash {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .flash-error {
            background: rgba(255, 0, 0, 0.15);
            border: 1px solid #ff4444;
            color: #ff6666;
        }

        /* Step 1: Terms */
        .terms-heading {
            color: #fff;
            font-size: 15px;
            font-weight: bold;
            font-style: normal;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .terms-box {
            background: #222;
            border: 1px solid #555;
            border-radius: 3px;
            padding: 20px 25px;
            max-height: 300px;
            overflow-y: auto;
            text-align: left;
            margin-bottom: 25px;
            font-size: 14px;
            color: #999;
            line-height: 1.8;
        }
        .terms-box::-webkit-scrollbar {
            width: 8px;
        }
        .terms-box::-webkit-scrollbar-track {
            background: #333;
        }
        .terms-box::-webkit-scrollbar-thumb {
            background: #666;
            border-radius: 4px;
        }
        .terms-box h3 {
            color: #00bfff;
            font-size: 15px;
            margin-bottom: 15px;
        }
        .terms-box ul {
            list-style: disc;
            padding-left: 20px;
        }
        .terms-box li {
            margin-bottom: 8px;
        }

        /* Step 2: Form */
        .input-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }
        .input-row .icon {
            color: #888;
            font-size: 22px;
            width: 28px;
            text-align: center;
            flex-shrink: 0;
        }
        .input-row input {
            flex: 1;
            background: #555;
            border: 1px solid #666;
            padding: 11px 14px;
            color: #fff;
            font-size: 15px;
            border-radius: 2px;
            outline: none;
        }
        .input-row input::placeholder {
            color: #999;
        }
        .input-row input:focus {
            border-color: #00bfff;
        }

        /* Step 3: Success */
        .credential-label {
            color: #999;
            font-size: 15px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 8px;
        }
        .credential-value {
            color: #00bfff;
            font-size: 16px;
            font-weight: bold;
            word-break: break-all;
        }
        .record-notice {
            color: #999;
            font-size: 15px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .activation-text {
            color: #00bfff;
            font-size: 14px;
            margin-top: 10px;
            line-height: 1.6;
        }

        /* Shared */
        .btn {
            background: transparent;
            border: 1px solid #888;
            color: #fff;
            padding: 11px 50px;
            cursor: pointer;
            font-size: 15px;
            margin: 25px 0 20px;
            text-transform: lowercase;
            border-radius: 3px;
            transition: background 0.2s;
            display: inline-block;
        }
        .btn:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        a.btn {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="logo-wrapper">
                <img src="assets/logo.png" alt="<?= e(SITE_NAME) ?>">
            </div>

            <?php if ($error): ?>
                <div class="flash flash-error"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>

                <p class="terms-heading">By registering on this site you agree to the below policy</p>

                <div class="terms-box">
                    <h3>1.0 - General Rules</h3>
                    <ul>
                        <li>This site is for personal use only</li>
                        <li>Our services require an active subscription</li>
                        <li>Commercial use &amp; scraping will result in account removal without warning</li>
                        <li>Do not repost nzbs elsewhere</li>
                        <li>Do not post links to other nzb indexers</li>
                        <li>No links to streaming sites, warez, filesharing, p2p or crack sites</li>
                        <li>No serials, CD keys, keygens or cracks anywhere on the site, do not ask for them</li>
                        <li>No advertising or promoting services</li>
                        <li>Respect all members, regardless of your liking towards them</li>
                        <li>Adhere to our thumbs up/down usage</li>
                        <li>Adhere to our reporting usage</li>
                        <li>Usage of third party applications (Sonarr, Radarr etc) must be secure when exposed to the internet</li>
                    </ul>
                </div>

                <div class="terms-box">
                    <h3>2.0 - Usage</h3>
                    <ul>
                        <li>You may not use this site to transmit data in breach of copyright or any intellectual property rights</li>
                        <li>You may not pass your access details to any third parties, doing so will result in account removal</li>
                        <li>You may not make copies of the Site or our data, your are not permitted to screen scrape or capture any part of the Site</li>
                        <li>You are solely responsible for you use of the Site</li>
                        <li>You are not permitted to access this site or its services from multiple ip addresses simulataneously</li>
                        <li>You are not permitted to alter your ip address per request</li>
                        <li>The Site accepts no responsibility for any consequences resulting in your use of the Site</li>
                    </ul>
                </div>
                <form method="POST" action="register.php?step=1">
                    <?= csrf_input() ?>
                    <button type="submit" class="btn">agree</button>
                </form>

            <?php elseif ($step === 2): ?>
                <form method="POST" action="register.php?step=2">
                    <?= csrf_input() ?>
                    <div class="input-row">
                        <span class="icon"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" placeholder="user name" required autofocus
                               value="<?= e($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="input-row">
                        <span class="icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" placeholder="email address" required
                               value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn">register</button>
                </form>

            <?php elseif ($step === 3): ?>

                <p class="credential-label">Username</p>
                <p class="credential-value"><?= e($reg_username) ?></p>

                <p class="credential-label">User Key</p>
                <p class="credential-value"><?= e($reg_key) ?></p>

                <p class="record-notice">Record this information now!</p>

                <a href="login.php" class="btn">login</a>

                <p class="activation-text">
                    Please allow 5 mins for activation<br>
                    then logon with the username &amp; key
                </p>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>
