<?php
require_once __DIR__ . '/includes/functions.php';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $where[]  = 'p.name LIKE ?';
    $params[] = '%' . $q . '%';
}

$category = (int)($_GET['category'] ?? 0);
if ($category > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $category;
}

$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
if ($minPrice !== null && $minPrice >= 0) { $where[] = 'p.price >= ?'; $params[] = $minPrice; }
if ($maxPrice !== null && $maxPrice > 0)  { $where[] = 'p.price <= ?'; $params[] = $maxPrice; }

$color = trim($_GET['color'] ?? '');
if ($color !== '') {
    $where[]  = 'FIND_IN_SET(?, REPLACE(p.colors, " ", ""))';
    $params[] = str_replace(' ', '', $color);
}

$size = trim($_GET['size'] ?? '');
if ($size !== '') {
    $where[]  = 'FIND_IN_SET(?, REPLACE(p.sizes, " ", ""))';
    $params[] = str_replace(' ', '', $size);
}

$sale = !empty($_GET['sale']);
if ($sale) {
    $where[] = 'p.is_sale = 1';
}

$sort    = $_GET['sort'] ?? 'popular';
$orderBy = match($sort) {
    'low'  => 'p.price ASC',
    'high' => 'p.price DESC',
    'new'  => 'p.id DESC',
    default => 'p.is_popular DESC, p.id DESC',
};

$pageTitle = match(true) {
    $sale        => 'Sale',
    $sort === 'new' => 'New In',
    default      => 'All Products',
};

$whereSql  = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = db()->prepare("SELECT COUNT(*) FROM products p $whereSql");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "SELECT p.*,
        c.name AS category_name,
        (SELECT pi.id FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_main DESC, id ASC LIMIT 1) AS image_id
        FROM products p
        JOIN categories c ON c.id = p.category_id
        $whereSql
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products   = $stmt->fetchAll();
$categories = get_categories();

include __DIR__ . '/header.php';
?>
<div class="small text-muted mb-2">Home &gt; <?= e($pageTitle) ?></div>
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
                        <div class="col"><input class="form-control form-control-sm" type="number" name="min_price" placeholder="₱0" value="<?= e((string)($_GET['min_price'] ?? '')) ?>"></div>
                        <div class="col"><input class="form-control form-control-sm" type="number" name="max_price" placeholder="₱500" value="<?= e((string)($_GET['max_price'] ?? '')) ?>"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Sort By</label>
                    <select name="sort" class="form-select form-select-sm">
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        <option value="new"     <?= $sort === 'new'     ? 'selected' : '' ?>>Newest First</option>
                        <option value="low"     <?= $sort === 'low'     ? 'selected' : '' ?>>Lowest Price</option>
                        <option value="high"    <?= $sort === 'high'    ? 'selected' : '' ?>>Highest Price</option>
                    </select>
                </div>
                <?php if ($sale): ?><input type="hidden" name="sale" value="1"><?php endif; ?>
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <button class="btn btn-dark w-100">Apply Filter</button>
            </form>
        </div>
    </aside>

    <section class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 m-0 fw-bold"><?= e($pageTitle) ?></h1>
            <small class="text-muted">Showing <?= count($products) ?> of <?= $total ?> products</small>
        </div>
        <?php if ($sort === 'new' || $sale): ?>
        <form method="get" class="d-flex gap-2 mb-3">
            <?php if ($sale): ?><input type="hidden" name="sale" value="1"><?php endif; ?>
            <?php if ($sort === 'new'): ?><input type="hidden" name="sort" value="new"><?php endif; ?>
            <input type="search" name="q" class="form-control"
                   placeholder="Search <?= e($pageTitle) ?>..."
                   value="<?= e($q) ?>">
            <button class="btn btn-dark px-4">Search</button>
            <?php if ($q !== ''): ?>
            <a href="?<?= $sale ? 'sale=1' : 'sort=new' ?>" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
        <?php endif; ?>
        <div class="row g-3">
            <?php foreach ($products as $product): ?>
                <div class="col-6 col-md-4">
                    <a class="text-decoration-none text-dark" href="<?= BASE_URL ?>/product.php?id=<?= (int)$product['id'] ?>">
                        <article class="product-card">
                            <div class="position-relative">
                                <img src="<?= e(image_url(isset($product['image_id']) ? (int)$product['image_id'] : 0, fallback_image_url())) ?>" alt="<?= e($product['name']) ?>">
                                <?php if (!empty($product['is_sold'])): ?>
                                    <span class="badge text-bg-danger position-absolute top-0 start-0 m-2">SOLD</span>
                                <?php elseif (!empty($product['is_sale'])): ?>
                                    <span class="badge text-bg-warning text-dark position-absolute top-0 start-0 m-2">SALE</span>
                                <?php endif; ?>
                            </div>
                            <h6 class="mt-2 mb-1"><?= e($product['name']) ?></h6>
                            <div>
                                <strong>₱<?= number_format((float)$product['price'], 0) ?></strong>
                                <?php if (!empty($product['old_price'])): ?>
                                    <span class="old-price ms-1">₱<?= number_format((float)$product['old_price'], 0) ?></span>
                                <?php endif; ?>
                            </div>
                        </article>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (!$products): ?>
                <div class="col-12"><p class="text-muted text-center py-5">No products found.</p></div>
            <?php endif; ?>
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
