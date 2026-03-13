<?php
/**
 * MPass - TOTP Two-Factor Authentication Helper
 * Pure PHP implementation using HMAC-SHA1 (RFC 6238)
 */
if (basename($_SERVER['PHP_SELF']) === 'totp.php') {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Generate a random Base32-encoded secret for TOTP.
 */
function totp_generate_secret(int $length = 20): string {
    $random = random_bytes($length);
    return base32_encode($random);
}

/**
 * Generate a TOTP code for the given secret at a given time.
 */
function totp_get_code(string $secret, ?int $time = null, int $digits = 6, int $period = 30): string {
    if ($time === null) {
        $time = time();
    }
    $counter = intdiv($time, $period);
    $binary_key = base32_decode($secret);

    // Pack counter as 8-byte big-endian
    $counter_bytes = pack('N*', 0, $counter);

    $hash = hash_hmac('sha1', $counter_bytes, $binary_key, true);
    $offset = ord($hash[19]) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % pow(10, $digits);

    return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}

/**
 * Verify a TOTP code with a time window tolerance.
 * Checks current period +/- $window periods.
 */
function totp_verify(string $secret, string $code, int $window = 1): bool {
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        $check_time = $now + ($i * 30);
        if (hash_equals(totp_get_code($secret, $check_time), $code)) {
            return true;
        }
    }
    return false;
}

/**
 * Generate the otpauth:// URI for QR code generation.
 */
function totp_get_uri(string $username, string $secret, string $issuer = SITE_NAME): string {
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($username)
        . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer)
        . '&digits=6&period=30';
}

/**
 * Generate a QR code image URL using Google Charts API.
 */
function totp_get_qr_url(string $username, string $secret): string {
    $uri = totp_get_uri($username, $secret);
    return 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($uri) . '&choe=UTF-8';
}

/**
 * Base32 encode binary data.
 */
function base32_encode(string $data): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    foreach (str_split($data) as $char) {
        $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $result = '';
    foreach (str_split($binary, 5) as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $result .= $alphabet[bindec($chunk)];
    }
    return $result;
}

/**
 * Base32 decode to binary data.
 */
function base32_decode(string $data): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $data = strtoupper($data);
    $data = rtrim($data, '=');
    $binary = '';
    foreach (str_split($data) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) continue;
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $result = '';
    foreach (str_split($binary, 8) as $byte) {
        if (strlen($byte) < 8) break;
        $result .= chr(bindec($byte));
    }
    return $result;
}
