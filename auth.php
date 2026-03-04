<?php
/**
 * MPass - Authentication Middleware
 */
if (basename($_SERVER['PHP_SELF']) === 'auth.php') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config.php';

// Secure session start
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = get_db();
$stmt = $db->prepare('
    SELECT u.*, ms.name AS status_name, ms.display_name AS status_display
    FROM users u
    JOIN member_statuses ms ON u.status_id = ms.id
    WHERE u.id = ?
');
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

// Validate user still exists and is not closed
if (!$current_user || ($current_user['status_name'] ?? '') === 'closed') {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Auto-expire trial accounts
if ($current_user['status_name'] === 'active_trial') {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $expires = new DateTime($current_user['expires_at'], new DateTimeZone('UTC'));
    if ($expires <= $now) {
        $expired_stmt = $db->prepare('SELECT id FROM member_statuses WHERE name = ?');
        $expired_stmt->execute(['expired']);
        $expired_id = $expired_stmt->fetchColumn();
        if ($expired_id) {
            $update = $db->prepare('UPDATE users SET status_id = ? WHERE id = ?');
            $update->execute([$expired_id, $current_user['id']]);
        }
        $current_user['status_name'] = 'expired';
        $current_user['status_display'] = 'expired';
    }
}

// Session ID regeneration every 30 minutes
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
