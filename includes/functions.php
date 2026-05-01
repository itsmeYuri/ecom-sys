<?php
require_once __DIR__ . '/db.php';

// ═══════════════════════════════════════════════════════════════
//  SCHEMA MIGRATIONS (idempotent)
// ═══════════════════════════════════════════════════════════════
function run_schema_migrations(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $migrations = [
        "CREATE TABLE IF NOT EXISTS hero_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL DEFAULT 'FIND CLOTHES THAT MATCHES YOUR STYLE',
            description TEXT NULL,
            image_data LONGBLOB NULL,
            image_mime VARCHAR(100) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS activation_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_act_user (user_id)
        )",
        "CREATE TABLE IF NOT EXISTS totp_secrets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM('user','admin','employee') NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            secret VARCHAR(128) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_totp (entity_type, entity_id)
        )",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM('user','admin','employee') NOT NULL,
            identifier VARCHAR(150) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            success TINYINT(1) NOT NULL DEFAULT 0,
            INDEX idx_att_ident (entity_type, identifier, attempted_at)
        )",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM('user','admin','employee','guest') NOT NULL DEFAULT 'guest',
            entity_id INT UNSIGNED NULL,
            username VARCHAR(150) NULL,
            action VARCHAR(120) NOT NULL,
            detail TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(512) NULL,
            logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_aud_action (action, logged_at)
        )",
        "CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
    ];

    $defaults = [
        'max_login_attempts'       => '3',
        'lockout_duration_minutes' => '30',
        'session_timeout_minutes'  => '120',
        'password_min_length'      => '8',
        'password_require_upper'   => '1',
        'password_require_number'  => '1',
        'password_require_special' => '1',
        'password_expiry_days'     => '90',
        'recaptcha_site_key'       => '',
        'recaptcha_secret_key'     => '',
        'paymongo_secret_key'      => '',
        'paymongo_public_key'      => '',
        'paymongo_webhook_secret'  => '',
        'enable_totp'              => '1',
        'enable_email_activation'  => '1',
        'maintenance_mode'         => '0',
    ];

    // Columns to add with ALTER (MySQL < 8 has no ADD COLUMN IF NOT EXISTS)
    $columnChecks = [
        ['users',    'is_locked',           "ALTER TABLE users ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0"],
        ['users',    'locked_at',           "ALTER TABLE users ADD COLUMN locked_at DATETIME NULL"],
        ['users',    'locked_reason',       "ALTER TABLE users ADD COLUMN locked_reason VARCHAR(255) NULL"],
        ['users',    'password_changed_at', "ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL"],
        ['users',    'totp_enabled',        "ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0"],
        ['users',    'is_verified',         "ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0"],
        ['admin',    'email',               "ALTER TABLE admin ADD COLUMN email VARCHAR(150) UNIQUE NULL"],
        ['admin',    'totp_enabled',        "ALTER TABLE admin ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0"],
        ['employees','is_locked',           "ALTER TABLE employees ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0"],
        ['employees','locked_at',           "ALTER TABLE employees ADD COLUMN locked_at DATETIME NULL"],
        ['employees','totp_enabled',        "ALTER TABLE employees ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0"],
        ['products', 'is_sold',             "ALTER TABLE products ADD COLUMN is_sold TINYINT(1) NOT NULL DEFAULT 0"],
        ['product_images','image_data',     "ALTER TABLE product_images ADD COLUMN image_data LONGBLOB NULL"],
        ['product_images','image_mime',     "ALTER TABLE product_images ADD COLUMN image_mime VARCHAR(100) NULL"],
        ['product_images','image_name',     "ALTER TABLE product_images ADD COLUMN image_name VARCHAR(255) NULL"],
        ['orders',   'payment_method',      "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) NULL DEFAULT 'cod'"],
        ['orders',   'payment_status',      "ALTER TABLE orders ADD COLUMN payment_status ENUM('Pending','Paid','Failed','Refunded') DEFAULT 'Pending'"],
        ['orders',   'payment_ref',         "ALTER TABLE orders ADD COLUMN payment_ref VARCHAR(255) NULL"],
        ['orders',   'shipping_address',    "ALTER TABLE orders ADD COLUMN shipping_address TEXT NULL"],
        ['orders',   'tracking_link',      "ALTER TABLE orders ADD COLUMN tracking_link VARCHAR(1000) NULL"],
    ];

    try {
        foreach ($migrations as $sql) {
            db()->exec($sql);
        }

        foreach ($columnChecks as [$table, $col, $ddl]) {
            $exists = db()->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'")->fetch();
            if (!$exists) {
                db()->exec($ddl);
            }
        }

        foreach ($defaults as $k => $v) {
            db()->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?,?)")->execute([$k,$v]);
        }

        db()->exec("UPDATE admin SET email='admin@example.com' WHERE username='admin' AND (email IS NULL OR email='')");

    } catch (Throwable $e) {
        // Keep running even if migration is blocked
    }
}
run_schema_migrations();

