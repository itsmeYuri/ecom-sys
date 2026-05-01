<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $entityType = $_POST['entity_type'] ?? '';
    $entityId   = (int)($_POST['entity_id'] ?? 0);
    $action     = $_POST['action'] ?? '';

    if ($action === 'unlock' && in_array($entityType, ['user','employee'], true) && $entityId > 0) {
        unlock_entity_account($entityType, $entityId);
        // Also clear recent failed attempts
        $table = ($entityType === 'employee') ? 'employees' : 'users';
        $identifierCol = ($entityType === 'employee') ? 'username' : 'email';
        $row = db()->prepare("SELECT {$identifierCol} AS identifier FROM {$table} WHERE id=? LIMIT 1");
        $row->execute([$entityId]);
        $ident = ($row->fetch())['identifier'] ?? '';
        if ($ident) {
            db()->prepare("DELETE FROM login_attempts WHERE entity_type=? AND identifier=? AND success=0")->execute([$entityType,$ident]);
        }
        audit_log('admin', (int)$_SESSION['admin']['id'], $_SESSION['admin']['username'], 'ACCOUNT_UNLOCKED', "Unlocked {$entityType} ID {$entityId}");
        flash('success', ucfirst($entityType) . ' account unlocked successfully.');
    }

    header('Location: ' . BASE_URL . '/admin/locked-accounts.php');
    exit;
}

// Fetch all locked accounts with attempt counts
$lockedUsers = db()->query("
    SELECT 'user' AS entity_type, u.id, u.full_name AS name, u.email AS identifier, u.locked_at, u.locked_reason,
           (SELECT COUNT(*) FROM login_attempts la WHERE la.entity_type='user' AND la.identifier=u.email AND la.success=0 AND la.attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS recent_attempts
    FROM users u WHERE u.is_locked=1 ORDER BY u.locked_at DESC
")->fetchAll();

$lockedEmps = db()->query("
    SELECT 'employee' AS entity_type, e.id, e.full_name AS name, e.username AS identifier, e.locked_at, e.locked_reason,
           (SELECT COUNT(*) FROM login_attempts la WHERE la.entity_type='employee' AND la.identifier=e.username AND la.success=0 AND la.attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS recent_attempts
    FROM employees e WHERE e.is_locked=1 ORDER BY e.locked_at DESC
")->fetchAll();

$recentAttempts = db()->query("
    SELECT entity_type, identifier, ip_address, attempted_at, success
    FROM login_attempts
    WHERE success=0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY attempted_at DESC LIMIT 50
")->fetchAll();

include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Locked Accounts</h1>
        <p class="text-muted small mb-0">View and unlock accounts locked due to failed login attempts</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-dark btn-sm">&#8592; Dashboard</a>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
</div>

<?php if (empty($lockedUsers) && empty($lockedEmps)): ?>
<div class="detail-box p-4 text-center text-muted">
    <div style="font-size:2.5rem;">&#9989;</div>
    <p class="mt-2 mb-0">No accounts are currently locked.</p>
</div>
<?php else: ?>

<?php foreach ([['Users',$lockedUsers],['Employees',$lockedEmps]] as [$label,$accounts]): ?>
<?php if ($accounts): ?>
<div class="detail-box p-3 mb-4">
    <h5 class="fw-bold mb-3">Locked <?= e($label) ?> (<?= count($accounts) ?>)</h5>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th><th>Name</th><th>Identifier</th>
                    <th>Locked At</th><th>Reason</th><th>Recent Attempts</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $acct): ?>
                <tr>
                    <td><?= (int)$acct['id'] ?></td>
                    <td><?= e($acct['name']) ?></td>
                    <td><code><?= e($acct['identifier']) ?></code></td>
                    <td class="small"><?= e($acct['locked_at'] ?? '—') ?></td>
                    <td class="small text-muted"><?= e($acct['locked_reason'] ?? '—') ?></td>
                    <td><span class="badge text-bg-danger"><?= (int)$acct['recent_attempts'] ?></span></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Unlock this account?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unlock">
                            <input type="hidden" name="entity_type" value="<?= e($acct['entity_type']) ?>">
                            <input type="hidden" name="entity_id" value="<?= (int)$acct['id'] ?>">
                            <button class="btn btn-sm btn-success">&#128275; Unlock</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>

<div class="detail-box p-3 mt-4">
    <h5 class="fw-bold mb-3">&#128270; Recent Failed Attempts (last 1 hour)</h5>
    <?php if ($recentAttempts): ?>
    <div class="table-responsive">
        <table class="table table-sm small">
            <thead class="table-light"><tr><th>Type</th><th>Identifier</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach ($recentAttempts as $a): ?>
                <tr>
                    <td><span class="badge text-bg-secondary"><?= e($a['entity_type']) ?></span></td>
                    <td><?= e($a['identifier']) ?></td>
                    <td><?= e($a['attempted_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted small mb-0">No failed attempts in the last hour.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
