<?php
require_once __DIR__ . '/includes/functions.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $where[] = 'p.name LIKE ?';
    $params[] = '%' . $q . '%';
}

$category = (int)($_GET['category'] ?? 0);
if ($category > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $category;
}

$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
if ($minPrice !== null && $minPrice >= 0) {
    $where[] = 'p.price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null && $maxPrice > 0) {
    $where[] = 'p.price <= ?';
    $params[] = $maxPrice;
}

$color = trim($_GET['color'] ?? '');
if ($color !== '') {
    $where[] = 'FIND_IN_SET(?, REPLACE(p.colors, " ", ""))';
    $params[] = str_replace(' ', '', $color);
}

$size = trim($_GET['size'] ?? '');
if ($size !== '') {
    $where[] = 'FIND_IN_SET(?, REPLACE(p.sizes, " ", ""))';
    $params[] = str_replace(' ', '', $size);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sort = $_GET['sort'] ?? 'popular';
$orderBy = 'p.is_popular DESC, p.id DESC';
if ($sort === 'low') {
    $orderBy = 'p.price ASC';
} elseif ($sort === 'high') {
    $orderBy = 'p.price DESC';
}

$countStmt = db()->prepare("SELECT COUNT(*) FROM products p $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "SELECT p.*, c.name AS category_name,
        COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.product_id = p.id), 0) AS rating,
        (SELECT pi.id FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_main DESC, id ASC LIMIT 1) AS image_id
        FROM products p
        JOIN categories c ON c.id = p.category_id
        $whereSql
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = get_categories();

include __DIR__ . '/header.php';
?>
<div class="small text-muted mb-2">Home &gt; Casual</div>
<div class="row g-4">
    <aside class="col-lg-3">
        <div class="filter-card p-3">
            <h5 class="filter-title mb-3">Filters</h5>
            <form method="get" action="">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $category === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Price</label>
                    <div class="row g-2">
                        <div class="col"><input class="form-control form-control-sm" type="number" name="min_price" placeholder="₱50" value="<?= e((string)($_GET['min_price'] ?? '')) ?>"></div>
                        <div class="col"><input class="form-control form-control-sm" type="number" name="max_price" placeholder="₱200" value="<?= e((string)($_GET['max_price'] ?? '')) ?>"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Colors</label><br>
                    <?php foreach (['#2a9d4b','#d90f0f','#f4b400','#f97316','#16a1d9','#4934ff','#c823d9','#ff7bc8','#3b0909'] as $c): ?>
                        <span class="color-dot" style="background: <?= e($c) ?>"></span>
                    <?php endforeach; ?>
                    <input class="form-control form-control-sm mt-2" name="color" placeholder="Color name" value="<?= e($color) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Size</label><br>
                    <?php foreach (['XX-Small','X-Small','Small','Medium','Large','X-Large','XX-Large'] as $s): ?>
                        <span class="sidebar-chip <?= strtolower($size) === strtolower($s) ? 'active' : '' ?>"><?= e($s) ?></span>
                    <?php endforeach; ?>
                    <input class="form-control form-control-sm mt-2" name="size" placeholder="Size" value="<?= e($size) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Sort By</label>
                    <select name="sort" class="form-select form-select-sm">
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        <option value="low" <?= $sort === 'low' ? 'selected' : '' ?>>Lowest Price</option>
                        <option value="high" <?= $sort === 'high' ? 'selected' : '' ?>>Highest Price</option>
                    </select>
                </div>
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <button class="btn btn-dark w-100">Apply Filter</button>
            </form>
        </div>
    </aside>

    <section class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 m-0 fw-bold">Casual</h1>
            <small class="text-muted">Showing <?= count($products) ?> of <?= $total ?> products</small>
        </div>
        <div class="row g-3">
            <?php foreach ($products as $product): ?>
                <div class="col-6 col-md-4">
                    <a class="text-decoration-none text-dark" href="<?= BASE_URL ?>/product.php?id=<?= (int)$product['id'] ?>">
                        <article class="product-card">
                            <img src="<?= e(image_url(isset($product['image_id']) ? (int)$product['image_id'] : 0, fallback_image_url())) ?>" alt="<?= e($product['name']) ?>">
                            <h6><?= e($product['name']) ?> <?php if (!empty($product['is_sold'])): ?><span class="badge text-bg-danger">SOLD</span><?php endif; ?></h6>
                            <div class="rating"><?= e(render_stars((float)$product['rating'])) ?> <?= e((string)$product['rating']) ?></div>
                            <div><strong>₱<?= number_format((float)$product['price'], 0) ?></strong>
                                <?php if (!empty($product['old_price'])): ?>
                                    <span class="old-price">₱<?= number_format((float)$product['old_price'], 0) ?></span>
                                <?php endif; ?>
                            </div>
                        </article>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <nav class="mt-4 d-flex justify-content-center">
            <ul class="pagination pagination-sm">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </section>
</div>
<?php include __DIR__ . '/footer.php'; ?>