// ═══════════════════════════════════════════════════════════════
//  SYSTEM SETTINGS
// ═══════════════════════════════════════════════════════════════
function get_setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = db()->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
            $cache = array_column($rows, 'setting_value', 'setting_key');
        } catch (Throwable) { $cache = []; }
    }
    return (string)($cache[$key] ?? $default);
}

function set_setting(string $key, string $value): void {
    db()->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$key,$value,$value]);
}

// ═══════════════════════════════════════════════════════════════
//  OUTPUT ESCAPING & XSS PREVENTION
// ═══════════════════════════════════════════════════════════════
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ═══════════════════════════════════════════════════════════════
//  CSRF PROTECTION
// ═══════════════════════════════════════════════════════════════
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), (string)$token)) {
        http_response_code(403);
        die('CSRF verification failed. Please go back and try again.');
    }
}

// ═══════════════════════════════════════════════════════════════
//  SESSION HELPERS & TIMEOUT
// ═══════════════════════════════════════════════════════════════
function is_logged_in(): bool    { return !empty($_SESSION['user']); }
function current_user(): ?array  { return $_SESSION['user'] ?? null; }
function is_admin_logged_in(): bool    { return !empty($_SESSION['admin']); }
function is_employee_logged_in(): bool { return !empty($_SESSION['employee']); }

function check_session_timeout(): void {
    if (empty($_SESSION['user']) && empty($_SESSION['admin']) && empty($_SESSION['employee'])) return;
    $timeout = (int)get_setting('session_timeout_minutes', '120') * 60;
    $last    = $_SESSION['last_activity'] ?? 0;
    if ($last && (time() - $last) > $timeout) {
        $type = !empty($_SESSION['admin']) ? 'admin'
              : (!empty($_SESSION['employee']) ? 'employee' : 'user');
        $id   = $_SESSION[$type]['id'] ?? null;
        $name = $_SESSION[$type]['username'] ?? ($_SESSION[$type]['email'] ?? 'unknown');
        audit_log($type, $id, $name, 'SESSION_TIMEOUT', 'Auto-logout: inactivity');
        session_unset();
        session_destroy();
        session_start();
        flash('warning', 'Your session expired. Please log in again.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function require_login(): void {
    check_session_timeout();
    if (!is_logged_in()) {
        $returnTo = urlencode($_SERVER['REQUEST_URI'] ?? (BASE_URL . '/homepage.php'));
        header('Location: ' . BASE_URL . '/login.php?return_to=' . $returnTo);
        exit;
    }
}

function require_admin(): void {
    check_session_timeout();
    if (!is_admin_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_employee(): void {
    check_session_timeout();
    if (!is_employee_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════
//  FLASH MESSAGES
// ═══════════════════════════════════════════════════════════════
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}

// ═══════════════════════════════════════════════════════════════
//  PASSWORD POLICY
// ═══════════════════════════════════════════════════════════════
function validate_password(string $password): ?string {
    $minLen  = (int)get_setting('password_min_length', '8');
    $upper   = (bool)(int)get_setting('password_require_upper', '1');
    $number  = (bool)(int)get_setting('password_require_number', '1');
    $special = (bool)(int)get_setting('password_require_special', '1');

    if (strlen($password) < $minLen)                                  return "Password must be at least {$minLen} characters.";
    if ($upper   && !preg_match('/[A-Z]/', $password))                return 'Password must contain at least one uppercase letter.';
    if ($number  && !preg_match('/[0-9]/', $password))                return 'Password must contain at least one number.';
    if ($special && !preg_match('/[\!\@\#\$\%\^\&\*\(\)_\+\-\=\[\]\{\}\|\\;:\'",.?\/`~]/', $password)) {
        return 'Password must contain at least one special character.';
    }
    return null;
}

function is_password_expired(?string $changedAt): bool {
    $days = (int)get_setting('password_expiry_days', '90');
    if ($days <= 0) return false;
    if (!$changedAt) return true;
    return (time() - strtotime($changedAt)) > ($days * 86400);
}

// ═══════════════════════════════════════════════════════════════
//  CLIENT IP
// ═══════════════════════════════════════════════════════════════
function get_client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) return trim(explode(',', $_SERVER[$key])[0]);
    }
    return '0.0.0.0';
}

// ═══════════════════════════════════════════════════════════════
//  LOGIN ATTEMPT TRACKING & ACCOUNT LOCKOUT
// ═══════════════════════════════════════════════════════════════
function record_login_attempt(string $entityType, string $identifier, bool $success): void {
    try {
        db()->prepare("INSERT INTO login_attempts (entity_type, identifier, ip_address, success) VALUES (?,?,?,?)")
           ->execute([$entityType, $identifier, get_client_ip(), $success ? 1 : 0]);
    } catch (Throwable) {}
}

function get_recent_failed_attempts(string $entityType, string $identifier): int {
    $window = (int)get_setting('lockout_duration_minutes', '30');
    $stmt   = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE entity_type=? AND identifier=? AND success=0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$entityType, $identifier, $window]);
    return (int)$stmt->fetchColumn();
}

