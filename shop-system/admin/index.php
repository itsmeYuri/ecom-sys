<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$stats = [
    'products' => (int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'orders' => (int)db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'reviews' => (int)db()->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
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
        <div class="col-sm-6 col-lg-3">
            <div class="detail-box p-3">
                <h6 class="text-uppercase text-muted"><?= e($label) ?></h6>
                <h3 class="mb-0"><?= $value ?></h3>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="d-flex flex-wrap gap-2" id="adminPageButtons">
    <a href="<?= BASE_URL ?>/admin/products.php" class="btn btn-dark admin-page-btn" data-title="Manage Products" data-desc="Add, edit, and delete products in your listing.">Manage Products</a>
    <a href="<?= BASE_URL ?>/admin/categories.php" class="btn btn-outline-dark admin-page-btn" data-title="Manage Categories" data-desc="Create and organize product categories.">Manage Categories</a>
    <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline-dark admin-page-btn" data-title="View Orders" data-desc="Review customer orders and update status.">View Orders</a>
    <a href="<?= BASE_URL ?>/admin/reviews.php" class="btn btn-outline-dark admin-page-btn" data-title="Manage Reviews" data-desc="Moderate customer reviews and ratings.">Manage Reviews</a>
</div>

<div id="inlinePageWrap" class="detail-box mt-3 d-none" style="overflow: hidden;">
    <iframe
        id="inlinePageFrame"
        title="Admin Inline Page"
        src="about:blank"
        style="width: 100%; min-height: 760px; border: 0; display: block; background: #fff;">
    </iframe>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btns = document.querySelectorAll('.admin-page-btn');
    const wrap = document.getElementById('inlinePageWrap');
    const frame = document.getElementById('inlinePageFrame');

    btns.forEach((btn) => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const href = btn.getAttribute('href') || '#';
            const sep = href.includes('?') ? '&' : '?';
            const frameUrl = href + sep + 'embed=1';

            btns.forEach((b) => {
                b.classList.remove('btn-dark');
                b.classList.add('btn-outline-dark');
            });
            btn.classList.remove('btn-outline-dark');
            btn.classList.add('btn-dark');

            frame.src = frameUrl;
            wrap.classList.remove('d-none');
            wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });
});
</script>
<?php include __DIR__ . '/../footer.php'; ?>
