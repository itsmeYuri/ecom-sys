<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) { header('Location: ' . BASE_URL . '/homepage.php'); exit; }

$useEmailActivation = get_setting('enable_email_activation', '1') === '1';
$captchaSiteKey     = get_setting('recaptcha_site_key', '');
$minLen             = (int)get_setting('password_min_length', '8');
$requireUpper       = get_setting('password_require_upper', '1') === '1';
$requireNumber      = get_setting('password_require_number', '1') === '1';
$requireSpecial     = get_setting('password_require_special', '1') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($captchaSiteKey !== '') {
        if (!verify_recaptcha($_POST['g-recaptcha-response'] ?? '')) {
            flash('danger', 'CAPTCHA verification failed. Please try again.');
            header('Location: ' . BASE_URL . '/register.php'); exit;
        }
    }

    $fullName        = trim($_POST['full_name']       ?? '');
    $email           = trim($_POST['email']           ?? '');
    $phone           = trim($_POST['phone']           ?? '');
    $password        = $_POST['password']             ?? '';
    $confirmPassword = $_POST['confirm_password']     ?? '';
    $agreeTerms      = !empty($_POST['agree_terms']);

    $errors = [];
    if ($fullName === '')                                                $errors[] = 'Full name is required.';
    if ($email === '')                                                   $errors[] = 'Email address is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))                 $errors[] = 'Invalid email format.';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone))  $errors[] = 'Invalid phone format.';
    $pwdErr = validate_password($password);
    if ($pwdErr) $errors[] = $pwdErr;
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';
    if (!$agreeTerms) $errors[] = 'You must agree to the Terms and Privacy Policy.';

    if (empty($errors)) {
        $chk = db()->prepare('SELECT id FROM users WHERE (email=? AND ?<>"") OR (phone=? AND ?<>"") LIMIT 1');
        $chk->execute([$email,$email,$phone,$phone]);
        if ($chk->fetch()) $errors[] = 'An account with this email or phone already exists.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
        $stmt = db()->prepare('INSERT INTO users (full_name, email, phone, password_hash, is_verified, password_changed_at) VALUES (?,?,?,?,0,NOW())');
        $stmt->execute([$fullName, $email ?: null, $phone ?: null, $hash]);
        $userId = (int)db()->lastInsertId();
        audit_log('user', $userId, $email, 'REGISTER', 'New user registration');

        if ($useEmailActivation) {
            send_activation_email($userId, $email, $fullName);
            flash('success', 'Account created! Check your email for an activation link (expires in 24 hours).');
            header('Location: ' . BASE_URL . '/login.php');
        } else {
            $otp = create_otp_code('user', $userId, 'email_verify', 10);
            send_otp_notice($email, $otp, 'email verification');
            $_SESSION['otp_pending'] = ['entity_type'=>'user','entity_id'=>$userId,'purpose'=>'email_verify','return_to'=>BASE_URL.'/homepage.php','totp_check'=>false];
            flash('success', 'Account created! Enter the OTP sent to your email.');
            header('Location: ' . BASE_URL . '/verify-otp.php');
        }
        exit;
    }

    foreach ($errors as $err) flash('danger', $err);
}

include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="detail-box p-4 p-md-5">
            <div class="text-center mb-4">
                <h1 class="h3 fw-bold mb-1">Create Account</h1>
                <p class="text-muted small">Join <?= e(APP_NAME) ?> today &mdash; it's free</p>
            </div>

            <form method="post" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input name="full_name" class="form-control" placeholder="John Dela Cruz" required
                           value="<?= e((string)($_POST['full_name'] ?? '')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input name="email" type="email" class="form-control" placeholder="your@email.com" required
                           value="<?= e((string)($_POST['email'] ?? '')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Phone <span class="text-muted small">(optional)</span></label>
                    <input name="phone" class="form-control" placeholder="+63 912 345 6789"
                           value="<?= e((string)($_POST['phone'] ?? '')) ?>">
                </div>
                <div class="mb-1">
                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="pwdNew" class="form-control" placeholder="Password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('pwdNew')" tabindex="-1">&#128065;</button>
                    </div>
                    <div class="form-text">Min <?= $minLen ?> chars<?= $requireUpper?' + uppercase':'' ?><?= $requireNumber?' + number':'' ?><?= $requireSpecial?' + special char':'' ?>.</div>
                    <div class="progress mt-1" id="pwdStrengthWrap" style="height:4px;display:none;">
                        <div id="pwdStrengthFill" class="progress-bar" style="width:0%"></div>
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="pwdConfirm" class="form-control" placeholder="Repeat password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('pwdConfirm')" tabindex="-1">&#128065;</button>
                    </div>
                </div>

                <?php if ($captchaSiteKey !== ''): ?>
                <div class="mb-3">
                    <div class="g-recaptcha" data-sitekey="<?= e($captchaSiteKey) ?>"></div>
                </div>
                <?php endif; ?>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="agree_terms" class="form-check-input" id="agreeTerms" required>
                    <label class="form-check-label small" for="agreeTerms">
                        I agree to the <a href="<?= BASE_URL ?>/terms.php" target="_blank">Terms &amp; Conditions</a>
                        and <a href="<?= BASE_URL ?>/privacy-policy.php" target="_blank">Privacy Policy</a>.
                    </label>
                </div>

                <button type="submit" class="btn btn-dark w-100">Create Account</button>
            </form>

            <p class="text-center small mt-3 mb-0">
                Already have an account? <a href="<?= BASE_URL ?>/login.php" class="fw-semibold">Sign in</a>
            </p>
        </div>
    </div>
</div>

<?php if ($captchaSiteKey !== ''): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
<script>
function togglePwd(id) {
    const f = document.getElementById(id);
    f.type = f.type === 'password' ? 'text' : 'password';
}
const pwdField = document.getElementById('pwdNew');
const wrap = document.getElementById('pwdStrengthWrap');
const fill = document.getElementById('pwdStrengthFill');
const colours = ['#dc3545','#fd7e14','#ffc107','#20c997','#198754'];
pwdField.addEventListener('input', () => {
    const v = pwdField.value;
    wrap.style.display = v ? 'flex' : 'none';
    let s = 0;
    if (v.length >= <?= $minLen ?>) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[\W_]/.test(v)) s++;
    if (v.length >= 12) s++;
    s = Math.min(4, Math.max(0, s - 1));
    fill.style.width = ((s + 1) * 20) + '%';
    fill.style.backgroundColor = colours[s];
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