function is_account_locked_by_attempts(string $entityType, string $identifier): bool {
    $max = (int)get_setting('max_login_attempts', '3');
    return get_recent_failed_attempts($entityType, $identifier) >= $max;
}

function lock_entity_account(string $entityType, int $entityId, string $reason = 'Too many failed login attempts'): void {
    try {
        $table = ($entityType === 'employee') ? 'employees' : (($entityType === 'admin') ? 'admin' : 'users');
        db()->prepare("UPDATE `{$table}` SET is_locked=1, locked_at=NOW(), locked_reason=? WHERE id=?")->execute([$reason, $entityId]);
    } catch (Throwable) {}
}

function unlock_entity_account(string $entityType, int $entityId): void {
    try {
        $table = ($entityType === 'employee') ? 'employees' : (($entityType === 'admin') ? 'admin' : 'users');
        db()->prepare("UPDATE `{$table}` SET is_locked=0, locked_at=NULL, locked_reason=NULL WHERE id=?")->execute([$entityId]);
    } catch (Throwable) {}
}

function check_and_lock_if_needed(string $entityType, string $identifier, int $entityId): bool {
    $max    = (int)get_setting('max_login_attempts', '3');
    $failed = get_recent_failed_attempts($entityType, $identifier);
    if ($failed >= $max) {
        lock_entity_account($entityType, $entityId, "Auto-locked after {$failed} failed attempts");
        audit_log($entityType, $entityId, $identifier, 'ACCOUNT_LOCKED', "Auto-locked after {$failed} failed attempts from IP " . get_client_ip());
        return true;
    }
    return false;
}

function get_locked_accounts(): array {
    $out = [];
    try {
        $users = db()->query("SELECT 'user' AS entity_type, id, full_name AS name, email AS identifier, locked_at, locked_reason FROM users WHERE is_locked=1")->fetchAll();
        $emps  = db()->query("SELECT 'employee' AS entity_type, id, full_name AS name, username AS identifier, locked_at, locked_reason FROM employees WHERE is_locked=1")->fetchAll();
        $out   = array_merge($users, $emps);
    } catch (Throwable) {}
    return $out;
}

// ═══════════════════════════════════════════════════════════════
//  AUDIT LOGGING
// ═══════════════════════════════════════════════════════════════
function audit_log(string $entityType, ?int $entityId, ?string $username, string $action, ?string $detail = null): void {
    try {
        db()->prepare("INSERT INTO audit_logs (entity_type, entity_id, username, action, detail, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)")
           ->execute([
               $entityType, $entityId, $username, strtoupper($action), $detail,
               get_client_ip(),
               substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
           ]);
    } catch (Throwable) {}
}

