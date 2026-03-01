<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';
    $returnTo = trim($_POST['return_to'] ?? '');

    $adminStmt = db()->prepare('SELECT * FROM admin WHERE username = ? LIMIT 1');
    $adminStmt->execute([$identity]);
    $admin = $adminStmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin'] = ['id' => (int)$admin['id'], 'username' => $admin['username']];
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? OR phone = ? LIMIT 1');
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('danger', 'Invalid credentials.');
    } else {
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
        ];
        $fallback = BASE_URL . '/index.php';
        if ($returnTo !== '' && substr($returnTo, 0, 1) === '/') {
            header('Location: ' . $returnTo);
        } else {
            header('Location: ' . $fallback);
        }
        exit;
    }
}

include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="detail-box p-4">
            <h1 class="h3 mb-3">Login</h1>
            <form method="post">
                <input type="hidden" name="return_to" value="<?= e((string)($_GET['return_to'] ?? '')) ?>">
                <div class="mb-3"><label class="form-label">Email, Phone, or Username</label><input name="identity" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <button class="btn btn-dark w-100">Login</button>
            </form>
            <p class="small mt-3 mb-0">No account? <a href="<?= BASE_URL ?>/register.php">Register here</a></p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
