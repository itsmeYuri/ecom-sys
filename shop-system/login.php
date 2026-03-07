<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}
if (is_admin_logged_in()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}
if (is_employee_logged_in()) {
    header('Location: ' . BASE_URL . '/employee/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';
    $returnTo = trim($_POST['return_to'] ?? '');

    $adminStmt = db()->prepare('SELECT * FROM admin WHERE username = ? OR email = ? LIMIT 1');
    $adminStmt->execute([$identity, $identity]);
    $admin = $adminStmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        if (empty($admin['email']) || !filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Admin account has no valid email for OTP. Please set admin email first.');
        } else {
            $otp = create_otp_code('admin', (int)$admin['id'], 'login', 10);
            send_otp_notice($admin['email'], $otp, 'admin login');
            $_SESSION['otp_pending'] = [
                'entity_type' => 'admin',
                'entity_id' => (int)$admin['id'],
                'purpose' => 'login',
                'return_to' => BASE_URL . '/admin/index.php',
            ];
            header('Location: ' . BASE_URL . '/verify-otp.php');
            exit;
        }
    }

    $empStmt = db()->prepare('SELECT * FROM employees WHERE username = ? OR email = ? LIMIT 1');
    $empStmt->execute([$identity, $identity]);
    $employee = $empStmt->fetch();
    if ($employee && !empty($employee['is_active']) && password_verify($password, $employee['password_hash'])) {
        $otp = create_otp_code('employee', (int)$employee['id'], 'login', 10);
        send_otp_notice($employee['email'] ?? null, $otp, 'employee login');
        $_SESSION['otp_pending'] = [
            'entity_type' => 'employee',
            'entity_id' => (int)$employee['id'],
            'purpose' => 'login',
            'return_to' => BASE_URL . '/employee/index.php',
        ];
        header('Location: ' . BASE_URL . '/verify-otp.php');
        exit;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? OR phone = ? LIMIT 1');
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('danger', 'Invalid credentials.');
    } else {
        if (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'This account has no valid email for OTP. Please contact admin.');
        } else {
            $fallback = BASE_URL . '/homepage.php';
            $target = ($returnTo !== '' && substr($returnTo, 0, 1) === '/') ? $returnTo : $fallback;

            if (empty($user['is_verified'])) {
                $otp = create_otp_code('user', (int)$user['id'], 'email_verify', 10);
                send_otp_notice($user['email'] ?? null, $otp, 'email verification');
                $_SESSION['otp_pending'] = [
                    'entity_type' => 'user',
                    'entity_id' => (int)$user['id'],
                    'purpose' => 'email_verify',
                    'return_to' => $target,
                ];
                flash('warning', 'Account is not verified. Please enter OTP.');
                header('Location: ' . BASE_URL . '/verify-otp.php');
            } else {
                $otp = create_otp_code('user', (int)$user['id'], 'login', 10);
                send_otp_notice($user['email'] ?? null, $otp, 'login');
                $_SESSION['otp_pending'] = [
                    'entity_type' => 'user',
                    'entity_id' => (int)$user['id'],
                    'purpose' => 'login',
                    'return_to' => $target,
                ];
                header('Location: ' . BASE_URL . '/verify-otp.php');
            }
            exit;
        }
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
