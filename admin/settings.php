<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $keys = [
        'max_login_attempts', 'lockout_duration_minutes', 'session_timeout_minutes',
        'password_min_length', 'password_require_upper', 'password_require_number',
        'password_require_special', 'password_expiry_days',
        'recaptcha_site_key', 'recaptcha_secret_key',
        'paymongo_secret_key', 'paymongo_public_key', 'paymongo_webhook_secret',
        'enable_totp', 'enable_email_activation', 'maintenance_mode',
    ];

    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            // Numeric fields: clamp to safe ranges
            if (in_array($key, ['max_login_attempts'], true))         $val = (string)max(1, min(20, (int)$val));
            if (in_array($key, ['lockout_duration_minutes'], true))   $val = (string)max(1, min(1440, (int)$val));
            if (in_array($key, ['session_timeout_minutes'], true))    $val = (string)max(1, min(1440, (int)$val));
            if (in_array($key, ['password_min_length'], true))        $val = (string)max(6, min(128, (int)$val));
            if (in_array($key, ['password_expiry_days'], true))       $val = (string)max(0, min(365, (int)$val));
            if (in_array($key, ['password_require_upper','password_require_number','password_require_special','enable_totp','enable_email_activation','maintenance_mode'], true)) {
                $val = isset($_POST[$key]) ? '1' : '0';
            }
            set_setting($key, $val);
        } else {
            // Checkbox not checked — save as 0
            if (in_array($key, ['password_require_upper','password_require_number','password_require_special','enable_totp','enable_email_activation','maintenance_mode'], true)) {
                set_setting($key, '0');
            }
        }
    }

    audit_log('admin', (int)($_SESSION['admin']['id'] ?? 0), $_SESSION['admin']['username'] ?? 'admin', 'SETTINGS_UPDATE', 'Admin updated system settings');
    flash('success', 'Settings saved successfully.');
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$s = [];
$settingKeys = [
    'max_login_attempts','lockout_duration_minutes','session_timeout_minutes',
    'password_min_length','password_require_upper','password_require_number',
    'password_require_special','password_expiry_days',
    'recaptcha_site_key','recaptcha_secret_key',
    'paymongo_secret_key','paymongo_public_key','paymongo_webhook_secret',
    'enable_totp','enable_email_activation','maintenance_mode',
];
foreach ($settingKeys as $k) { $s[$k] = get_setting($k); }

include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">System Settings</h1>
        <p class="text-muted small mb-0">Configure security policies and application behaviour</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-dark btn-sm">&#8592; Dashboard</a>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
</div>