// ═══════════════════════════════════════════════════════════════
//  RECAPTCHA
// ═══════════════════════════════════════════════════════════════
function verify_recaptcha(string $responseToken): bool {
    $secret = get_setting('recaptcha_secret_key', '');
    if ($secret === '') return true; // disabled if not configured
    $ctx    = stream_context_create(['http'=>['method'=>'POST','header'=>'Content-type: application/x-www-form-urlencoded','content'=>http_build_query(['secret'=>$secret,'response'=>$responseToken,'remoteip'=>get_client_ip()])]]);
    $result = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    if (!$result) return false;
    $data = json_decode($result, true);
    return !empty($data['success']);
}

// ═══════════════════════════════════════════════════════════════
//  OTP
// ═══════════════════════════════════════════════════════════════
function generate_otp_code(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function create_otp_code(string $entityType, int $entityId, string $purpose, int $ttlMinutes = 10): string {
    $otp  = generate_otp_code();
    $hash = password_hash($otp, PASSWORD_BCRYPT);
    db()->prepare("UPDATE otp_codes SET consumed_at=NOW() WHERE entity_type=? AND entity_id=? AND purpose=? AND consumed_at IS NULL")->execute([$entityType,$entityId,$purpose]);
    db()->prepare("INSERT INTO otp_codes (entity_type, entity_id, purpose, code_hash, expires_at) VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE))")->execute([$entityType,$entityId,$purpose,$hash,$ttlMinutes]);
    return $otp;
}

function verify_otp_code(string $entityType, int $entityId, string $purpose, string $otp): bool {
    $stmt = db()->prepare("SELECT * FROM otp_codes WHERE entity_type=? AND entity_id=? AND purpose=? AND consumed_at IS NULL AND expires_at>=NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$entityType,$entityId,$purpose]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($otp, $row['code_hash'])) return false;
    db()->prepare("UPDATE otp_codes SET consumed_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
    return true;
}

function send_otp_notice(?string $email, string $otp, string $purpose): void {
    $subject = APP_NAME . ' — Your OTP Code';
    $label   = strtoupper(str_replace('_', ' ', $purpose));
    $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;border:1px solid #eee;border-radius:8px;">'
          . '<h2 style="color:#111;">' . e(APP_NAME) . ' — OTP Code</h2>'
          . '<p>Your one-time password for <strong>' . e($label) . '</strong>:</p>'
          . '<div style="font-size:2rem;font-weight:800;letter-spacing:8px;padding:16px;background:#f5f5f5;border-radius:8px;text-align:center;margin:16px 0;">' . e($otp) . '</div>'
          . '<p style="color:#666;font-size:13px;">Expires in 10 minutes. Never share this code.</p>'
          . '</div>';
    $text = "Your OTP for {$label}: {$otp}. Expires in 10 minutes.";
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (send_email_smtp($email, $subject, $html, $text)) { flash('success', 'OTP sent to your email.'); return; }
    }
    flash('warning', '[DEV MODE] OTP: ' . $otp);
}

// ═══════════════════════════════════════════════════════════════
//  EMAIL ACTIVATION LINK
// ═══════════════════════════════════════════════════════════════
function create_activation_token(int $userId): string {
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    db()->prepare("UPDATE activation_tokens SET consumed_at=NOW() WHERE user_id=? AND consumed_at IS NULL")->execute([$userId]);
    db()->prepare("INSERT INTO activation_tokens (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 24 HOUR))")->execute([$userId,$hash]);
    return $token;
}

function verify_activation_token(string $token): ?array {
    $hash = hash('sha256', $token);
    $stmt = db()->prepare("SELECT * FROM activation_tokens WHERE token_hash=? AND consumed_at IS NULL AND expires_at>=NOW() LIMIT 1");
    $stmt->execute([$hash]);
    return $stmt->fetch() ?: null;
}

function consume_activation_token(int $tokenId): void {
    db()->prepare("UPDATE activation_tokens SET consumed_at=NOW() WHERE id=?")->execute([$tokenId]);
}

function send_activation_email(int $userId, string $email, string $fullName): void {
    $token = create_activation_token($userId);
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link  = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/activate.php?token=' . urlencode($token);
    $subject = APP_NAME . ' — Activate Your Account';
    $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;border:1px solid #eee;border-radius:8px;">'
          . '<h2 style="color:#111;">Welcome to ' . e(APP_NAME) . '!</h2>'
          . '<p>Hi ' . e($fullName) . ',</p><p>Please activate your account by clicking below:</p>'
          . '<p style="text-align:center;margin:24px 0;"><a href="' . $link . '" style="background:#111;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700;">Activate Account</a></p>'
          . '<p style="color:#666;font-size:13px;">This link expires in 24 hours.</p>'
          . '<p style="word-break:break-all;color:#999;font-size:12px;">Or copy: ' . e($link) . '</p>'
          . '</div>';
    send_email_smtp($email, $subject, $html, "Activate your account: {$link}");
}

// ═══════════════════════════════════════════════════════════════
//  SMTP EMAIL
// ═══════════════════════════════════════════════════════════════
function send_email_smtp(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
    $base = __DIR__ . '/../assets/PHPMailer/src';
    foreach (['Exception.php','PHPMailer.php','SMTP.php'] as $f) {
        if (!is_file("{$base}/{$f}")) return false;
        require_once "{$base}/{$f}";
    }
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = strtolower(SMTP_ENCRYPTION) === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (Throwable) { return false; }
}

// ═══════════════════════════════════════════════════════════════
//  TOTP / GOOGLE AUTHENTICATOR
// ═══════════════════════════════════════════════════════════════
function totp_generate_secret(): string {
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 32; $i++) $secret .= $chars[random_int(0, 31)];
    return $secret;
}

function totp_base32_decode(string $secret): string {
    $alpha  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(str_replace(' ', '', $secret));
    $bin    = '';
    foreach (str_split($secret) as $char) {
        $pos  = strpos($alpha, $char);
        if ($pos === false) continue;
        $bin .= sprintf('%05b', $pos);
    }
    $bytes = '';
    foreach (str_split($bin, 8) as $chunk) {
        if (strlen($chunk) === 8) $bytes .= chr(bindec($chunk));
    }
    return $bytes;
}

function totp_generate_code(string $secret, int $timeSlice = 0): string {
    if ($timeSlice === 0) $timeSlice = (int)floor(time() / 30);
    $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
    $hm   = hash_hmac('sha1', $time, totp_base32_decode($secret), true);
    $off  = ord($hm[19]) & 0x0f;
    $code = (((ord($hm[$off])   & 0x7f) << 24) | ((ord($hm[$off+1]) & 0xff) << 16)
           | ((ord($hm[$off+2]) & 0xff) <<  8) |  (ord($hm[$off+3]) & 0xff)) % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code, int $window = 1): bool {
    $slice = (int)floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_generate_code($secret, $slice + $i), $code)) return true;
    }
    return false;
}

