<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user   = current_user();
$userId = (int)$user['id'];

$stmt = db()->prepare('SELECT totp_enabled FROM users WHERE id=? LIMIT 1');
$stmt->execute([$userId]);
$totpEnabled = (bool)($stmt->fetchColumn());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'start') {
        $_SESSION['totp_setup_secret'] = totp_generate_secret();
        header('Location: ' . BASE_URL . '/setup-totp.php'); exit;
    }

    if ($action === 'confirm') {
        $tempSecret = $_SESSION['totp_setup_secret'] ?? '';
        $code       = preg_replace('/\D/', '', trim($_POST['totp_code'] ?? ''));
        if (!$tempSecret) {
            flash('danger', 'Setup session expired. Please start again.');
            header('Location: ' . BASE_URL . '/setup-totp.php'); exit;
        }
        if (!totp_verify($tempSecret, $code)) {
            flash('danger', 'Incorrect code. Make sure your authenticator app is synced and try again.');
            header('Location: ' . BASE_URL . '/setup-totp.php'); exit;
        }
        totp_save_secret('user', $userId, $tempSecret);
        db()->prepare('UPDATE users SET totp_enabled=1 WHERE id=?')->execute([$userId]);
        unset($_SESSION['totp_setup_secret']);
        audit_log('user', $userId, $user['email'] ?? '', 'TOTP_ENABLED', 'Google Authenticator enabled');
        flash('success', 'Google Authenticator is now active on your account.');
        header('Location: ' . BASE_URL . '/setup-totp.php'); exit;
    }

    if ($action === 'disable') {
        db()->prepare('DELETE FROM totp_secrets WHERE entity_type=? AND entity_id=?')->execute(['user', $userId]);
        db()->prepare('UPDATE users SET totp_enabled=0 WHERE id=?')->execute([$userId]);
        audit_log('user', $userId, $user['email'] ?? '', 'TOTP_DISABLED', 'Google Authenticator disabled');
        flash('success', 'Google Authenticator has been removed from your account.');
        header('Location: ' . BASE_URL . '/setup-totp.php'); exit;
    }
}

// Build otpauth URI for QR code (client-side rendering only — no external image API)
$tempSecret = $_SESSION['totp_setup_secret'] ?? null;
$otpauthUri = null;
if ($tempSecret) {
    $issuer     = APP_NAME;
    $label      = $issuer . ':' . ($user['email'] ?? 'User');
    $otpauthUri = 'otpauth://totp/' . rawurlencode($label)
                . '?secret=' . rawurlencode($tempSecret)
                . '&issuer=' . rawurlencode($issuer);
}

include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
<div class="col-md-7 col-lg-5">
<div class="detail-box p-4">

    <div class="mb-4">
        <a href="<?= BASE_URL ?>/profile.php" class="text-muted text-decoration-none small">&#8592; My Profile</a>
    </div>

    <h1 class="h4 fw-bold mb-1">&#128241; Google Authenticator</h1>
    <p class="text-muted small mb-4">Add an extra layer of security with a time-based one-time password (TOTP) app.</p>

    <?php if ($totpEnabled): ?>
    <!-- ── Already enabled ── -->
    <div class="alert alert-success d-flex align-items-center gap-3 mb-4" style="border-radius:12px;">
        <span style="font-size:1.6rem;line-height:1;">&#9989;</span>
        <div>
            <div class="fw-semibold">Authenticator is active</div>
            <div class="small">Your account is protected with Google Authenticator.</div>
        </div>
    </div>
    <form method="post" onsubmit="return confirm('Remove Google Authenticator from your account?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="disable">
        <button class="btn btn-outline-danger w-100">Remove Authenticator</button>
    </form>

    <?php elseif ($tempSecret && $otpauthUri): ?>
    <!-- ── Step 2: scan QR + confirm ── -->
    <div class="mb-3">
        <div class="fw-semibold mb-3">Step 1 &mdash; Scan this QR code in Google Authenticator</div>

        <!-- QR rendered client-side via qrcodejs — no external image API -->
        <div class="text-center mb-3">
            <div id="qrCodeBox"
                 style="display:inline-block;background:#ffffff;padding:14px;border-radius:14px;line-height:0;">
            </div>
        </div>

        <div class="text-center small text-muted mb-1">Or enter this key manually in your app:</div>
        <div class="text-center mb-3">
            <code id="totpSecret" style="font-size:.85rem;word-break:break-all;user-select:all;cursor:pointer;"
                  title="Click to copy" onclick="copySecret()"><?= e($tempSecret) ?></code>
            <div id="copyNote" class="small text-success mt-1" style="display:none;">Copied!</div>
        </div>
    </div>

    <hr class="my-3">

    <div class="fw-semibold mb-2">Step 2 &mdash; Enter the 6-digit code shown in your app</div>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="confirm">
        <div class="mb-3">
            <input name="totp_code"
                   class="form-control form-control-lg text-center fw-bold"
                   maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                   required autofocus inputmode="numeric"
                   style="letter-spacing:8px;font-size:1.5rem;">
        </div>
        <button class="btn btn-dark w-100 mb-2">Verify &amp; Enable</button>
    </form>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="start">
        <button class="btn btn-outline-secondary w-100 btn-sm">Generate new QR code</button>
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    new QRCode(document.getElementById('qrCodeBox'), {
        text    : <?= json_encode($otpauthUri) ?>,
        width   : 200,
        height  : 200,
        colorDark : '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
    function copySecret() {
        const s = document.getElementById('totpSecret').innerText;
        navigator.clipboard.writeText(s).then(() => {
            const n = document.getElementById('copyNote');
            n.style.display = 'block';
            setTimeout(() => n.style.display = 'none', 2000);
        });
    }
    </script>

    <?php else: ?>
    <!-- ── Step 1: start setup ── -->
    <div class="mb-4">
        <ol class="small text-muted ps-3 mb-0">
            <li class="mb-2">Install <strong>Google Authenticator</strong> or <strong>Authy</strong> on your phone.</li>
            <li class="mb-2">Tap the <strong>+</strong> button in the app and choose <strong>Scan QR code</strong>.</li>
            <li>Click the button below, scan the QR, then enter the 6-digit code to confirm.</li>
        </ol>
    </div>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="start">
        <button class="btn btn-dark w-100">Set Up Google Authenticator</button>
    </form>
    <?php endif; ?>

</div>
</div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