<form method="post">
    <?= csrf_field() ?>
    <div class="row g-4">

        <!-- Login Security -->
        <div class="col-lg-6">
            <div class="detail-box p-4">
                <h5 class="fw-bold mb-3">&#128274; Login Security</h5>
                <div class="mb-3">
                    <label class="form-label">Max Login Attempts Before Lockout</label>
                    <input type="number" name="max_login_attempts" class="form-control" min="1" max="20"
                           value="<?= e($s['max_login_attempts']) ?>">
                    <div class="form-text">Account locks after this many consecutive failures.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Lockout Duration (minutes)</label>
                    <input type="number" name="lockout_duration_minutes" class="form-control" min="1" max="1440"
                           value="<?= e($s['lockout_duration_minutes']) ?>">
                    <div class="form-text">How long the window is for counting failed attempts.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Session Timeout (minutes)</label>
                    <input type="number" name="session_timeout_minutes" class="form-control" min="1" max="1440"
                           value="<?= e($s['session_timeout_minutes']) ?>">
                    <div class="form-text">Auto-logout after this many minutes of inactivity.</div>
                </div>
            </div>
        </div>

        <!-- Password Policy -->
        <div class="col-lg-6">
            <div class="detail-box p-4">
                <h5 class="fw-bold mb-3">&#128273; Password Policy</h5>
                <div class="mb-3">
                    <label class="form-label">Minimum Password Length</label>
                    <input type="number" name="password_min_length" class="form-control" min="6" max="128"
                           value="<?= e($s['password_min_length']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password Expiry (days, 0 = never)</label>
                    <input type="number" name="password_expiry_days" class="form-control" min="0" max="365"
                           value="<?= e($s['password_expiry_days']) ?>">
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" name="password_require_upper" class="form-check-input"
                           id="chkUpper" <?= $s['password_require_upper']==='1'?'checked':'' ?>>
                    <label class="form-check-label" for="chkUpper">Require uppercase letter</label>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" name="password_require_number" class="form-check-input"
                           id="chkNumber" <?= $s['password_require_number']==='1'?'checked':'' ?>>
                    <label class="form-check-label" for="chkNumber">Require number</label>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" name="password_require_special" class="form-check-input"
                           id="chkSpecial" <?= $s['password_require_special']==='1'?'checked':'' ?>>
                    <label class="form-check-label" for="chkSpecial">Require special character</label>
                </div>
            </div>
        </div>

        <!-- MFA & Verification -->
        <div class="col-lg-6">
            <div class="detail-box p-4">
                <h5 class="fw-bold mb-3">&#128241; MFA &amp; Verification</h5>
                <div class="form-check mb-3">
                    <input type="checkbox" name="enable_totp" class="form-check-input"
                           id="chkTotp" <?= $s['enable_totp']==='1'?'checked':'' ?>>
                    <label class="form-check-label" for="chkTotp">Enable Google Authenticator (TOTP) for users</label>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="enable_email_activation" class="form-check-input"
                           id="chkActivation" <?= $s['enable_email_activation']==='1'?'checked':'' ?>>
                    <label class="form-check-label" for="chkActivation">
                        Use email activation link (instead of OTP) for new registrations
                    </label>
                </div>
            </div>
        </div>

        <!-- reCAPTCHA -->
        <div class="col-lg-6">
            <div class="detail-box p-4">
                <h5 class="fw-bold mb-3">&#129302; reCAPTCHA v2</h5>
                <p class="small text-muted">Leave empty to disable. Get keys from <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a>.</p>
                <div class="mb-3">
                    <label class="form-label">Site Key (public)</label>
                    <input name="recaptcha_site_key" class="form-control" value="<?= e($s['recaptcha_site_key']) ?>" placeholder="6Lc...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Secret Key (private)</label>
                    <input name="recaptcha_secret_key" class="form-control" value="<?= e($s['recaptcha_secret_key']) ?>" placeholder="6Lc...">
                </div>
            </div>
        </div>

        <!-- PayMongo -->
        <div class="col-lg-6">
            <div class="detail-box p-4">
                <h5 class="fw-bold mb-3">&#128179; PayMongo</h5>
                <p class="small text-muted">Leave empty to disable PayMongo checkout. Use <code>sk_test_</code> / <code>pk_test_</code> for testing.</p>
                <div class="mb-3">
                    <label class="form-label">Secret Key</label>
                    <input name="paymongo_secret_key" class="form-control" value="<?= e($s['paymongo_secret_key']) ?>" placeholder="sk_test_...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Public Key</label>
                    <input name="paymongo_public_key" class="form-control" value="<?= e($s['paymongo_public_key']) ?>" placeholder="pk_test_...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Webhook Secret</label>
                    <input name="paymongo_webhook_secret" class="form-control" value="<?= e($s['paymongo_webhook_secret']) ?>" placeholder="whsec_...">
                    <div class="form-text">From PayMongo Dashboard → Developers → Webhooks. Leave empty to skip signature verification.</div>
                </div>
                <div class="alert alert-light border small py-2 mb-0">
                    <?php
                        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $webhookUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/paymongo-webhook.php';
                    ?>
                    Webhook URL to register in PayMongo:<br>
                    <code><?= e($webhookUrl) ?></code>
                    &nbsp;&mdash;&nbsp;Event: <code>checkout_session.payment.paid</code><br>
                    <span class="text-warning fw-semibold">&#9888; PayMongo cannot reach localhost.</span>
                    Use <a href="https://ngrok.com" target="_blank">ngrok</a> to get a public URL:
                    <code>ngrok http 80</code> &rarr; replace <code>localhost</code> above with your ngrok URL.
                </div>
            </div>
        </div>

        <!-- Maintenance -->
        <div class="col-12">
            <div class="detail-box p-4">
                <h5 class="fw-bold mb-3">&#9881;&#65039; Maintenance</h5>
                <div class="form-check">
                    <input type="checkbox" name="maintenance_mode" class="form-check-input"
                           id="chkMaint" <?= $s['maintenance_mode']==='1'?'checked':'' ?>>
                    <label class="form-check-label" for="chkMaint">
                        <strong>Enable Maintenance Mode</strong> — Only admins can access the site
                    </label>
                </div>
            </div>
        </div>

    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-dark px-5">Save Settings</button>
    </div>
</form>

<?php include __DIR__ . '/../footer.php'; ?>
