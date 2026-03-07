<?php
require_once __DIR__ . '/includes/functions.php';

$pending = $_SESSION['otp_pending'] ?? null;
if (!$pending || empty($pending['entity_type']) || empty($pending['entity_id']) || empty($pending['purpose'])) {
    flash('warning', 'No OTP session found. Please login/register again.');
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$entityType = (string)$pending['entity_type'];
$entityId = (int)$pending['entity_id'];
$purpose = (string)$pending['purpose'];
$returnTo = (string)($pending['return_to'] ?? (BASE_URL . '/homepage.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';

    if ($action === 'resend') {
        if ($entityType === 'user') {
            $userStmt = db()->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
            $userStmt->execute([$entityId]);
            $user = $userStmt->fetch();
            $otp = create_otp_code('user', $entityId, $purpose, 10);
            send_otp_notice($user['email'] ?? null, $otp, $purpose === 'email_verify' ? 'email verification' : 'login');
        } elseif ($entityType === 'employee') {
            $empStmt = db()->prepare('SELECT email FROM employees WHERE id = ? LIMIT 1');
            $empStmt->execute([$entityId]);
            $employee = $empStmt->fetch();
            $otp = create_otp_code('employee', $entityId, 'login', 10);
            send_otp_notice($employee['email'] ?? null, $otp, 'employee login');
        } else {
            $adminStmt = db()->prepare('SELECT email FROM admin WHERE id = ? LIMIT 1');
            $adminStmt->execute([$entityId]);
            $admin = $adminStmt->fetch();
            $otp = create_otp_code('admin', $entityId, 'login', 10);
            send_otp_notice($admin['email'] ?? null, $otp, 'admin login');
        }
        flash('success', 'A new OTP has been generated.');
        header('Location: ' . BASE_URL . '/verify-otp.php');
        exit;
    }

    $otpInput = trim($_POST['otp_code'] ?? '');
    if (!preg_match('/^[0-9]{6}$/', $otpInput)) {
        flash('danger', 'OTP must be a 6-digit code.');
    } elseif (!verify_otp_code($entityType, $entityId, $purpose, $otpInput)) {
        flash('danger', 'Invalid or expired OTP.');
    } else {
        if ($entityType === 'admin') {
            $stmt = db()->prepare('SELECT id, username FROM admin WHERE id = ? LIMIT 1');
            $stmt->execute([$entityId]);
            $admin = $stmt->fetch();
            if (!$admin) {
                flash('danger', 'Admin account not found.');
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            }
            $_SESSION['admin'] = ['id' => (int)$admin['id'], 'username' => $admin['username']];
        } elseif ($entityType === 'employee') {
            $stmt = db()->prepare('SELECT id, full_name, username, email FROM employees WHERE id = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$entityId]);
            $employee = $stmt->fetch();
            if (!$employee) {
                flash('danger', 'Employee account not found.');
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            }
            $_SESSION['employee'] = [
                'id' => (int)$employee['id'],
                'full_name' => $employee['full_name'],
                'username' => $employee['username'],
                'email' => $employee['email'],
            ];
        } else {
            if ($purpose === 'email_verify') {
                db()->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$entityId]);
            }

            $stmt = db()->prepare('SELECT id, full_name, email, phone FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$entityId]);
            $user = $stmt->fetch();
            if (!$user) {
                flash('danger', 'User account not found.');
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            }

            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
            ];
        }

        unset($_SESSION['otp_pending']);
        flash('success', 'OTP verified successfully.');
        header('Location: ' . $returnTo);
        exit;
    }
}

include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="detail-box p-4">
            <h1 class="h3 mb-3">OTP Verification</h1>
            <p class="text-muted small">Enter the 6-digit OTP code to continue.</p>
            <form method="post" class="mb-2">
                <input type="hidden" name="action" value="verify">
                <div class="mb-3">
                    <label class="form-label">OTP Code</label>
                    <input name="otp_code" class="form-control" maxlength="6" pattern="[0-9]{6}" required>
                </div>
                <button class="btn btn-dark w-100">Verify OTP</button>
            </form>
            <form method="post">
                <input type="hidden" name="action" value="resend">
                <button class="btn btn-outline-dark w-100">Resend OTP</button>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
