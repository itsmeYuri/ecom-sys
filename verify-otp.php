<?php
require_once __DIR__ . '/includes/functions.php';

$pending = $_SESSION['otp_pending'] ?? null;
if (!$pending || empty($pending['entity_type']) || empty($pending['entity_id']) || empty($pending['purpose'])) {
    flash('warning', 'No OTP session found. Please login again.');
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$entityType = (string)$pending['entity_type'];
$entityId   = (int)$pending['entity_id'];
$purpose    = (string)$pending['purpose'];
$returnTo   = (string)($pending['return_to'] ?? (BASE_URL . '/homepage.php'));
$showTotpStep = !empty($pending['totp_step']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'verify';

    if ($action === 'resend') {
        if ($entityType === 'user') {
            $r = db()->prepare('SELECT email FROM users WHERE id=? LIMIT 1');
            $r->execute([$entityId]);
            $email = ($r->fetch())['email'] ?? null;
            $otp   = create_otp_code('user', $entityId, $purpose, 10);
            send_otp_notice($email, $otp, $purpose === 'email_verify' ? 'email verification' : 'login');
        } elseif ($entityType === 'employee') {
            $r = db()->prepare('SELECT email FROM employees WHERE id=? LIMIT 1');
            $r->execute([$entityId]);
            $email = ($r->fetch())['email'] ?? null;
            $otp   = create_otp_code('employee', $entityId, 'login', 10);
            send_otp_notice($email, $otp, 'employee login');
        } else {
            $r = db()->prepare('SELECT email FROM admin WHERE id=? LIMIT 1');
            $r->execute([$entityId]);
            $email = ($r->fetch())['email'] ?? null;
            $otp   = create_otp_code('admin', $entityId, 'login', 10);
            send_otp_notice($email, $otp, 'admin login');
        }
        flash('success', 'A new OTP has been sent to your email.');
        header('Location: ' . BASE_URL . '/verify-otp.php');
        exit;
    }

    if ($action === 'totp_verify') {
        $totpCode   = preg_replace('/\D/', '', trim($_POST['totp_code'] ?? ''));
        $totpSecret = totp_get_secret($entityType, $entityId);
        if (!$totpSecret || !totp_verify($totpSecret, $totpCode)) {
            flash('danger', 'Invalid authenticator code. Please try again.');
            header('Location: ' . BASE_URL . '/verify-otp.php');
            exit;
        }
        goto finalize;
    }

    // Email OTP verify
    $otpInput = trim($_POST['otp_code'] ?? '');
    if (!preg_match('/^[0-9]{6}$/', $otpInput)) {
        flash('danger', 'OTP must be exactly 6 digits.');
        header('Location: ' . BASE_URL . '/verify-otp.php');
        exit;
    }
    if (!verify_otp_code($entityType, $entityId, $purpose, $otpInput)) {
        flash('danger', 'Invalid or expired OTP.');
        header('Location: ' . BASE_URL . '/verify-otp.php');
        exit;
    }

    // Escalate to TOTP step if enabled
    $totpSecret = totp_get_secret($entityType, $entityId);
    $totpEnabled = $totpSecret && !empty($pending['totp_check']);
    if ($totpEnabled) {
        $_SESSION['otp_pending']['otp_verified'] = true;
        $_SESSION['otp_pending']['totp_step']    = true;
        flash('success', 'OTP verified. Now enter your Google Authenticator code.');
        header('Location: ' . BASE_URL . '/verify-otp.php');
        exit;
    }

    finalize:
    if ($entityType === 'admin') {
        $stmt = db()->prepare('SELECT id, username, email FROM admin WHERE id=? LIMIT 1');
        $stmt->execute([$entityId]);
        $row = $stmt->fetch();
        if (!$row) { flash('danger', 'Admin not found.'); header('Location: ' . BASE_URL . '/login.php'); exit; }
        $_SESSION['admin']         = ['id' => (int)$row['id'], 'username' => $row['username'], 'email' => $row['email']];
        $_SESSION['last_activity'] = time();
        audit_log('admin', (int)$row['id'], $row['username'], 'LOGIN', 'Admin login via OTP');

    } elseif ($entityType === 'employee') {
        $stmt = db()->prepare('SELECT id, full_name, username, email FROM employees WHERE id=? AND is_active=1 LIMIT 1');
        $stmt->execute([$entityId]);
        $row = $stmt->fetch();
        if (!$row) { flash('danger', 'Employee not found.'); header('Location: ' . BASE_URL . '/login.php'); exit; }
        $_SESSION['employee']      = ['id' => (int)$row['id'], 'full_name' => $row['full_name'], 'username' => $row['username'], 'email' => $row['email']];
        $_SESSION['last_activity'] = time();
        audit_log('employee', (int)$row['id'], $row['username'], 'LOGIN', 'Employee login via OTP');

    } else {
        if ($purpose === 'email_verify') {
            db()->prepare('UPDATE users SET is_verified=1, password_changed_at=COALESCE(password_changed_at,NOW()) WHERE id=?')->execute([$entityId]);
        }
        $stmt = db()->prepare('SELECT id, full_name, email, phone FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$entityId]);
        $row = $stmt->fetch();
        if (!$row) { flash('danger', 'User not found.'); header('Location: ' . BASE_URL . '/login.php'); exit; }
        $_SESSION['user']          = ['id' => (int)$row['id'], 'full_name' => $row['full_name'], 'email' => $row['email'], 'phone' => $row['phone']];
        $_SESSION['last_activity'] = time();
        audit_log('user', (int)$row['id'], $row['email'], 'LOGIN', 'User login via OTP');
    }

    unset($_SESSION['otp_pending']);
    flash('success', 'Signed in successfully. Welcome back!');
    header('Location: ' . $returnTo);
    exit;
}

include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="detail-box p-4 p-md-5 text-center">
            <div style="font-size:2.5rem; margin-bottom:8px;">&#128274;</div>
            <h1 class="h4 fw-bold mb-1">
                <?= $showTotpStep ? 'Authenticator Verification' : 'OTP Verification' ?>
            </h1>
            <p class="text-muted small mb-4">
                <?= $showTotpStep
                    ? 'Enter the 6-digit code from your Google Authenticator app.'
                    : 'Enter the 6-digit code sent to your registered email.' ?>
            </p>

            <?php if ($showTotpStep): ?>
            <form method="post" class="text-start">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="totp_verify">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Authenticator Code</label>
                    <input name="totp_code" class="form-control form-control-lg text-center"
                           maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                           required autofocus inputmode="numeric">
                </div>
                <button class="btn btn-dark w-100">Verify</button>
            </form>

            <?php else: ?>
            <form method="post" class="mb-3 text-start">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify">
                <div class="mb-3">
                    <label class="form-label fw-semibold">OTP Code</label>
                    <input name="otp_code" class="form-control form-control-lg text-center"
                           maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                           required autofocus inputmode="numeric">
                </div>
                <button class="btn btn-dark w-100">Verify OTP</button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resend">
                <button class="btn btn-outline-secondary w-100 btn-sm">Resend OTP</button>
            </form>
            <?php endif; ?>

            <p class="small mt-3 mb-0">
                <a href="<?= BASE_URL ?>/login.php">&#8592; Back to Login</a>
            </p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
