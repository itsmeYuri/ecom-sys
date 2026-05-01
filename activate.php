<?php
require_once __DIR__ . '/includes/functions.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    flash('danger', 'Invalid or missing activation link.');
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$row = verify_activation_token($token);

if (!$row) {
    include __DIR__ . '/header.php';
    ?>
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="detail-box p-4 text-center">
                <div style="font-size:3rem;">&#10060;</div>
                <h2 class="h4 fw-bold mt-2">Link Expired or Invalid</h2>
                <p class="text-muted">This activation link has expired or already been used.</p>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-dark">Back to Login</a>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/footer.php';
    exit;
}

// Mark verified
$userId = (int)$row['user_id'];
consume_activation_token((int)$row['id']);
db()->prepare('UPDATE users SET is_verified=1, password_changed_at=COALESCE(password_changed_at,NOW()) WHERE id=?')->execute([$userId]);
audit_log('user', $userId, null, 'ACCOUNT_ACTIVATED', 'Email activation link used');

include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="detail-box p-4 text-center">
            <div style="font-size:3rem;">&#9989;</div>
            <h2 class="h4 fw-bold mt-2">Account Activated!</h2>
            <p class="text-muted">Your account has been successfully verified. You can now log in.</p>
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-dark">Go to Login</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
