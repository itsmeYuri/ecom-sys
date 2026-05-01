<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user   = current_user();
$userId = (int)$user['id'];
$email  = (string)($user['email'] ?? '');

// Generate or load pending secret
if (empty($_SESSION['totp_pending_secret'])) {
    $_SESSION['totp_pending_secret'] = totp_generate_secret();
}
$secret = $_SESSION['totp_pending_secret'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = preg_replace('/\D/', '', trim($_POST['totp_code'] ?? ''));

    if (!totp_verify($secret, $code)) {
        flash('danger', 'Invalid code. Make sure your device time is correct and try again.');
        header('Location: ' . BASE_URL . '/totp-setup.php');
        exit;
    }

    totp_save_secret('user', $userId, $secret);
    db()->prepare('UPDATE users SET totp_enabled=1 WHERE id=?')->execute([$userId]);
    unset($_SESSION['totp_pending_secret']);
    audit_log('user', $userId, $email, 'TOTP_ENABLED', 'User enabled Google Authenticator MFA');
    flash('success', 'Google Authenticator is now enabled on your account.');
    header('Location: ' . BASE_URL . '/profile.php');
    exit;
}

$qrUrl  = totp_qr_url($secret, $email);
include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="detail-box p-4">
            <h1 class="h4 fw-bold mb-1">Set Up Google Authenticator</h1>
            <p class="text-muted small mb-4">Two-factor authentication adds an extra layer of security.</p>

            <div class="text-center mb-4">
                <img src="<?= e($qrUrl) ?>" alt="QR Code" class="img-fluid rounded border" width="200" height="200">
                <p class="small text-muted mt-2">Scan this QR code with <strong>Google Authenticator</strong> or any TOTP app.</p>
            </div>

            <div class="alert alert-light border small mb-4">
                <strong>Manual entry key:</strong><br>
                <code style="font-size:1rem;letter-spacing:3px;"><?= e($secret) ?></code>
            </div>

            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Enter 6-digit code from app to confirm</label>
                    <input name="totp_code" class="form-control form-control-lg text-center"
                           maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                           required autofocus inputmode="numeric">
                </div>
                <button type="submit" class="btn btn-dark w-100">Enable Authenticator</button>
            </form>

            <p class="small mt-3 text-center">
                <a href="<?= BASE_URL ?>/profile.php">&#8592; Back to Profile</a>
            </p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
