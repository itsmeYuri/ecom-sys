<?php
require_once __DIR__ . '/includes/functions.php';

$productId = (int)($_GET['id'] ?? 0);
if ($productId < 1) {
    header('Location: ' . BASE_URL . '/shop.php');
    exit;
}

if (is_logged_in() && (int)($_GET['add'] ?? 0) === 1) {
    $qtyFromGet = max(1, (int)($_GET['qty'] ?? 1));
    $soldCheck = db()->prepare('SELECT is_sold FROM products WHERE id = ? LIMIT 1');
    $soldCheck->execute([$productId]);
    $soldRow = $soldCheck->fetch();
    if (!empty($soldRow['is_sold'])) {
        flash('warning', 'This item is marked as sold and cannot be added to cart.');
        header('Location: ' . BASE_URL . '/product.php?id=' . $productId);
        exit;
    }

    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $qtyFromGet;
    flash('success', 'Product added to cart.');
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    if (!is_logged_in()) {
        flash('warning', 'Please login first to add items to your cart.');
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $returnTo = urlencode('/product.php?id=' . $productId . '&add=1&qty=' . $qty);
        header('Location: ' . BASE_URL . '/login.php?return_to=' . $returnTo);
        exit;
    }

    $soldCheck = db()->prepare('SELECT is_sold FROM products WHERE id = ? LIMIT 1');
    $soldCheck->execute([$productId]);
    $soldRow = $soldCheck->fetch();
    if (!empty($soldRow['is_sold'])) {
        flash('warning', 'This item is marked as sold and cannot be added to cart.');
        header('Location: ' . BASE_URL . '/product.php?id=' . $productId);
        exit;
    }

    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $qty;
    flash('success', 'Product added to cart.');
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

$stmt = db()->prepare('SELECT p.*, c.name AS category_name, COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.product_id = p.id), 0) AS rating FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    header('Location: ' . BASE_URL . '/shop.php');
    exit;
}

$imgStmt = db()->prepare('SELECT id AS image_id, is_main FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC');
$imgStmt->execute([$productId]);
$images = $imgStmt->fetchAll();

$revStmt = db()->prepare('SELECT reviewer_name, rating, comment, created_at FROM reviews WHERE product_id = ? ORDER BY id DESC');
$revStmt->execute([$productId]);
$reviews = $revStmt->fetchAll();

$relStmt = db()->prepare("SELECT p.*, COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.product_id = p.id), 0) AS rating, (SELECT pi.id FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_main DESC, id ASC LIMIT 1) AS image_id FROM products p WHERE p.category_id = ? AND p.id <> ? ORDER BY p.is_popular DESC LIMIT 4");
$relStmt->execute([$product['category_id'], $productId]);
$related = $relStmt->fetchAll();

$colors = array_filter(array_map('trim', explode(',', $product['colors'])));
$sizes = array_filter(array_map('trim', explode(',', $product['sizes'])));

include __DIR__ . '/header.php';
?>
<div class="product-page">
    <div class="small text-muted mb-3">Home &gt; Shop &gt; Men &gt; T-shirts</div>

    <div class="product-top">
        <div class="product-gallery">
            <div class="gallery-thumbs d-flex flex-column gap-2">
                <?php foreach ($images as $img): ?>
                    <img src="<?= e(image_url((int)$img['image_id'], fallback_image_url())) ?>" alt="thumbnail">
                <?php endforeach; ?>
            </div>
            <div class="product-main-image">
                <img class="gallery-main" src="<?= e(image_url(isset($images[0]['image_id']) ? (int)$images[0]['image_id'] : 0, fallback_image_url())) ?>" alt="<?= e($product['name']) ?>">
            </div>
        </div>

        <div class="product-summary">
            <h1 class="product-name"><?= e($product['name']) ?>
                <?php if (!empty($product['is_sold'])): ?>
                    <span class="badge text-bg-danger align-middle ms-1">SOLD</span>
                <?php endif; ?>
            </h1>
            <div class="product-rating-line">
                <span class="rating"><?= e(render_stars((float)$product['rating'])) ?></span>
                <span class="rating-score"><?= e((string)$product['rating']) ?>/5</span>
            </div>

            <div class="product-price-line">
                <strong class="current-price">₱<?= number_format((float)$product['price'], 0) ?></strong>
                <?php if (!empty($product['old_price'])): ?>
                    <span class="old-price">₱<?= number_format((float)$product['old_price'], 0) ?></span>
                    <?php $discountPct = (int)round((1 - ((float)$product['price'] / (float)$product['old_price'])) * 100); ?>
                    <?php if ($discountPct > 0): ?>
                        <span class="discount-pill">-<?= $discountPct ?>%</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <p class="product-desc"><?= e($product['description']) ?></p>
            <div class="product-divider"></div>

            <div class="small text-muted mb-2">Select Colors</div>
            <div class="d-flex gap-2 mb-3">
                <?php foreach ($colors as $i => $color): ?>
                    <span class="color-swatch <?= $i === 0 ? 'active' : '' ?>" style="background: <?= e($color) ?>;" title="<?= e($color) ?>"></span>
                <?php endforeach; ?>
            </div>

            <div class="product-divider"></div>
            <div class="small text-muted mb-2">Choose Size</div>
            <div class="mb-3">
                <?php foreach ($sizes as $i => $size): ?>
                    <span class="size-pill <?= strtolower($size) === 'large' || $i === 0 ? 'active' : '' ?>"><?= e($size) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="product-divider"></div>
            <?php if (!empty($product['is_sold'])): ?>
                <button class="btn btn-secondary px-5" disabled>Sold Out</button>
            <?php else: ?>
                <form method="post" class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="hidden" name="action" value="add_to_cart">
                    <div class="qty-compact">
                        <button type="button">-</button>
                        <input type="number" min="1" name="quantity" value="1">
                        <button type="button">+</button>
                    </div>
                    <button class="btn btn-dark product-add-btn">Add to Cart</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="product-tabs mt-4">
        <button type="button">Product Details</button>
        <button type="button" class="active">Rating & Reviews</button>
        <button type="button">FAQs</button>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap gap-2">
        <h4 class="m-0">All Reviews <span class="text-muted small">(<?= count($reviews) ?>)</span></h4>
        <div class="d-flex align-items-center gap-2">
            <button class="review-filter-btn" type="button">Latest</button>
            <button class="btn btn-dark btn-sm">Write a Review</button>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($reviews as $review): ?>
            <div class="col-md-6">
                <div class="review-card">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="rating"><?= e(render_stars((float)$review['rating'])) ?></div>
                        <span class="text-muted small">•••</span>
                    </div>
                    <strong><?= e($review['reviewer_name']) ?></strong>
                    <p class="small text-muted mb-1"><?= e($review['comment']) ?></p>
                    <small class="text-muted">Posted on <?= e(date('F d, Y', strtotime($review['created_at']))) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-3">
        <button class="load-more-btn" type="button">Load More Reviews</button>
    </div>
</div>

<section class="mt-5">
    <h4 class="section-title product-related-title">You might also like</h4>
    <div class="row g-3">
        <?php foreach ($related as $rp): ?>
            <div class="col-6 col-md-3">
                <a class="text-decoration-none text-dark" href="<?= BASE_URL ?>/product.php?id=<?= (int)$rp['id'] ?>">
                    <article class="product-card product-card-related">
                        <img src="<?= e(image_url(isset($rp['image_id']) ? (int)$rp['image_id'] : 0, fallback_image_url())) ?>" alt="<?= e($rp['name']) ?>">
                        <h6><?= e($rp['name']) ?></h6>
                        <div class="rating"><?= e(render_stars((float)$rp['rating'])) ?> <span class="text-muted"><?= e((string)$rp['rating']) ?>/5</span></div>
                        <div class="d-flex align-items-center gap-2">
                            <strong>₱<?= number_format((float)$rp['price'], 0) ?></strong>
                            <?php if (!empty($rp['old_price'])): ?>
                                <span class="old-price">₱<?= number_format((float)$rp['old_price'], 0) ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
