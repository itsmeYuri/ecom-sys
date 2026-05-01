<?php
require_once __DIR__ . '/includes/functions.php';

$pending = $_SESSION['captcha_2fa_pending'] ?? null;
if (!$pending || empty($pending['entity_type']) || empty($pending['entity_id'])) {
    flash('warning', 'No session found. Please login again.');
    header('Location: ' . BASE_URL . '/login.php'); exit;
}

$captchaSiteKey = get_setting('recaptcha_site_key', '');
if ($captchaSiteKey === '') {
    flash('warning', 'reCAPTCHA is not configured.');
    header('Location: ' . BASE_URL . '/login.php'); exit;
}

$entityType = $pending['entity_type'];
$entityId   = (int)$pending['entity_id'];
$returnTo   = $pending['return_to'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!verify_recaptcha($_POST['g-recaptcha-response'] ?? '')) {
        flash('danger', 'CAPTCHA verification failed. Please try again.');
        header('Location: ' . BASE_URL . '/verify-captcha.php'); exit;
    }

    // Finalize login
    if ($entityType === 'user') {
        $stmt = db()->prepare('SELECT id, full_name, email, phone FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$entityId]);
        $row = $stmt->fetch();
        if (!$row) { flash('danger', 'User not found.'); header('Location: ' . BASE_URL . '/login.php'); exit; }
        $_SESSION['user']          = ['id'=>(int)$row['id'],'full_name'=>$row['full_name'],'email'=>$row['email'],'phone'=>$row['phone']];
        $_SESSION['last_activity'] = time();
        audit_log('user', (int)$row['id'], $row['email'], 'LOGIN', 'User login via reCAPTCHA');
    } elseif ($entityType === 'admin') {
        $stmt = db()->prepare('SELECT id, username, email FROM admin WHERE id=? LIMIT 1');
        $stmt->execute([$entityId]);
        $row = $stmt->fetch();
        if (!$row) { flash('danger', 'Admin not found.'); header('Location: ' . BASE_URL . '/login.php'); exit; }
        $_SESSION['admin']         = ['id'=>(int)$row['id'],'username'=>$row['username'],'email'=>$row['email']];
        $_SESSION['last_activity'] = time();
        audit_log('admin', (int)$row['id'], $row['username'], 'LOGIN', 'Admin login via reCAPTCHA');
    } elseif ($entityType === 'employee') {
        $stmt = db()->prepare('SELECT id, full_name, username, email FROM employees WHERE id=? AND is_active=1 LIMIT 1');
        $stmt->execute([$entityId]);
        $row = $stmt->fetch();
        if (!$row) { flash('danger', 'Employee not found.'); header('Location: ' . BASE_URL . '/login.php'); exit; }
        $_SESSION['employee']      = ['id'=>(int)$row['id'],'full_name'=>$row['full_name'],'username'=>$row['username'],'email'=>$row['email']];
        $_SESSION['last_activity'] = time();
        audit_log('employee', (int)$row['id'], $row['username'], 'LOGIN', 'Employee login via reCAPTCHA');
    }

    unset($_SESSION['captcha_2fa_pending']);
    flash('success', 'Signed in successfully. Welcome back!');
    header('Location: ' . $returnTo); exit;
}

include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="detail-box p-4 p-md-5 text-center">
            <div style="font-size:2.5rem;margin-bottom:8px;">&#129302;</div>
            <h1 class="h4 fw-bold mb-1">Verify You&rsquo;re Human</h1>
            <p class="text-muted small mb-4">Complete the CAPTCHA below to sign in.</p>
            <form method="post">
                <?= csrf_field() ?>
                <div class="d-flex justify-content-center mb-3">
                    <div class="g-recaptcha" data-sitekey="<?= e($captchaSiteKey) ?>"></div>
                </div>
                <button class="btn btn-dark w-100">Verify &amp; Sign In</button>
            </form>
            <p class="small mt-3 mb-0"><a href="<?= BASE_URL ?>/choose-2fa.php">&#8592; Choose another method</a></p>
        </div>
    </div>
</div>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php include __DIR__ . '/footer.php'; ?>
