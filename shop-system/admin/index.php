<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$stats = [
    'admins' => (int)db()->query('SELECT COUNT(*) FROM admin')->fetchColumn(),
    'employees' => (int)db()->query('SELECT COUNT(*) FROM employees')->fetchColumn(),
    'users' => (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
];

include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Admin Dashboard</h1>
    <a href="<?= BASE_URL ?>/admin/logout.php" class="btn btn-outline-dark btn-sm">Logout</a>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $label => $value): ?>
        <div class="col-sm-6 col-lg-4">
            <div class="detail-box p-3">
                <h6 class="text-uppercase text-muted"><?= e($label) ?></h6>
                <h3 class="mb-0"><?= $value ?></h3>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="d-flex flex-wrap gap-2">
    <a href="<?= BASE_URL ?>/admin/accounts.php" class="btn btn-dark">Manage User & Employee Accounts</a>
    <a href="<?= BASE_URL ?>/employee/index.php" class="btn btn-outline-dark">Open Employee System</a>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
