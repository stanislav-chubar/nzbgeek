<?php
/**
 * MPass - Subscribe Page
 */
require_once __DIR__ . '/auth.php';

$now = new DateTime('now', new DateTimeZone('UTC'));
$expires = new DateTime($current_user['expires_at'], new DateTimeZone('UTC'));
$is_expired = $expires <= $now;
$countdown = $is_expired ? 'expired' : countdown_to($current_user['expires_at']);
$expires_formatted = format_date($current_user['expires_at']) . ' UTC';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe - <?= e(SITE_NAME) ?></title>
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
        .nav-username { color: #fff; font-weight: bold; font-size: 15px; cursor: pointer; }
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

        /* Content */
        .content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 30px 20px 40px;
        }

        /* Orange-bordered cards */
        .card-orange {
            border: 2px solid #ff6600;
            background: #2a2a2a;
            border-radius: 4px;
            padding: 20px 28px;
            margin-bottom: 24px;
        }
        .card-orange-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 4px;
        }
        .card-orange-header .icon { color: #ff6600; font-size: 28px; }
        .card-orange-header h3 { color: #ff6600; font-size: 18px; font-weight: bold; margin: 0; }
        .card-orange p { color: #888; font-size: 15px; line-height: 1.7; margin-top: 8px; }
        .card-orange a { color: #ff6600; font-weight: bold; }
        .card-orange a:hover { color: #ff8833; }
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
                    <a href="subscribe.php"><i class="fas fa-credit-card"></i>&nbsp; subscribe</a>
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

    <div class="content">
        <div class="card-orange">
            <div class="card-orange-header">
                <span class="icon"><i class="fas fa-credit-card"></i></span>
                <h3>Subscribe</h3>
            </div>
            <p>Subscription options will be available here soon.</p>
            <p>Your current membership expires on <?= e($expires_formatted) ?>
                <span style="color:#ff6600; font-weight:bold;">(<?= e($countdown) ?>)</span>
            </p>
        </div>
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
