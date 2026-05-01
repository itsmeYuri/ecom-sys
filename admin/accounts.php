<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_employee') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username']  ?? '');
        $email    = trim($_POST['email']     ?? '');
        $password = $_POST['password']       ?? '';
        $errors   = [];
        if (!$fullName||!$username||!$email||!$password) $errors[] = 'All fields are required.';
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
        $pwdErr = validate_password($password);
        if ($pwdErr) $errors[] = $pwdErr;
        if ($errors) { foreach ($errors as $e) flash('danger', $e); }
        else {
            try {
                db()->prepare('INSERT INTO employees (full_name, username, email, password_hash, is_active) VALUES (?,?,?,?,1)')
                    ->execute([$fullName,$username,$email,password_hash($password,PASSWORD_BCRYPT,['cost'=>12])]);
                audit_log('admin',(int)$_SESSION['admin']['id'],$_SESSION['admin']['username'],'EMPLOYEE_CREATED',"Created: {$username}");
                flash('success','Employee account created.');
            } catch (Throwable) { flash('danger','Username or email already exists.'); }
        }
    }

    if ($action === 'toggle_employee') {
        $id = (int)($_POST['id']??0); $active = (int)($_POST['is_active']??0)===1?1:0;
        if ($id > 0) { db()->prepare('UPDATE employees SET is_active=? WHERE id=?')->execute([$active,$id]); flash('success',$active?'Employee activated.':'Employee deactivated.'); }
    }

    if ($action === 'delete_employee') {
        $id = (int)($_POST['id']??0);
        if ($id > 0) { db()->prepare('DELETE FROM employees WHERE id=?')->execute([$id]); audit_log('admin',(int)$_SESSION['admin']['id'],$_SESSION['admin']['username'],'EMPLOYEE_DELETED',"#{$id}"); flash('success','Employee deleted.'); }
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id']??0);
        if ($id > 0) { db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]); audit_log('admin',(int)$_SESSION['admin']['id'],$_SESSION['admin']['username'],'USER_DELETED',"#{$id}"); flash('success','User deleted.'); }
    }

    if ($action === 'unlock_user') {
        $id = (int)($_POST['id']??0); $type = $_POST['entity_type']??'user';
        if ($id > 0 && in_array($type,['user','employee'],true)) {
            unlock_entity_account($type,$id);
            // Clear failed attempts for the entity
            $table = ($type==='employee')? 'employees':'users';
            $iCol  = ($type==='employee')? 'username':'email';
            $row   = db()->prepare("SELECT {$iCol} AS ident FROM {$table} WHERE id=? LIMIT 1"); $row->execute([$id]);
            if ($ident = ($row->fetch())['ident']??null) {
                db()->prepare("DELETE FROM login_attempts WHERE entity_type=? AND identifier=? AND success=0")->execute([$type,$ident]);
            }
            audit_log('admin',(int)$_SESSION['admin']['id'],$_SESSION['admin']['username'],'ACCOUNT_UNLOCKED',"{$type} #{$id}");
            flash('success',ucfirst($type).' account unlocked.');
        }
    }

    header('Location: ' . BASE_URL . '/admin/accounts.php'); exit;
}

$users     = db()->query('SELECT id, full_name, email, phone, is_verified, is_locked, locked_at, created_at FROM users ORDER BY id DESC')->fetchAll();
$employees = db()->query('SELECT id, full_name, username, email, is_active, is_locked, locked_at, created_at FROM employees ORDER BY id DESC')->fetchAll();
$minLen    = (int)get_setting('password_min_length','8');

include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Account Management</h1>
        <p class="text-muted small mb-0">Manage customers and employee accounts</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-dark btn-sm">&#8592; Dashboard</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="detail-box p-4">
            <h5 class="fw-bold mb-3">Add Employee</h5>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_employee">
                <div class="mb-2"><input class="form-control" name="full_name" placeholder="Full Name" required></div>
                <div class="mb-2"><input class="form-control" name="username"  placeholder="Username"  required></div>
                <div class="mb-2"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
                <div class="mb-3">
                    <input class="form-control" type="password" name="password" placeholder="Password (min <?= $minLen ?> chars)" required>
                    <div class="form-text">Must meet current password policy.</div>
                </div>
                <button class="btn btn-dark w-100">Create Employee</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="detail-box p-3 mb-4">
            <h5 class="fw-bold mb-3">Employees (<?= count($employees) ?>)</h5>
            <div class="table-responsive">
                <table class="table table-sm small align-middle">
                    <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><?= (int)$emp['id'] ?></td>
                        <td><?= e($emp['full_name']) ?></td>
                        <td><?= e($emp['username']) ?></td>
                        <td><?= e($emp['email']??'—') ?></td>
                        <td>
                            <?php if (!empty($emp['is_locked'])): ?>
                                <span class="badge text-bg-danger">LOCKED</span>
                            <?php elseif (!empty($emp['is_active'])): ?>
                                <span class="badge text-bg-success">ACTIVE</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">INACTIVE</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end" style="white-space:nowrap;">
                            <?php if (!empty($emp['is_locked'])): ?>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unlock_user">
                                <input type="hidden" name="entity_type" value="employee">
                                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                <button class="btn btn-sm btn-warning">&#128275; Unlock</button>
                            </form>
                            <?php else: ?>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_employee">
                                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= !empty($emp['is_active'])?'0':'1' ?>">
                                <button class="btn btn-sm btn-outline-dark"><?= !empty($emp['is_active'])?'Deactivate':'Activate' ?></button>
                            </form>
                            <?php endif; ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this employee?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_employee">
                                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$employees): ?><tr><td colspan="6" class="text-muted py-2">No employees yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="detail-box p-3">
            <h5 class="fw-bold mb-3">Users (<?= count($users) ?>)</h5>
            <div class="table-responsive">
                <table class="table table-sm small align-middle">
                    <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Verified</th><th>Locked</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><?= e($u['full_name']) ?></td>
                        <td><?= e($u['email']??'—') ?></td>
                        <td><?= e($u['phone']??'—') ?></td>
                        <td><?= !empty($u['is_verified']) ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-warning text-dark">No</span>' ?></td>
                        <td><?= !empty($u['is_locked'])  ? '<span class="badge text-bg-danger">Yes</span>'  : '—' ?></td>
                        <td class="text-end" style="white-space:nowrap;">
                            <?php if (!empty($u['is_locked'])): ?>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unlock_user">
                                <input type="hidden" name="entity_type" value="user">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-warning">&#128275; Unlock</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?><tr><td colspan="7" class="text-muted py-2">No users yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
