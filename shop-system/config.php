<?php
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '604800');
    ini_set('session.cookie_lifetime', '604800');
    session_name('threapglailz_session');
    session_set_cookie_params([
        'lifetime' => 604800,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['session_initialized'])) {
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = time();
}

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'shop_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', '/shop-system');

// SMTP configuration for OTP email sending (PHPMailer)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'darryljohn020@gmail.com');
define('SMTP_PASSWORD', 'vngp dgcy iips jjbh');
define('SMTP_ENCRYPTION', 'tls'); // tls or ssl
define('SMTP_FROM_EMAIL', 'darryljohn020@gmail.com');
define('SMTP_FROM_NAME', 'Threap Glailz');

date_default_timezone_set('Asia/Manila');
