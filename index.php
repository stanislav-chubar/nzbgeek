<?php

require_once __DIR__ . '/config.php';
ensure_session();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
