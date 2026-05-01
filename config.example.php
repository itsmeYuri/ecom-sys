<?php
// ── Session bootstrap ──────────────────────────────────────────
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '7200');
    ini_set('session.cookie_lifetime', '0');
    session_name('tg_secure_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

if (empty($_SESSION['session_initialized'])) {
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = time();
    $_SESSION['session_ip']          = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['session_ua']          = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

// ── Database ───────────────────────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'shop_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ── Application ────────────────────────────────────────────────
define('BASE_URL',  '/your-path/shop-system');
define('APP_NAME',  'Your App Name');
define('APP_ENV',   getenv('APP_ENV') ?: 'development');

// ── SMTP ───────────────────────────────────────────────────────
define('SMTP_HOST',       getenv('SMTP_HOST')       ?: 'smtp.gmail.com');
define('SMTP_PORT',       (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME',   getenv('SMTP_USERNAME')   ?: 'your@email.com');
define('SMTP_PASSWORD',   getenv('SMTP_PASSWORD')   ?: 'your-app-password');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'your@email.com');
define('SMTP_FROM_NAME',  getenv('SMTP_FROM_NAME')  ?: APP_NAME);

// ── Encryption key (AES-256) — change this to a random 32-char string ──
define('APP_ENCRYPT_KEY', getenv('APP_ENCRYPT_KEY') ?: 'change-me-32-char-key-in-prod!!');

// ── Security headers ───────────────────────────────────────────
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

date_default_timezone_set('Asia/Manila');
