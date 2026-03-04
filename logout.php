<?php
/**
 * MPass - Logout Page
 */

require_once __DIR__ . '/auth.php';

// Handle logout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

// Calculate countdown for background dashboard content
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
    <title>Logout - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #2a2a2a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 15px;
            color: #ccc;
            min-height: 100vh;
            overflow: hidden;
        }
        a { text-decoration: none; }

        /* Dashboard background (blurred/dimmed) */
        .dashboard-bg {
            filter: blur(3px) brightness(0.3);
            pointer-events: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            overflow: hidden;
        }

        /* Navbar styles (same as dashboard) */
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
        .navbar-search select, .navbar-search input, .navbar-search button {
            background: #333; color: #fff; border: 1px solid #444;
            padding: 6px 10px; font-size: 14px; border-radius: 2px;
        }
        .navbar-search input { color: #fff; width: 200px; }
        .navbar-secondary {
            background: #0d5f78;
            padding: 6px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 15px;
        }
        .nav-left {
            display: flex; align-items: center; gap: 14px;
        }
        .nav-username {
            color: #fff; font-weight: bold; font-size: 15px;
        }
        .nav-icons { display: flex; gap: 12px; }
        .nav-icons a { color: #fff; font-size: 16px; }
        .nav-icons a.icon-mail { color: #ffcc00; }
        .nav-right { display: flex; gap: 18px; }
        .nav-right a { color: #fff; font-size: 15px; }
        .bg-content {
            max-width: 1280px; margin: 0 auto; padding: 0 20px;
        }
        .search-bar {
            border: 1px solid #555; padding: 12px 20px; margin: 20px auto;
            text-align: center; color: #ccc; font-size: 15px; border-radius: 3px;
        }
        .search-bar .fa-magnifying-glass { color: #fff; margin: 0 6px; }
        .card-orange {
            border: 2px solid #ff6600; background: #2a2a2a;
            border-radius: 4px; padding: 20px 28px; margin-bottom: 24px;
        }
        .card-orange-header {
            display: flex; align-items: center; gap: 14px;
        }
        .card-orange-header .icon { color: #ff6600; font-size: 28px; }
        .card-orange-header h3 { color: #ff6600; font-size: 18px; margin: 0; }
        .card-orange-header .msg-text { color: #fff; font-size: 15px; font-weight: bold; }
        .card-orange p { color: #888; font-size: 15px; line-height: 1.7; margin-top: 8px; }
        .card-orange ul { color: #888; font-size: 15px; padding-left: 24px; margin: 10px 0; line-height: 1.8; }
        .card-orange a { color: #ff6600; font-weight: bold; }
        .trial-expiry { margin-top: 16px; font-size: 15px; }
        .trial-expiry strong { color: #fff; }
        .countdown { color: #ff6600; font-weight: bold; }

        /* Logout Modal Overlay */
        .logout-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .close-x {
            position: fixed;
            top: 20px;
            right: 30px;
            font-size: 32px;
            color: #fff;
            z-index: 1001;
            line-height: 1;
            opacity: 0.8;
        }
        .close-x:hover {
            opacity: 1;
        }
        .power-icon {
            font-size: 100px;
            color: #00bfff;
            margin-bottom: 20px;
        }
        .logout-text {
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 25px;
        }
        .logout-btn {
            background: transparent;
            border: 1px solid #888;
            color: #fff;
            padding: 11px 60px;
            cursor: pointer;
            font-size: 15px;
            text-transform: lowercase;
            border-radius: 3px;
            transition: background 0.2s;
        }
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>

    <!-- Dashboard Background -->
    <div class="dashboard-bg">
        <div class="navbar-top">
            <div class="navbar-logo"><img src="assets/nzbgeek.png" alt="<?= e(SITE_NAME) ?>"></div>
        </div>
        <div class="navbar-secondary">
            <div class="nav-left">
                <span class="nav-username"><?= e($current_user['username']) ?> <i class="fas fa-caret-down"></i></span>
                <div class="nav-icons">
                    <a href="#"><i class="fas fa-home"></i></a>
                    <a href="#"><i class="fas fa-power-off"></i></a>
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
        <div class="bg-content">
            <div class="search-bar">geekseek <i class="fas fa-magnifying-glass"></i> Search</div>
            <div class="card-orange">
                <div class="card-orange-header">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <span class="msg-text">You have 1 new message</span>
                </div>
            </div>
            <div class="card-orange">
                <div class="card-orange-header">
                    <span class="icon"><i class="fas fa-bullhorn"></i></span>
                    <h3>3 Day Trial</h3>
                </div>
                <p>During the trial you can.</p>
                <ul>
                    <li>Grab a total of 15 nzbs</li>
                    <li>Use the api in third party applications</li>
                    <li>Browse &amp; search the index</li>
                    <li>Use my cart &amp; rss feeds</li>
                    <li>Create &amp; use saved seeks</li>
                    <li>Customise your dashboard &amp; views</li>
                    <li>Access howto guides &amp; information</li>
                </ul>
                <p>A subscription is required for full, unrestricted &amp; continued use after your trial expires.</p>
                <p>Visit <a href="#">my account</a> to subscribe.</p>
                <p>Visit <a href="#">my subscription</a> for detailed information about our subscriptions.</p>
                <div class="trial-expiry">
                    <strong>Your trial expires on <?= e($expires_formatted) ?></strong>
                    <span class="countdown">(<?= e($countdown) ?>)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Close button -->
    <a href="dashboard.php" class="close-x">&times;</a>

    <!-- Logout Modal -->
    <div class="logout-overlay">
        <div class="power-icon">
            <i class="fas fa-power-off"></i>
        </div>
        <p class="logout-text">logout?</p>
        <form method="POST" action="logout.php">
            <?= csrf_input() ?>
            <button type="submit" class="logout-btn">confirm</button>
        </form>
    </div>

</body>
</html>
