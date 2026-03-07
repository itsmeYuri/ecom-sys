<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '') {
        flash('danger', 'Full name is required.');
    } elseif ($email === '') {
        flash('danger', 'Email is required for OTP verification.');
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Invalid email format.');
    } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
        flash('danger', 'Invalid phone format.');
    } elseif (strlen($password) < 6) {
        flash('danger', 'Password must be at least 6 characters.');
    } elseif ($password !== $confirmPassword) {
        flash('danger', 'Passwords do not match.');
    } else {
        $check = db()->prepare('SELECT id FROM users WHERE (email = ? AND ? <> "") OR (phone = ? AND ? <> "") LIMIT 1');
        $check->execute([$email, $email, $phone, $phone]);
        if ($check->fetch()) {
            flash('danger', 'Email or phone already exists.');
        } else {
            $stmt = db()->prepare('INSERT INTO users (full_name, email, phone, password_hash, is_verified) VALUES (?, ?, ?, ?, 0)');
            $stmt->execute([$fullName, $email ?: null, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)db()->lastInsertId();

            $otp = create_otp_code('user', $userId, 'email_verify', 10);
            send_otp_notice($email ?: null, $otp, 'email verification');

            $_SESSION['otp_pending'] = [
                'entity_type' => 'user',
                'entity_id' => $userId,
                'purpose' => 'email_verify',
                'return_to' => BASE_URL . '/homepage.php',
            ];

            flash('success', 'Registration successful. Please verify OTP to continue.');
            header('Location: ' . BASE_URL . '/verify-otp.php');
            exit;
        }
    }
}

include __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="detail-box p-4">
            <h1 class="h3 mb-3">Create Account</h1>
            <form method="post">
                <div class="mb-3"><label class="form-label">Full Name</label><input name="full_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Phone (optional)</label><input name="phone" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Confirm Password</label><input name="confirm_password" type="password" class="form-control" required></div>
                <button class="btn btn-dark w-100">Register</button>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>

