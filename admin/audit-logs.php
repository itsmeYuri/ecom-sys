<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$filterAction = trim($_GET['action'] ?? '');
$filterType   = trim($_GET['type']   ?? '');
$filterUser   = trim($_GET['user']   ?? '');

$where  = [];
$params = [];
if ($filterAction !== '') { $where[] = 'action LIKE ?'; $params[] = '%' . $filterAction . '%'; }
if ($filterType   !== '') { $where[] = 'entity_type = ?'; $params[] = $filterType; }
if ($filterUser   !== '') { $where[] = 'username LIKE ?'; $params[] = '%'.$filterUser.'%'; }

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)db()->prepare("SELECT COUNT(*) FROM audit_logs {$whereStr}")->execute($params) ?
         db()->prepare("SELECT COUNT(*) FROM audit_logs {$whereStr}")->execute($params) : 0;

$countStmt = db()->prepare("SELECT COUNT(*) FROM audit_logs {$whereStr}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$logStmt = db()->prepare("SELECT * FROM audit_logs {$whereStr} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
$logStmt->execute($params);
$logs = $logStmt->fetchAll();

$actionBadge = [
    'LOGIN'           => 'success',
    'LOGOUT'          => 'secondary',
    'REGISTER'        => 'primary',
    'ACCOUNT_LOCKED'  => 'danger',
    'ACCOUNT_UNLOCKED'=> 'warning',
    'SESSION_TIMEOUT' => 'info',
    'ORDER_PLACED'    => 'primary',
    'SETTINGS_UPDATE' => 'warning',
    'ACCOUNT_ACTIVATED'=> 'success',
];

include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Audit Logs</h1>
        <p class="text-muted small mb-0">All user actions, logins, and security events</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-dark btn-sm">&#8592; Dashboard</a>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
</div>

<form method="get" class="detail-box p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4">
            <label class="form-label small">Action</label>
            <input name="action" class="form-control form-control-sm" placeholder="e.g. LOGIN" value="<?= e($filterAction) ?>">
        </div>
        <div class="col-sm-3">
            <label class="form-label small">User Type</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach (['user','admin','employee','guest'] as $t): ?>
                <option value="<?= $t ?>" <?= $filterType===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label small">Username</label>
            <input name="user" class="form-control form-control-sm" placeholder="username or email" value="<?= e($filterUser) ?>">
        </div>
        <div class="col-sm-2">
            <button class="btn btn-dark btn-sm w-100">Filter</button>
        </div>
    </div>
</form>

<div class="detail-box p-3">
    <div class="d-flex justify-content-between mb-2">
        <span class="small text-muted"><?= number_format($total) ?> records</span>
        <span class="small text-muted">Page <?= $page ?> / <?= $pages ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm small align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th><th>Time</th><th>Type</th><th>Username</th>
                    <th>Action</th><th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-muted"><?= (int)$log['id'] ?></td>
                        <td class="text-nowrap"><?= e($log['logged_at']) ?></td>
                        <td><span class="badge text-bg-secondary"><?= e($log['entity_type']) ?></span></td>
                        <td><?= e($log['username'] ?? '—') ?></td>
                        <td>
                            <?php $cls = $actionBadge[$log['action']] ?? 'dark'; ?>
                            <span class="badge text-bg-<?= $cls ?>"><?= e($log['action']) ?></span>
                        </td>
                        <td class="text-muted" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="<?= e($log['detail'] ?? '') ?>">
                            <?= e(substr($log['detail'] ?? '—', 0, 100)) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm mb-0 justify-content-center">
            <?php for ($i = 1; $i <= min($pages, 20); $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&action=<?= urlencode($filterAction) ?>&type=<?= urlencode($filterType) ?>&user=<?= urlencode($filterUser) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
