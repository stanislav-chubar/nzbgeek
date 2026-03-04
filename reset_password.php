<?php
/**
 * MPass - Set Personal Password (Protected)
 */

require_once __DIR__ . '/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);

            $db = get_db();
            $update = $db->prepare('
                UPDATE users SET password_hash = ?, is_using_generated_password = 0 WHERE id = ?
            ');
            $update->execute([$hash, $current_user['id']]);

            set_flash('success', 'Your personal password has been set successfully.');
            header('Location: account.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password - <?= e(SITE_NAME) ?></title>
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
        .navbar-search {
            display: flex; align-items: center; gap: 6px;
        }
        .navbar-search select {
            background: #333; color: #fff; border: 1px solid #444;
            padding: 6px 10px; font-size: 14px; border-radius: 2px;
        }
        .navbar-search input[type="text"] {
            background: #333; color: #fff; border: 1px solid #444;
            padding: 6px 12px; font-size: 14px; border-radius: 2px; width: 200px;
        }
        .navbar-search input::placeholder { color: #888; }
        .navbar-search button {
            background: #444; color: #fff; border: 1px solid #555;
            padding: 6px 14px; font-size: 14px; cursor: pointer; border-radius: 2px;
        }
        .navbar-search button:hover { background: #555; }

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
        .nav-username { color: #fff; font-weight: bold; font-size: 15px; }
        .nav-icons { display: flex; gap: 12px; }
        .nav-icons a { color: #fff; font-size: 16px; }
        .nav-icons a:hover { color: #cce; }
        .nav-icons a.icon-mail { color: #ffcc00; }
        .nav-icons a.icon-mail:hover { color: #ffe055; }
        .nav-right { display: flex; gap: 18px; }
        .nav-right a { color: #fff; font-size: 15px; }
        .nav-right a:hover { color: #cce; }

        .content {
            max-width: 500px;
            margin: 60px auto;
            padding: 0 20px;
        }
        .password-card {
            background: #1e1e1e;
            border: 1px solid #555;
            border-radius: 4px;
            padding: 35px 40px;
            text-align: center;
        }
        .password-card h2 {
            color: #ff6600;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 25px;
            text-transform: lowercase;
        }
        .flash {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
        }
        .flash-error {
            background: rgba(255, 0, 0, 0.15);
            border: 1px solid #ff4444;
            color: #ff6666;
        }
        .form-group {
            margin-bottom: 16px;
            text-align: left;
        }
        .form-group label {
            display: block;
            color: #999;
            font-size: 14px;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            background: #444;
            border: 1px solid #666;
            color: #fff;
            padding: 10px 14px;
            font-size: 15px;
            border-radius: 2px;
            outline: none;
        }
        .form-group input::placeholder { color: #888; }
        .form-group input:focus { border-color: #00bfff; }
        .btn {
            background: transparent;
            border: 1px solid #888;
            color: #fff;
            padding: 10px 40px;
            cursor: pointer;
            font-size: 15px;
            text-transform: lowercase;
            border-radius: 3px;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .btn:hover { background: rgba(255,255,255,0.05); }
        .cancel-link {
            display: block;
            margin-top: 15px;
            color: #888;
            font-size: 14px;
        }
        .cancel-link:hover { color: #ccc; }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <div class="navbar-top">
        <div class="navbar-logo">
            <a href="dashboard.php"><img src="assets/nzbgeek.png" alt="<?= e(SITE_NAME) ?>"></a>
        </div>
        <div class="navbar-search">
            <select><option>all</option></select>
            <input type="text" placeholder="basic search">
            <button type="button">search</button>
        </div>
    </div>

    <!-- Secondary Navbar -->
    <div class="navbar-secondary">
        <div class="nav-left">
            <span class="nav-username"><?= e($current_user['username']) ?> <i class="fas fa-caret-down"></i></span>
            <div class="nav-icons">
                <a href="dashboard.php" title="Home"><i class="fas fa-home"></i></a>
                <a href="#" title="Notifications"><i class="fas fa-bullhorn"></i></a>
                <a href="#" title="Messages" class="icon-mail"><i class="fas fa-envelope"></i></a>
                <a href="logout.php" title="Logout"><i class="fas fa-power-off"></i></a>
            </div>
        </div>
        <div class="nav-right">
            <a href="#">movies <i class="fas fa-caret-down"></i></a>
            <a href="#">tv <i class="fas fa-caret-down"></i></a>
            <a href="#">games <i class="fas fa-caret-down"></i></a>
            <a href="#">audio <i class="fas fa-caret-down"></i></a>
            <a href="#">books <i class="fas fa-caret-down"></i></a>
            <a href="#">pc <i class="fas fa-caret-down"></i></a>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="password-card">
            <h2>set password</h2>

            <?php if ($error): ?>
                <div class="flash flash-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="reset_password.php">
                <?= csrf_input() ?>
                <div class="form-group">
                    <label>new password</label>
                    <input type="password" name="new_password" placeholder="minimum 8 characters" required>
                </div>
                <div class="form-group">
                    <label>confirm password</label>
                    <input type="password" name="confirm_password" placeholder="retype password" required>
                </div>
                <button type="submit" class="btn">set password</button>
            </form>

            <a href="account.php" class="cancel-link">back to my account</a>
        </div>
    </div>

</body>
</html>
