<?php
/**
 * MPass - Central Configuration
 */
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Forbidden');
}

// --- Load .env ---
$env_path = __DIR__ . '/.env';
if (!file_exists($env_path)) {
    die('.env file not found. Copy .env.example to .env and fill in your credentials.');
}
foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    if (strpos($line, '=') === false) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

// --- Database Configuration ---
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_CHARSET', $_ENV['DB_CHARSET']);
define('SENDGRID_API_KEY', $_ENV['SENDGRID_API_KEY']);
define('SENDGRID_FROM_EMAIL', $_ENV['SENDGRID_FROM_EMAIL']);
define('SENDGRID_FROM_NAME', $_ENV['SENDGRID_FROM_NAME']);

// --- Site Configuration ---
define('SITE_NAME', 'MPass');
define('SITE_URL', 'https://mpass.id');
define('TRIAL_HOURS', 72);
define('GENERATED_KEY_LENGTH', 30);

define('SESSION_LIFETIME', 86400); // 24 hours

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// --- CSRF Token Helpers ---
function ensure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function generate_csrf_token(): string {
    ensure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
    ensure_session();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token()) . '">';
}

// --- Random Key Generation ---
function generate_random_key(int $length = GENERATED_KEY_LENGTH): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $key = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $key .= $chars[random_int(0, $max)];
    }
    return $key;
}

// --- Flash Message Helpers (for PRG pattern) ---
function set_flash(string $type, string $message): void {
    ensure_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    ensure_session();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function render_flash(): string {
    $flash = get_flash();
    if (!$flash) return '';
    $type = $flash['type'] === 'error' ? 'flash-error' : 'flash-success';
    return '<div class="flash ' . $type . '">' . e($flash['message']) . '</div>';
}

// --- Output Escaping Helper ---
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// --- Email Masking Helper ---
function mask_email(string $email): string {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return '***';
    $local = $parts[0];
    $domain = $parts[1];
    $masked_local = substr($local, 0, 1) . str_repeat('*', max(strlen($local) - 1, 3));
    $domain_parts = explode('.', $domain);
    $masked_domain = substr($domain_parts[0], 0, 1) . str_repeat('*', max(strlen($domain_parts[0]) - 1, 3));
    return $masked_local . '@' . $masked_domain . '.' . end($domain_parts);
}

// --- Relative Time Helper ---
function time_ago(string $datetime): string {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $then = new DateTime($datetime, new DateTimeZone('UTC'));
    $diff = $now->diff($then);
    $parts = [];
    $total_days = $diff->days;
    if ($total_days > 0) $parts[] = $total_days . ' day' . ($total_days !== 1 ? 's' : '');
    if ($diff->h > 0) $parts[] = $diff->h . ' hour' . ($diff->h !== 1 ? 's' : '');
    if ($diff->i > 0) $parts[] = $diff->i . ' min' . ($diff->i !== 1 ? 's' : '');
    return implode(', ', $parts) ?: '0 mins';
}

// --- Countdown Helper (for expiry) ---
function countdown_to(string $datetime): string {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $target = new DateTime($datetime, new DateTimeZone('UTC'));
    if ($target <= $now) return 'expired';
    $diff = $now->diff($target);
    $parts = [];
    $total_days = $diff->days;
    if ($total_days > 0) $parts[] = $total_days . ' day' . ($total_days !== 1 ? 's' : '');
    if ($diff->h > 0) $parts[] = $diff->h . ' hour' . ($diff->h !== 1 ? 's' : '');
    if ($diff->i > 0) $parts[] = $diff->i . ' min' . ($diff->i !== 1 ? 's' : '');
    return implode(', ', $parts) ?: '0 mins';
}

// --- Format Date for Display ---
function format_date(string $datetime): string {
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    return $dt->format('l jS F Y \@ h:i:sa');
}
