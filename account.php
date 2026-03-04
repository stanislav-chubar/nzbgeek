<?php
/**
 * MPass - My Account Page (Protected)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/sendgrid.php';

$error = '';
$success = '';
$show_change_username = false;
$show_change_email = false;
$show_close_account = false;

if (isset($_GET['action']) && $_GET['action'] === 'confirm_close' && isset($_GET['token'])) {
    $token = $_GET['token'];
    $db = get_db();
    $stmt = $db->prepare('
        SELECT id FROM users
        WHERE id = ? AND account_close_token = ? AND account_close_token_expires > NOW()
    ');
    $stmt->execute([$current_user['id'], $token]);
    $valid = (bool)$stmt->fetch();

    if ($valid) {
        $status_stmt = $db->prepare('SELECT id FROM member_statuses WHERE name = ?');
        $status_stmt->execute(['closed']);
        $closed_id = $status_stmt->fetchColumn();

        $update = $db->prepare('
            UPDATE users SET status_id = ?, account_close_token = NULL, account_close_token_expires = NULL WHERE id = ?
        ');
        $update->execute([$closed_id, $current_user['id']]);

        session_unset();
        session_destroy();
        session_start();
        set_flash('success', 'Your account has been closed. All records will be removed from our system.');
        header('Location: login.php');
        exit;
    } else {
        $error = 'Invalid or expired closure verification link.';
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'change_username':
                $new_username = trim($_POST['new_username'] ?? '');
                if ($new_username === '') {
                    $error = 'Username is required.';
                } elseif (strlen($new_username) < 3 || strlen($new_username) > 50) {
                    $error = 'Username must be between 3 and 50 characters.';
                } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
                    $error = 'Username can only contain letters, numbers, and underscores.';
                } else {
                    $db = get_db();
                    $check = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
                    $check->execute([$new_username, $current_user['id']]);
                    $taken = (bool)$check->fetch();

                    if ($taken) {
                        $error = 'This username is already taken.';
                    } else {
                        $update = $db->prepare('UPDATE users SET username = ? WHERE id = ?');
                        $update->execute([$new_username, $current_user['id']]);
                        $_SESSION['username'] = $new_username;
                        $current_user['username'] = $new_username;
                        $success = 'Username updated successfully.';
                    }
                }
                if ($error) $show_change_username = true;
                break;

            case 'change_email':
                $new_email = trim($_POST['new_email'] ?? '');
                if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    $db = get_db();
                    $update = $db->prepare('UPDATE users SET email = ? WHERE id = ?');
                    $update->execute([$new_email, $current_user['id']]);
                    $current_user['email'] = $new_email;
                    $success = 'Email updated successfully.';
                }
                if ($error) $show_change_email = true;
                break;

            case 'request_close':
                $token = bin2hex(random_bytes(32));
                $expires = new DateTime('now', new DateTimeZone('UTC'));
                $expires->modify('+24 hours');

                $db = get_db();
                $update = $db->prepare('
                    UPDATE users SET account_close_token = ?, account_close_token_expires = ? WHERE id = ?
                ');
                $update->execute([$token, $expires->format('Y-m-d H:i:s'), $current_user['id']]);

                try {
                    send_close_account_email($current_user['email'], $current_user['username'], $token);
                } catch (\Throwable $e) {
                    // Email failed silently
                }
                $success = 'A verification email has been sent to your registered email address. Please check your inbox.';
                break;

            case 'show_change_username':
                $show_change_username = true;
                break;

            case 'show_change_email':
                $show_change_email = true;
                break;

            case 'show_close_account':
                $show_close_account = true;
                break;
        }
    }
}

// Calculations for display
$reg_formatted = format_date($current_user['registered_at']) . ' UTC';
$reg_ago = time_ago($current_user['registered_at']);
$expires_formatted = format_date($current_user['expires_at']) . ' UTC';
$countdown = countdown_to($current_user['expires_at']);
$masked = mask_email($current_user['email']);
$password_status = $current_user['is_using_generated_password'] ? 'currently using generated key' : 'personal password set';

// Status color
$status_color = '#ff6600';
if ($current_user['status_name'] === 'active_trial') $status_color = '#ff6600';
elseif ($current_user['status_name'] === 'active') $status_color = '#00cc44';
elseif ($current_user['status_name'] === 'staff') $status_color = '#00bfff';
elseif ($current_user['status_name'] === 'expired') $status_color = '#ff4444';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #2a2a2a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 15px;
            color: #ccc;
            min-height: 100vh;
        }
        a { text-decoration: none; }

        /* Top Navbar */
        .navbar-top {
            background: #1a1a1a;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-logo img { height: 45px; }

        /* Secondary Navbar */
        .navbar-secondary {
            background: #0d5f78;
            padding: 6px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 15px;
        }
        .nav-left { display: flex; align-items: center; gap: 14px; }
        .nav-user-wrapper { position: relative; }
        .nav-username {
            color: #fff; font-weight: bold; font-size: 15px; cursor: pointer;
        }
        .nav-username .fa-caret-down { font-size: 11px; margin-left: 3px; }
        .user-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            margin-top: 6px;
            background: #1a1a1a;
            border: 1px solid #444;
            border-radius: 3px;
            min-width: 160px;
            z-index: 999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        }
        .user-dropdown.open { display: block; }
        .user-dropdown a {
            display: block;
            padding: 10px 16px;
            color: #ccc;
            font-size: 14px;
            font-weight: normal;
            transition: background 0.15s;
        }
        .user-dropdown a:hover { background: #333; color: #fff; }
        .user-dropdown a + a { border-top: 1px solid #333; }
        .nav-icons { display: flex; gap: 12px; align-items: center; }
        .nav-icons a { color: #fff; font-size: 16px; transition: color 0.2s; }
        .nav-icons a:hover { color: #cce; }
        .nav-right { display: flex; gap: 18px; align-items: center; }
        .nav-right a { color: #fff; font-size: 15px; transition: color 0.2s; }
        .nav-right a:hover { color: #cce; }
        .nav-right .fa-caret-down { font-size: 10px; margin-left: 2px; }

        /* Account Card */
        .account-card {
            max-width: 1100px;
            margin: 30px auto;
            background: #333;
            border: 1px solid #555;
            border-radius: 12px;
            padding: 35px 40px 40px;
        }

        /* Card Header with logo */
        .card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }
        .card-title img {
            height: 55px;
        }
        .card-title h1 {
            color: #ccc;
            font-size: 32px;
            font-weight: bold;
        }

        /* Flash Messages */
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

        /* Section Headers */
        .section {
            margin-bottom: 24px;
        }
        .section-header {
            color: #ff6600;
            font-size: 16px;
            font-weight: bold;
            font-style: italic;
            padding-bottom: 6px;
            border-bottom: 1px solid #ff6600;
            margin-bottom: 0;
        }

        /* Section Table */
        .section-table {
            width: 100%;
            border-collapse: collapse;
        }
        .section-table tr {
            border-bottom: 1px solid #444;
        }
        .section-table td {
            padding: 12px 14px;
            font-size: 15px;
            vertical-align: middle;
        }
        .section-table .label {
            color: #ccc;
            font-weight: bold;
            width: 140px;
            white-space: nowrap;
        }
        .section-table .value {
            color: #999;
        }
        .section-table .action {
            text-align: right;
            width: 180px;
        }
        .section-table .action button,
        .section-table .action a {
            background: #555;
            border: 1px solid #666;
            color: #ccc;
            padding: 6px 20px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 3px;
            display: inline-block;
            text-transform: lowercase;
        }
        .section-table .action button:hover,
        .section-table .action a:hover {
            background: #666;
            color: #fff;
        }
        .status-text {
            font-weight: bold;
        }
        .countdown {
            color: #ff6600;
            font-weight: bold;
            font-style: italic;
        }

        /* Modal-style forms */
        .modal-form {
            background: #2a2a2a;
            border: 1px solid #555;
            border-radius: 4px;
            padding: 25px 30px;
            margin-bottom: 20px;
        }
        .modal-form h3 {
            color: #ff6600;
            font-size: 15px;
            margin-bottom: 15px;
        }
        .modal-form input[type="text"],
        .modal-form input[type="email"] {
            background: #444;
            border: 1px solid #666;
            color: #fff;
            padding: 10px 14px;
            font-size: 15px;
            border-radius: 2px;
            width: 100%;
            max-width: 400px;
            outline: none;
            margin-bottom: 12px;
            display: block;
        }
        .modal-form input::placeholder { color: #888; }
        .modal-form input:focus { border-color: #00bfff; }
        .modal-form button {
            background: transparent;
            border: 1px solid #888;
            color: #fff;
            padding: 8px 30px;
            cursor: pointer;
            font-size: 14px;
            text-transform: lowercase;
            border-radius: 3px;
            margin-right: 10px;
        }
        .modal-form button:hover { background: rgba(255,255,255,0.05); }
        .modal-form .cancel-link {
            color: #888;
            font-size: 14px;
        }
        .modal-form .cancel-link:hover { color: #ccc; }

        /* Close Account Confirmation */
        .close-account-box {
            text-align: center;
            padding: 10px 0 20px;
        }
        .close-account-box .sorry {
            color: #fff;
            font-weight: bold;
            font-size: 16px;
            margin: 30px 0 16px;
        }
        .close-account-box p {
            color: #999;
            font-size: 15px;
            line-height: 2;
        }
        .close-account-box button {
            background: transparent;
            border: 1px solid #ff6600;
            color: #fff;
            padding: 10px 40px;
            cursor: pointer;
            font-size: 15px;
            text-transform: lowercase;
            border-radius: 3px;
            margin-top: 20px;
        }
        .close-account-box button:hover { background: rgba(255,102,0,0.1); }
        .close-account-box .cancel-link {
            display: block;
            margin-top: 12px;
            color: #888;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <div class="navbar-top">
        <div class="navbar-logo">
            <a href="dashboard.php"><img src="assets/nzbgeek.png" alt="<?= e(SITE_NAME) ?>"></a>
        </div>
    </div>

    <!-- Secondary Navbar -->
    <div class="navbar-secondary">
        <div class="nav-left">
            <div class="nav-user-wrapper">
                <span class="nav-username" onclick="document.querySelector('.user-dropdown').classList.toggle('open')">
                    <?= e($current_user['username']) ?> <i class="fas fa-caret-down"></i>
                </span>
                <div class="user-dropdown">
                    <a href="account.php"><i class="fas fa-user"></i>&nbsp; my account</a>
                    <a href="logout.php"><i class="fas fa-power-off"></i>&nbsp; logout</a>
                </div>
            </div>
            <div class="nav-icons">
                <a href="dashboard.php" title="Home"><i class="fas fa-home"></i></a>
                <a href="logout.php" title="Logout"><i class="fas fa-power-off"></i></a>
            </div>
        </div>
        <div class="nav-right">
            <a href="#">Movies <i class="fas fa-caret-down"></i></a>
            <a href="#">Tv <i class="fas fa-caret-down"></i></a>
            <a href="#">Games <i class="fas fa-caret-down"></i></a>
            <a href="#">Audio <i class="fas fa-caret-down"></i></a>
            <a href="#">Books <i class="fas fa-caret-down"></i></a>
            <a href="#">Pc <i class="fas fa-caret-down"></i></a>
        </div>
    </div>

    <!-- Account Card -->
    <div class="account-card">
        <div class="card-title">
            <img src="assets/logo_2.png" alt="<?= e(SITE_NAME) ?>">
            <h1>my account</h1>
        </div>

        <?php if ($error): ?>
            <div class="flash flash-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="flash flash-success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($show_close_account): ?>
            <!-- Close Account Confirmation -->
            <div class="section">
                <div class="section-header">my account - close account</div>
                <div class="close-account-box">
                    <p class="sorry">We are sorry to see you go.</p>
                    <p>Closing your account requires additional verification by you,</p>
                    <p>an approval email will be sent to your registered email address.</p>
                    <p>Once approved all records are removed from our system.</p>
                    <form method="POST" action="account.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="request_close">
                        <button type="submit">close my account</button>
                    </form>
                    <a href="account.php" class="cancel-link">cancel</a>
                </div>
            </div>
        <?php else: ?>

            <?php if ($show_change_username): ?>
                <div class="modal-form">
                    <h3>change username</h3>
                    <form method="POST" action="account.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="change_username">
                        <input type="text" name="new_username" placeholder="new username" required
                               value="<?= e($_POST['new_username'] ?? $current_user['username']) ?>">
                        <button type="submit">save</button>
                        <a href="account.php" class="cancel-link">cancel</a>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($show_change_email): ?>
                <div class="modal-form">
                    <h3>change email</h3>
                    <form method="POST" action="account.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="change_email">
                        <input type="email" name="new_email" placeholder="new email address" required
                               value="<?= e($_POST['new_email'] ?? '') ?>">
                        <button type="submit">save</button>
                        <a href="account.php" class="cancel-link">cancel</a>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Summary Section -->
            <div class="section">
                <div class="section-header">summary</div>
                <table class="section-table">
                    <tr>
                        <td class="label">username:</td>
                        <td class="value"><?= e($current_user['username']) ?></td>
                        <td class="action">
                            <form method="POST" action="account.php" style="display:inline;">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="show_change_username">
                                <button type="submit">change</button>
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <td class="label">email:</td>
                        <td class="value"><?= e($masked) ?></td>
                        <td class="action">
                            <form method="POST" action="account.php" style="display:inline;">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="show_change_email">
                                <button type="submit">change</button>
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <td class="label">status:</td>
                        <td class="value">
                            <span class="status-text" style="color: <?= $status_color ?>"><?= e($current_user['status_display']) ?></span>
                        </td>
                        <td class="action">
                            <form method="POST" action="account.php" style="display:inline;">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="show_close_account">
                                <button type="submit">close my account</button>
                            </form>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Subscription Section -->
            <div class="section">
                <div class="section-header">subscription</div>
                <table class="section-table">
                    <tr>
                        <td class="label">registration:</td>
                        <td class="value"><?= e($reg_formatted) ?> (<?= e($reg_ago) ?>)</td>
                        <td class="action"></td>
                    </tr>
                    <tr>
                        <td class="label">expiry:</td>
                        <td class="value">
                            <?= e($expires_formatted) ?>
                            <span class="countdown">(<?= e($countdown) ?>)</span>
                        </td>
                        <td class="action">
                            <button type="button" disabled style="opacity:0.5;">subscribe</button>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Security Section -->
            <div class="section">
                <div class="section-header">security</div>
                <table class="section-table">
                    <tr>
                        <td class="label">password:</td>
                        <td class="value"><?= e($password_status) ?></td>
                        <td class="action">
                            <a href="reset_password.php">set password</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="label">two factor:</td>
                        <td class="value">please allow 5 mins once enabled</td>
                        <td class="action">
                            <button type="button" disabled style="opacity:0.5;">enable 2fa</button>
                        </td>
                    </tr>
                </table>
            </div>

        <?php endif; ?>
    </div>

<script>
document.addEventListener('click', function(e) {
    var dd = document.querySelector('.user-dropdown');
    if (dd && !e.target.closest('.nav-user-wrapper')) {
        dd.classList.remove('open');
    }
});
</script>
</body>
</html>
