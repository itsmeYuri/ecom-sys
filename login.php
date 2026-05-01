<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in())          { header('Location: ' . BASE_URL . '/homepage.php');       exit; }
if (is_admin_logged_in())    { header('Location: ' . BASE_URL . '/admin/index.php');    exit; }
if (is_employee_logged_in()) { header('Location: ' . BASE_URL . '/employee/index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';
    $returnTo = trim($_POST['return_to'] ?? '');
    $ip       = get_client_ip();

    // reCAPTCHA check
    $captchaKey = get_setting('recaptcha_site_key', '');
    if ($captchaKey !== '') {
        if (!verify_recaptcha($_POST['g-recaptcha-response'] ?? '')) {
            flash('danger', 'CAPTCHA verification failed. Please try again.');
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }

    // ── Admin ─────────────────────────────────────────────────
    $adminRow = db()->prepare('SELECT * FROM admin WHERE username=? OR email=? LIMIT 1');
    $adminRow->execute([$identity, $identity]);
    $admin = $adminRow->fetch();

    if ($admin) {
        if (!empty($admin['is_locked'])) {
            record_login_attempt('admin', $identity, false);
            flash('danger', 'Admin account is locked. Contact support.');
            header('Location: ' . BASE_URL . '/login.php'); exit;
        }
        if (is_account_locked_by_attempts('admin', $identity)) {
            lock_entity_account('admin', (int)$admin['id']);
            flash('danger', 'Account locked after too many failed attempts.');
            header('Location: ' . BASE_URL . '/login.php'); exit;
        }
        if (password_verify($password, $admin['password_hash'])) {
            if (!filter_var($admin['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                flash('danger', 'Admin account has no valid email for OTP. Set admin email first.');
            } else {
                record_login_attempt('admin', $identity, true);
                if (!empty($admin['totp_enabled']) && get_setting('enable_totp','1') === '1') {
                    $_SESSION['choose_2fa_pending'] = [
                        'entity_type' => 'admin',
                        'entity_id'   => (int)$admin['id'],
                        'purpose'     => 'login',
                        'return_to'   => BASE_URL . '/admin/index.php',
                    ];
                    header('Location: ' . BASE_URL . '/choose-2fa.php'); exit;
                }
                $otp = create_otp_code('admin', (int)$admin['id'], 'login', 10);
                send_otp_notice($admin['email'], $otp, 'admin login');
                $_SESSION['otp_pending'] = [
                    'entity_type' => 'admin',
                    'entity_id'   => (int)$admin['id'],
                    'purpose'     => 'login',
                    'return_to'   => BASE_URL . '/admin/index.php',
                    'totp_check'  => false,
                ];
                header('Location: ' . BASE_URL . '/verify-otp.php'); exit;
            }
        } else {
            record_login_attempt('admin', $identity, false);
            check_and_lock_if_needed('admin', $identity, (int)$admin['id']);
        }
    }

    // ── Employee ──────────────────────────────────────────────
    $empRow = db()->prepare('SELECT * FROM employees WHERE username=? OR email=? LIMIT 1');
    $empRow->execute([$identity, $identity]);
    $emp = $empRow->fetch();

    if ($emp) {
        if (!empty($emp['is_locked'])) {
            record_login_attempt('employee', $identity, false);
            flash('danger', 'Employee account is locked. Contact admin.');
            header('Location: ' . BASE_URL . '/login.php'); exit;
        }
        if (!empty($emp['is_active']) && is_account_locked_by_attempts('employee', $identity)) {
            lock_entity_account('employee', (int)$emp['id']);
            flash('danger', 'Account locked after too many failed attempts.');
            header('Location: ' . BASE_URL . '/login.php'); exit;
        }
        if (!empty($emp['is_active']) && password_verify($password, $emp['password_hash'])) {
            record_login_attempt('employee', $identity, true);
            if (!empty($emp['totp_enabled']) && get_setting('enable_totp','1') === '1') {
                $_SESSION['choose_2fa_pending'] = [
                    'entity_type' => 'employee',
                    'entity_id'   => (int)$emp['id'],
                    'purpose'     => 'login',
                    'return_to'   => BASE_URL . '/employee/index.php',
                ];
                header('Location: ' . BASE_URL . '/choose-2fa.php'); exit;
            }
            $otp = create_otp_code('employee', (int)$emp['id'], 'login', 10);
            send_otp_notice($emp['email'] ?? null, $otp, 'employee login');
            $_SESSION['otp_pending'] = [
                'entity_type' => 'employee',
                'entity_id'   => (int)$emp['id'],
                'purpose'     => 'login',
                'return_to'   => BASE_URL . '/employee/index.php',
                'totp_check'  => false,
            ];
            header('Location: ' . BASE_URL . '/verify-otp.php'); exit;
        } elseif (!empty($emp['is_active'])) {
            record_login_attempt('employee', $identity, false);
            check_and_lock_if_needed('employee', $identity, (int)$emp['id']);
        }
    }

    // ── User ──────────────────────────────────────────────────
    $userRow = db()->prepare('SELECT * FROM users WHERE email=? OR phone=? LIMIT 1');
    $userRow->execute([$identity, $identity]);
    $user = $userRow->fetch();

    if ($user) {
        if (!empty($user['is_locked'])) {
            record_login_attempt('user', $identity, false);
            flash('danger', 'Account locked due to too many failed attempts. Contact admin to unlock.');
            header('Location: ' . BASE_URL . '/login.php'); exit;
        }
        if (is_account_locked_by_attempts('user', $identity)) {
            lock_entity_account('user', (int)$user['id']);
            audit_log('user', (int)$user['id'], $identity, 'ACCOUNT_LOCKED', 'Auto-locked from IP ' . $ip);
            flash('danger', 'Account locked after ' . get_setting('max_login_attempts','3') . ' failed attempts. Contact admin.');
            header('Location: ' . BASE_URL . '/login.php'); exit;
        }
        if (password_verify($password, $user['password_hash'])) {
            if (!filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                flash('danger', 'Account has no valid email. Contact admin.');
            } else {
                record_login_attempt('user', $identity, true);
                $fallback = BASE_URL . '/homepage.php';
                $target   = ($returnTo !== '' && str_starts_with($returnTo, '/')) ? $returnTo : $fallback;

                if (!empty($user['password_changed_at']) && is_password_expired($user['password_changed_at'])) {
                    flash('warning', 'Your password has expired. Please change it from your profile after logging in.');
                }

                // Unverified accounts must verify by email OTP first — no choice
                if (empty($user['is_verified'])) {
                    $otp = create_otp_code('user', (int)$user['id'], 'email_verify', 10);
                    send_otp_notice($user['email'], $otp, 'email verification');
                    $_SESSION['otp_pending'] = ['entity_type'=>'user','entity_id'=>(int)$user['id'],'purpose'=>'email_verify','return_to'=>$target,'totp_check'=>false];
                    flash('warning', 'Account not verified. Enter the OTP sent to your email.');
                    header('Location: ' . BASE_URL . '/verify-otp.php'); exit;
                }

                // Always show 2FA choice screen for verified users
                $_SESSION['choose_2fa_pending'] = [
                    'entity_type' => 'user',
                    'entity_id'   => (int)$user['id'],
                    'purpose'     => 'login',
                    'return_to'   => $target,
                ];
                header('Location: ' . BASE_URL . '/choose-2fa.php'); exit;
            }
        } else {
            record_login_attempt('user', $identity, false);
            check_and_lock_if_needed('user', $identity, (int)$user['id']);
        }
    }

    flash('danger', 'Invalid credentials. Please check your details and try again.');
}

$captchaSiteKey = get_setting('recaptcha_site_key', '');
$maxAttempts    = (int)get_setting('max_login_attempts', '3');
$timeout        = (int)get_setting('session_timeout_minutes', '120');
if (!empty($_GET['timeout'])) {
    flash('warning', 'Your session expired due to inactivity. Please sign in again.');
}
include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="detail-box p-4 p-md-5">
            <div class="text-center mb-4">
                <h1 class="h3 fw-bold mb-1">Welcome Back</h1>
                <p class="text-muted small">Sign in to your <?= e(APP_NAME) ?> account</p>
            </div>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e((string)($_GET['return_to'] ?? '')) ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Email or Phone</label>
                    <input name="identity" class="form-control" placeholder="your@email.com" required autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="pwdField" class="form-control" placeholder="Password" required autocomplete="current-password">
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()" tabindex="-1">&#128065;</button>
                    </div>
                </div>

                <?php if ($captchaSiteKey !== ''): ?>
                <div class="mb-3">
                    <div class="g-recaptcha" data-sitekey="<?= e($captchaSiteKey) ?>"></div>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-dark w-100 mt-1">Sign In &rarr;</button>
            </form>

            <p class="text-center small mt-3 mb-0">
                No account? <a href="<?= BASE_URL ?>/register.php" class="fw-semibold">Create one</a>
            </p>
            <p class="text-center small mt-1">
                <a href="<?= BASE_URL ?>/privacy-policy.php" class="text-muted">Privacy Policy</a>
            </p>

            <div class="alert alert-light border small mt-3 mb-0 py-2">
                <strong>Security notice:</strong> Account locks after <?= $maxAttempts ?> failed attempts.
                Session expires after <?= $timeout ?> min of inactivity. OTP required every login.
            </div>
        </div>
    </div>
</div>

<?php if ($captchaSiteKey !== ''): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
<script>
function togglePwd() {
    const f = document.getElementById('pwdField');
    f.type = f.type === 'password' ? 'text' : 'password';
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
