<?php
require_once __DIR__ . '/../includes/functions.php';
require_employee();

$stats = [
    'products' => (int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'orders' => (int)db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'reviews' => (int)db()->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
    'categories' => (int)db()->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
];

include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Employee Dashboard</h1>
    <a href="<?= BASE_URL ?>/employee/logout.php" class="btn btn-outline-dark btn-sm">Logout</a>
</div>
<div class="row g-3 mb-4">
    <?php foreach ($stats as $label => $value): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="detail-box p-3">
                <h6 class="text-uppercase text-muted"><?= e($label) ?></h6>
                <h3 class="mb-0"><?= $value ?></h3>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="d-flex flex-wrap gap-2">
    <a href="<?= BASE_URL ?>/employee/products.php" class="btn btn-dark">Manage Products</a>
    <a href="<?= BASE_URL ?>/employee/categories.php" class="btn btn-outline-dark">Manage Categories</a>
    <a href="<?= BASE_URL ?>/employee/orders.php" class="btn btn-outline-dark">View Orders</a>
    <a href="<?= BASE_URL ?>/employee/reviews.php" class="btn btn-outline-dark">Manage Reviews</a>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
