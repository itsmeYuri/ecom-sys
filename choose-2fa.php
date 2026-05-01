<?php
require_once __DIR__ . '/includes/functions.php';

$pending = $_SESSION['choose_2fa_pending'] ?? null;
if (!$pending || empty($pending['entity_type']) || empty($pending['entity_id'])) {
    flash('warning', 'No authentication session found. Please login again.');
    header('Location: ' . BASE_URL . '/login.php'); exit;
}

$entityType     = $pending['entity_type'];
$entityId       = (int)$pending['entity_id'];
$purpose        = $pending['purpose'];
$returnTo       = $pending['return_to'];
$hasTOTP        = (bool)totp_get_secret($entityType, $entityId);
$captchaSiteKey = get_setting('recaptcha_site_key', '');
$hasRecaptcha   = $captchaSiteKey !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $method = $_POST['method'] ?? '';

    if ($method === 'email') {
        $tables = ['user' => 'users', 'admin' => 'admin', 'employee' => 'employees'];
        $tbl    = $tables[$entityType] ?? 'users';
        $stmt   = db()->prepare("SELECT email FROM {$tbl} WHERE id=? LIMIT 1");
        $stmt->execute([$entityId]);
        $email  = ($stmt->fetch())['email'] ?? null;
        $otp    = create_otp_code($entityType, $entityId, $purpose, 10);
        send_otp_notice($email, $otp, 'login');
        $_SESSION['otp_pending'] = [
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'purpose'     => $purpose,
            'return_to'   => $returnTo,
            'totp_check'  => false,
        ];
        unset($_SESSION['choose_2fa_pending']);
        flash('info', 'A 6-digit code has been sent to your email.');
        header('Location: ' . BASE_URL . '/verify-otp.php'); exit;

    } elseif ($method === 'totp' && $hasTOTP) {
        $_SESSION['otp_pending'] = [
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'purpose'     => $purpose,
            'return_to'   => $returnTo,
            'totp_check'  => true,
            'totp_step'   => true,
        ];
        unset($_SESSION['choose_2fa_pending']);
        header('Location: ' . BASE_URL . '/verify-otp.php'); exit;

    } elseif ($method === 'captcha' && $hasRecaptcha) {
        $_SESSION['captcha_2fa_pending'] = [
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'return_to'   => $returnTo,
        ];
        unset($_SESSION['choose_2fa_pending']);
        header('Location: ' . BASE_URL . '/verify-captcha.php'); exit;
    }
}

include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
<div class="col-md-6 col-lg-5">
<div class="detail-box p-4 p-md-5">

    <div class="text-center mb-4">
        <div style="font-size:2.2rem;line-height:1;margin-bottom:10px;">&#128274;</div>
        <h1 class="h4 fw-bold mb-1">Verify Your Identity</h1>
        <p class="text-muted small mb-0">Choose a verification method to continue</p>
    </div>

    <form method="post" class="d-flex flex-column gap-3">
        <?= csrf_field() ?>

        <!-- Email OTP — always available -->
        <button name="method" value="email"
                class="btn btn-dark text-start d-flex align-items-center gap-3 p-3"
                style="border-radius:12px;">
            <span style="font-size:1.6rem;line-height:1;flex-shrink:0;">&#128231;</span>
            <div>
                <div class="fw-semibold">Email OTP</div>
                <div class="small opacity-75">Send a 6-digit code to your registered email</div>
            </div>
        </button>

        <!-- Google Authenticator -->
        <?php if ($hasTOTP): ?>
        <button name="method" value="totp"
                class="btn btn-outline-dark text-start d-flex align-items-center gap-3 p-3"
                style="border-radius:12px;">
            <span style="font-size:1.6rem;line-height:1;flex-shrink:0;">&#128241;</span>
            <div>
                <div class="fw-semibold">Google Authenticator</div>
                <div class="small opacity-75">Enter the 6-digit code from your authenticator app</div>
            </div>
        </button>
        <?php else: ?>
        <div class="d-flex align-items-center gap-3 p-3"
             style="border-radius:12px;border:1px solid #ddd;opacity:.55;cursor:not-allowed;">
            <span style="font-size:1.6rem;line-height:1;flex-shrink:0;">&#128241;</span>
            <div>
                <div class="fw-semibold">Google Authenticator</div>
                <div class="small">
                    Not set up &mdash;
                    <?php if ($entityType === 'user'): ?>
                    <a href="<?= BASE_URL ?>/setup-totp.php" class="fw-semibold" style="color:inherit;">set it up in your profile</a>
                    <?php else: ?>
                    contact admin to enable
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- reCAPTCHA -->

    </form>

    <p class="text-center small mt-4 mb-0">
        <a href="<?= BASE_URL ?>/login.php">&#8592; Back to Login</a>
    </p>
</div>
</div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