function totp_get_secret(string $entityType, int $entityId): ?string {
    $stmt = db()->prepare("SELECT secret FROM totp_secrets WHERE entity_type=? AND entity_id=? LIMIT 1");
    $stmt->execute([$entityType, $entityId]);
    $row = $stmt->fetch();
    return $row ? $row['secret'] : null;
}

function totp_save_secret(string $entityType, int $entityId, string $secret): void {
    db()->prepare("INSERT INTO totp_secrets (entity_type, entity_id, secret) VALUES (?,?,?) ON DUPLICATE KEY UPDATE secret=?")->execute([$entityType,$entityId,$secret,$secret]);
}

function totp_qr_url(string $secret, string $label, string $issuer = ''): string {
    $issuer  = $issuer ?: APP_NAME;
    $otpauth = 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
             . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer);
    // api.qrserver.com — free, no API key, no deprecated status
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=10&data=' . rawurlencode($otpauth);
}

// ═══════════════════════════════════════════════════════════════
//  DATA ENCRYPTION (PII / payment data)
// ═══════════════════════════════════════════════════════════════
function encrypt_data(string $plaintext): string {
    $key    = substr(hash('sha256', APP_ENCRYPT_KEY, true), 0, 32);
    $iv     = random_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function decrypt_data(string $ciphertext): string {
    $key  = substr(hash('sha256', APP_ENCRYPT_KEY, true), 0, 32);
    $data = base64_decode($ciphertext);
    if (strlen($data) <= 16) return '';
    $iv   = substr($data, 0, 16);
    return (string)(openssl_decrypt(substr($data, 16), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv) ?: '');
}

// ═══════════════════════════════════════════════════════════════
//  CART & PRODUCT HELPERS
// ═══════════════════════════════════════════════════════════════
function cart_items_count(): int {
    if (!is_logged_in()) return 0;
    return array_sum($_SESSION['cart'] ?? []);
}

function get_categories(): array {
    return db()->query('SELECT id, name, slug FROM categories ORDER BY name ASC')->fetchAll();
}

function get_product_main_image(int $productId): string {
    $stmt = db()->prepare('SELECT id FROM product_images WHERE product_id=? ORDER BY is_main DESC, id ASC LIMIT 1');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return !empty($row['id']) ? (BASE_URL . '/image.php?id=' . (int)$row['id']) : fallback_image_url();
}

function fallback_image_url(): string { return BASE_URL . '/assets/images/model.png'; }

function image_url(?int $imageId, string $fallback = ''): string {
    if (!empty($imageId)) return BASE_URL . '/image.php?id=' . (int)$imageId;
    return $fallback !== '' ? $fallback : fallback_image_url();
}

function get_cart_products(): array {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) return [];
    $ids  = array_map('intval', array_keys($cart));
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT p.*, (SELECT pi.id FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_id FROM products p WHERE p.id IN ({$ph})");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();
    foreach ($products as &$p) {
        $p['quantity']   = (int)($cart[$p['id']] ?? 1);
        $p['line_total'] = $p['quantity'] * (float)$p['price'];
    }
    return $products;
}

function calculate_cart_totals(array $products): array {
    $subtotal = array_sum(array_column($products, 'line_total'));
    return ['subtotal'=>$subtotal,'discount'=>0,'delivery'=>0,'total'=>$subtotal];
}

function render_stars(float $rating): string {
    $full = max(0, min(5, (int)round($rating)));
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}

// ═══════════════════════════════════════════════════════════════
//  SECURE FILE UPLOAD VALIDATION
// ═══════════════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════════════
//  PAYMONGO
// ═══════════════════════════════════════════════════════════════
function paymongo_create_checkout(int $orderId, float $amount, string $description, string $method): ?string {
    $secretKey = get_setting('paymongo_secret_key', '');
    if (!$secretKey) return null;

    $methodMap = ['gcash' => 'gcash', 'maya' => 'maya'];
    $pmMethod  = $methodMap[$method] ?? 'gcash';

    // Build absolute URLs — PayMongo requires http:// or https://
    $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseAbs = $proto . '://' . $host . BASE_URL;

    $payload = json_encode([
        'data' => [
            'attributes' => [
                'line_items' => [[
                    'currency' => 'PHP',
                    'amount'   => (int)round($amount * 100),
                    'name'     => $description,
                    'quantity' => 1,
                ]],
                'payment_method_types' => [$pmMethod],
                'success_url'          => $baseAbs . '/payment-return.php?order=' . $orderId . '&result=success',
                'cancel_url'           => $baseAbs . '/payment-return.php?order=' . $orderId . '&result=cancel',
                'reference_number'     => (string)$orderId,
            ],
        ],
    ]);

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($secretKey . ':'),
        ],
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return null;
    $data      = json_decode($response, true);
    $sessionId = $data['data']['id'] ?? null;
    $url       = $data['data']['attributes']['checkout_url'] ?? null;

    if ($sessionId) {
        db()->prepare("UPDATE orders SET payment_ref=? WHERE id=?")->execute([$sessionId, $orderId]);
    }

    return $url;
}

function validate_uploaded_image(array $file): ?string {
    $allowedMimes = ['image/jpeg','image/png','image/gif','image/webp'];
    $maxBytes     = 5 * 1024 * 1024;
    if ($file['error'] !== UPLOAD_ERR_OK)  return 'Upload error code: ' . $file['error'];
    if ($file['size'] > $maxBytes)         return 'File too large. Maximum 5 MB allowed.';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) return 'Invalid file type. Only JPEG, PNG, GIF, WebP allowed.';
    $head = file_get_contents($file['tmp_name'], false, null, 0, 512);
    if ($head !== false && preg_match('/<\?php|<\?=/i', $head)) return 'File contains forbidden content.';
    return null;
}
