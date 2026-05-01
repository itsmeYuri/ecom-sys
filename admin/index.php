<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$stats = [
    'users'     => (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'employees' => (int)db()->query('SELECT COUNT(*) FROM employees')->fetchColumn(),
    'orders'    => (int)db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'revenue'   => (float)db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status NOT IN ('Cancelled')")->fetchColumn(),
    'pending'   => (int)db()->query("SELECT COUNT(*) FROM orders WHERE status='Pending'")->fetchColumn(),
    'locked'    => (int)db()->query("SELECT (SELECT COUNT(*) FROM users WHERE is_locked=1) + (SELECT COUNT(*) FROM employees WHERE is_locked=1)")->fetchColumn(),
];

$recentOrders = db()->query("SELECT id, customer_name, total, status, created_at FROM orders ORDER BY id DESC LIMIT 5")->fetchAll();
$recentLogs   = db()->query("SELECT action, username, ip_address, logged_at FROM audit_logs ORDER BY id DESC LIMIT 5")->fetchAll();

include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Admin Dashboard</h1>
        <p class="text-muted small mb-0">Welcome back, <strong><?= e($_SESSION['admin']['username'] ?? '') ?></strong></p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['&#128100;','Users',     $stats['users'],    'text-dark', '#/admin/accounts.php'],
        ['&#128188;','Employees', $stats['employees'],'text-dark', '#/admin/accounts.php'],
        ['&#128230;','Orders',    $stats['orders'],   'text-dark', BASE_URL.'/admin/orders.php'],
        ['&#8369;',  'Revenue',   '&#8369;'.number_format($stats['revenue'],0), 'text-dark', BASE_URL.'/admin/orders.php'],
        ['&#9203;',  'Pending',   $stats['pending'],  'text-warning', BASE_URL.'/admin/orders.php?status=Pending'],
        ['&#128274;','Locked',    $stats['locked'],   $stats['locked']>0?'text-danger':'text-success', BASE_URL.'/admin/locked-accounts.php'],
    ];
    foreach ($cards as [$icon,$label,$val,$cls,$href]): ?>
    <div class="col-6 col-sm-4 col-lg-2">
        <a href="<?= $href ?>" class="text-decoration-none">
            <div class="detail-box p-3 text-center h-100">
                <div style="font-size:1.6rem;"><?= $icon ?></div>
                <h6 class="text-muted small text-uppercase mb-1 mt-1"><?= $label ?></h6>
                <h3 class="mb-0 fw-bold <?= $cls ?>"><?= is_numeric($val) ? $val : $val ?></h3>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="detail-box p-3 mb-4">
    <h6 class="fw-bold mb-3">Quick Actions</h6>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= BASE_URL ?>/admin/accounts.php"        class="btn btn-dark btn-sm">&#128100; Accounts</a>
        <a href="<?= BASE_URL ?>/admin/orders.php"          class="btn btn-outline-dark btn-sm">&#128230; Orders</a>
        <a href="<?= BASE_URL ?>/admin/locked-accounts.php" class="btn btn-outline-<?= $stats['locked']>0?'danger':'dark' ?> btn-sm">&#128274; Locked <?= $stats['locked']>0?"({$stats['locked']})":'' ?></a>
        <a href="<?= BASE_URL ?>/admin/audit-logs.php"      class="btn btn-outline-dark btn-sm">&#128196; Audit Logs</a>
        <a href="<?= BASE_URL ?>/admin/settings.php"        class="btn btn-outline-dark btn-sm">&#9881;&#65039; Settings</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="detail-box p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Recent Orders</h6>
                <a href="<?= BASE_URL ?>/admin/orders.php" class="small text-muted">View all &rarr;</a>
            </div>
            <table class="table table-sm small">
                <thead class="table-light"><tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                    <tr>
                        <td>#<?= (int)$o['id'] ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td>&#8369;<?= number_format((float)$o['total'],0) ?></td>
                        <td><?php $cls=['Pending'=>'warning','Paid'=>'success','Shipped'=>'info','Completed'=>'dark','Cancelled'=>'danger'];
                        echo '<span class="badge text-bg-'.($cls[$o['status']]??'secondary').'">'.e($o['status']).'</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentOrders): ?><tr><td colspan="4" class="text-muted">No orders yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="detail-box p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Recent Activity</h6>
                <a href="<?= BASE_URL ?>/admin/audit-logs.php" class="small text-muted">View all &rarr;</a>
            </div>
            <table class="table table-sm small">
                <thead class="table-light"><tr><th>Action</th><th>User</th><th>IP</th><th>Time</th></tr></thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td><span class="badge text-bg-secondary"><?= e($log['action']) ?></span></td>
                        <td><?= e($log['username'] ?? '—') ?></td>
                        <td class="text-muted small"><?= e($log['ip_address'] ?? '—') ?></td>
                        <td class="text-muted small"><?= e(date('H:i', strtotime($log['logged_at']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentLogs): ?><tr><td colspan="4" class="text-muted">No activity yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
