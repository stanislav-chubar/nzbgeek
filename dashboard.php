<?php
/**
 * MPass - User Dashboard
 */
require_once __DIR__ . '/auth.php';

// Calculate countdown and formatting for trial expiry
$now = new DateTime('now', new DateTimeZone('UTC'));
$expires = new DateTime($current_user['expires_at'], new DateTimeZone('UTC'));
$is_expired = $expires <= $now;
$countdown = $is_expired ? 'expired' : countdown_to($current_user['expires_at']);
$expires_formatted = format_date($current_user['expires_at']) . ' UTC';
$reg_formatted = format_date($current_user['registered_at']) . ' UTC';
$reg_ago = time_ago($current_user['registered_at']);

$status_display = $current_user['status_display'];
$is_trial = $current_user['status_name'] === 'active_trial';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= e(SITE_NAME) ?></title>
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
        .navbar-logo img {
            height: 45px;
        }
        .navbar-search {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .navbar-search select {
            background: #333;
            color: #fff;
            border: 1px solid #444;
            padding: 6px 10px;
            font-size: 14px;
            border-radius: 2px;
        }
        .navbar-search input {
            background: #333;
            color: #fff;
            border: 1px solid #444;
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 2px;
            width: 200px;
        }
        .navbar-search input::placeholder { color: #888; }
        .navbar-search button {
            background: #444;
            color: #fff;
            border: 1px solid #555;
            padding: 6px 14px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 2px;
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
        .nav-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .nav-username {
            color: #fff;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
        }
        .nav-username .fa-caret-down {
            font-size: 11px;
            margin-left: 3px;
        }
        .nav-icons {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .nav-icons a {
            color: #fff;
            font-size: 16px;
            transition: color 0.2s;
        }
        .nav-icons a:hover {
            color: #cce;
        }
        .nav-icons a.icon-mail {
            color: #ffcc00;
        }
        .nav-icons a.icon-mail:hover {
            color: #ffe055;
        }
        .nav-right {
            display: flex;
            gap: 18px;
            align-items: center;
        }
        .nav-right a {
            color: #fff;
            font-size: 15px;
            transition: color 0.2s;
        }
        .nav-right a:hover {
            color: #cce;
        }
        .nav-right .fa-caret-down {
            font-size: 10px;
            margin-left: 2px;
        }

        /* Search Bar */
        .search-bar {
            border: 1px solid #555;
            padding: 12px 20px;
            margin: 20px auto;
            max-width: 1280px;
            text-align: center;
            color: #ccc;
            font-size: 15px;
            border-radius: 3px;
        }
        .search-bar .fa-magnifying-glass {
            color: #fff;
            margin: 0 6px;
        }

        /* Content */
        .content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px 40px;
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
        .card-orange-header .icon {
            color: #ff6600;
            font-size: 28px;
        }
        .card-orange-header h3 {
            color: #ff6600;
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }
        .card-orange-header .msg-text {
            color: #fff;
            font-size: 15px;
            font-weight: bold;
        }
        .card-orange p {
            color: #888;
            font-size: 15px;
            line-height: 1.7;
            margin-top: 8px;
        }
        .card-orange ul {
            color: #888;
            font-size: 15px;
            padding-left: 24px;
            margin: 10px 0;
            line-height: 1.8;
        }
        .card-orange a {
            color: #ff6600;
            font-weight: bold;
        }
        .card-orange a:hover {
            color: #ff8833;
        }
        .trial-expiry {
            margin-top: 16px;
            font-size: 15px;
        }
        .trial-expiry strong {
            color: #fff;
        }
        .countdown {
            color: #ff6600;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <div class="navbar-top">
        <div class="navbar-logo">
            <a href="dashboard.php"><img src="assets/nzbgeek.png" alt="<?= e(SITE_NAME) ?>"></a>
        </div>
        <div class="navbar-search">
            <select>
                <option>All</option>
                <option>Movies</option>
                <option>Tv</option>
                <option>Games</option>
                <option>Audio</option>
                <option>Books</option>
                <option>Pc</option>
            </select>
            <input type="text" placeholder="Basic search">
            <button type="button">Search</button>
        </div>
    </div>

    <!-- Secondary Navbar -->
    <div class="navbar-secondary">
        <div class="nav-left">
            <span class="nav-username">
                <?= e($current_user['username']) ?> <i class="fas fa-caret-down"></i>
            </span>
            <div class="nav-icons">
                <a href="dashboard.php" title="Home"><i class="fas fa-home"></i></a>
                <a href="#" title="Notifications"><i class="fas fa-bullhorn"></i></a>
                <a href="#" title="Messages" class="icon-mail"><i class="fas fa-envelope"></i></a>
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

    <!-- Search Bar -->
    <div class="content">
        <div class="search-bar">
            geekseek <i class="fas fa-magnifying-glass"></i> Search
        </div>

        <!-- Message Notification -->
        <div class="card-orange">
            <div class="card-orange-header">
                <span class="icon"><i class="fas fa-envelope"></i></span>
                <span class="msg-text">You have 1 new message</span>
            </div>
        </div>

        <!-- Trial / Membership Info -->
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

            <p>Visit <a href="account.php">my account</a> to subscribe.</p>

            <p>Visit <a href="#">my subscription</a> for detailed information about our subscriptions.</p>

            <div class="trial-expiry">
                <strong>Your trial expires on <?= e($expires_formatted) ?></strong>
                <?php if (!$is_expired): ?>
                    <span class="countdown">(<?= e($countdown) ?>)</span>
                <?php else: ?>
                    <span class="countdown">(expired)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>
