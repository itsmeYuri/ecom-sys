<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_employee') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($fullName === '' || $username === '' || $email === '' || $password === '') {
            flash('danger', 'All employee fields are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Invalid employee email.');
        } elseif (strlen($password) < 6) {
            flash('danger', 'Password must be at least 6 characters.');
        } else {
            try {
                $stmt = db()->prepare('INSERT INTO employees (full_name, username, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)');
                $stmt->execute([$fullName, $username, $email, password_hash($password, PASSWORD_DEFAULT)]);
                flash('success', 'Employee account created.');
            } catch (Throwable $e) {
                flash('danger', 'Username or email already exists.');
            }
        }
    }

    if ($action === 'toggle_employee') {
        $id = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        if ($id > 0) {
            db()->prepare('UPDATE employees SET is_active = ? WHERE id = ?')->execute([$active, $id]);
            flash('success', $active ? 'Employee activated.' : 'Employee deactivated.');
        }
    }

    if ($action === 'delete_employee') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('DELETE FROM employees WHERE id = ?')->execute([$id]);
            flash('success', 'Employee deleted.');
        }
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'User deleted.');
        }
    }

    header('Location: ' . BASE_URL . '/admin/accounts.php');
    exit;
}

$users = db()->query('SELECT id, full_name, email, phone, is_verified, created_at FROM users ORDER BY id DESC')->fetchAll();
$employees = db()->query('SELECT id, full_name, username, email, is_active, created_at FROM employees ORDER BY id DESC')->fetchAll();

include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Account Management</h1>
    <a href="<?= BASE_URL ?>/admin/logout.php" class="btn btn-outline-dark btn-sm">Logout</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="detail-box p-3">
            <h5>Add Employee</h5>
            <form method="post">
                <input type="hidden" name="action" value="add_employee">
                <div class="mb-2"><input class="form-control" name="full_name" placeholder="Full Name" required></div>
                <div class="mb-2"><input class="form-control" name="username" placeholder="Username" required></div>
                <div class="mb-2"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
                <div class="mb-3"><input class="form-control" type="password" name="password" placeholder="Password" required></div>
                <button class="btn btn-dark w-100">Create Employee</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="detail-box p-3 mb-4">
            <h5>Employees</h5>
            <table class="table table-sm">
                <thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?= (int)$emp['id'] ?></td>
                            <td><?= e($emp['full_name']) ?></td>
                            <td><?= e($emp['username']) ?></td>
                            <td><?= e($emp['email']) ?></td>
                            <td><?= !empty($emp['is_active']) ? '<span class="badge text-bg-success">ACTIVE</span>' : '<span class="badge text-bg-secondary">INACTIVE</span>' ?></td>
                            <td class="text-end">
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_employee">
                                    <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= !empty($emp['is_active']) ? '0' : '1' ?>">
                                    <button class="btn btn-sm btn-outline-dark"><?= !empty($emp['is_active']) ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete employee account?')">
                                    <input type="hidden" name="action" value="delete_employee">
                                    <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="detail-box p-3">
            <h5>Users</h5>
            <table class="table table-sm">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Verified</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= (int)$u['id'] ?></td>
                            <td><?= e($u['full_name']) ?></td>
                            <td><?= e($u['email'] ?? '-') ?></td>
                            <td><?= e($u['phone'] ?? '-') ?></td>
                            <td><?= !empty($u['is_verified']) ? '<span class="badge text-bg-success">YES</span>' : '<span class="badge text-bg-warning">NO</span>' ?></td>
                            <td class="text-end">
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete user account?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
