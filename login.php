<?php
/**
 * MPass - Login Page
 */

require_once __DIR__ . '/config.php';
ensure_session();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
// Handle Login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } else {
            $db = get_db();
            $stmt = $db->prepare('
                SELECT u.*, ms.name AS status_name
                FROM users u
                JOIN member_statuses ms ON u.status_id = ms.id
                WHERE u.username = ?
            ');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (($user['status_name'] ?? '') === 'closed') {
                    $error = 'This account has been closed.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['last_regeneration'] = time();
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e(SITE_NAME) ?></title>
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
            max-width: 440px;
            padding: 80px 20px 20px;
        }
        .auth-card {
            background: #1a1a1a;
            border: 1px solid #00bfff;
            box-shadow: 0 0 15px rgba(0, 191, 255, 0.3), 0 0 30px rgba(0, 191, 255, 0.1);
            border-radius: 6px;
            padding: 90px 45px 40px;
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
        .flash-success {
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid #00cc44;
            color: #66ff88;
        }
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
        .btn {
            background: transparent;
            border: 1px solid #888;
            color: #fff;
            padding: 11px 50px;
            cursor: pointer;
            font-size: 15px;
            margin: 25px 0 30px;
            text-transform: lowercase;
            border-radius: 3px;
            transition: background 0.2s;
        }
        .btn:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .links {
            margin-bottom: 25px;
            font-size: 15px;
        }
        .links a {
            color: #00bfff;
        }
        .links a:hover {
            color: #4dd4ff;
        }
        .help-text {
            color: #888;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 10px;
            font-style: normal;
        }
        .help-text a {
            color: #888;
        }
        .help-text a:hover {
            color: #ccc;
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

            <?php if ($flash): ?>
                <div class="flash flash-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <?= csrf_input() ?>
                <div class="input-row">
                    <span class="icon"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" placeholder="user name" required autofocus
                           value="<?= e($_POST['username'] ?? '') ?>">
                </div>
                <div class="input-row">
                    <span class="icon"><i class="fas fa-key"></i></span>
                    <input type="password" name="password" placeholder="user key" required>
                </div>
                <button type="submit" class="btn">login</button>
            </form>

            <p class="links">
                <a href="register.php">register account?</a> | <a href="recover.php">recover account?</a>
            </p>

            <p class="help-text">
                lost your 2FA, lost your key, can't login?<br>
                <a href="recover.php">recover your account now</a>
            </p>
        </div>
    </div>
</body>
</html>
