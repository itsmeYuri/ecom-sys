<?php
require_once __DIR__ . '/includes/functions.php';

$productId = (int)($_GET['id'] ?? 0);
if ($productId < 1) {
    header('Location: ' . BASE_URL . '/shop.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
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

$imgStmt = db()->prepare('SELECT image_path, is_main FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC');
$imgStmt->execute([$productId]);
$images = $imgStmt->fetchAll();

$revStmt = db()->prepare('SELECT reviewer_name, rating, comment, created_at FROM reviews WHERE product_id = ? ORDER BY id DESC');
$revStmt->execute([$productId]);
$reviews = $revStmt->fetchAll();

$relStmt = db()->prepare("SELECT p.*, COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.product_id = p.id), 0) AS rating, (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_main DESC, id ASC LIMIT 1) AS image_path FROM products p WHERE p.category_id = ? AND p.id <> ? ORDER BY p.is_popular DESC LIMIT 4");
$relStmt->execute([$product['category_id'], $productId]);
$related = $relStmt->fetchAll();

$colors = array_filter(array_map('trim', explode(',', $product['colors'])));
$sizes = array_filter(array_map('trim', explode(',', $product['sizes'])));

include __DIR__ . '/header.php';
?>
<div class="small text-muted mb-2">Home &gt; Shop &gt; <?= e($product['name']) ?></div>
<div class="detail-box p-3 p-md-4">
    <div class="row g-3">
        <div class="col-lg-2 gallery-thumbs d-flex flex-lg-column gap-2">
            <?php foreach ($images as $img): ?>
                <img src="<?= e($img['image_path']) ?>" alt="thumbnail">
            <?php endforeach; ?>
        </div>
        <div class="col-lg-4">
            <img class="gallery-main" src="<?= e($images[0]['image_path'] ?? (BASE_URL . '/assets/images/model.png')) ?>" alt="<?= e($product['name']) ?>">
        </div>
        <div class="col-lg-6">
            <h1 class="h2 fw-bold mb-2"><?= e(strtoupper($product['name'])) ?></h1>
            <div class="rating mb-2"><?= e(render_stars((float)$product['rating'])) ?> <?= e((string)$product['rating']) ?>/5</div>
            <h3 class="mb-2 fw-bold">$<?= number_format((float)$product['price'], 0) ?>
                <?php if (!empty($product['old_price'])): ?>
                    <span class="old-price">$<?= number_format((float)$product['old_price'], 0) ?></span>
                <?php endif; ?>
            </h3>
            <p class="text-muted small"><?= e($product['description']) ?></p>

            <div class="mb-2"><span class="small text-muted">Select Colors</span><br>
                <?php foreach ($colors as $i => $color): ?>
                    <span class="size-pill <?= $i === 0 ? 'active' : '' ?>"><?= e($color) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="mb-3"><span class="small text-muted">Choose Size</span><br>
                <?php foreach ($sizes as $i => $size): ?>
                    <span class="size-pill <?= strtolower($size) === 'large' || $i === 0 ? 'active' : '' ?>"><?= e($size) ?></span>
                <?php endforeach; ?>
            </div>

            <form method="post" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="hidden" name="action" value="add_to_cart">
                <div class="qty-compact">
                    <button type="button">-</button>
                    <input type="number" min="1" name="quantity" value="1">
                    <button type="button">+</button>
                </div>
                <button class="btn btn-dark px-5">Add to Cart</button>
            </form>
        </div>
    </div>

    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">All Reviews <span class="text-muted small">(<?= count($reviews) ?>)</span></h4>
        <button class="btn btn-dark btn-sm">Write a Review</button>
    </div>
    <div class="row g-3">
        <?php foreach ($reviews as $review): ?>
            <div class="col-md-6">
                <div class="review-card">
                    <div class="rating mb-1"><?= e(render_stars((float)$review['rating'])) ?></div>
                    <strong><?= e($review['reviewer_name']) ?></strong>
                    <p class="small text-muted mb-1"><?= e($review['comment']) ?></p>
                    <small class="text-muted">Posted on <?= e(date('F d, Y', strtotime($review['created_at']))) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<section class="mt-4">
    <h4 class="section-title">YOU MIGHT ALSO LIKE</h4>
    <div class="row g-3">
        <?php foreach ($related as $rp): ?>
            <div class="col-6 col-md-3">
                <a class="text-decoration-none text-dark" href="<?= BASE_URL ?>/product.php?id=<?= (int)$rp['id'] ?>">
                    <article class="product-card">
                        <img src="<?= e($rp['image_path'] ?: (BASE_URL . '/assets/images/model1.png')) ?>" alt="<?= e($rp['name']) ?>">
                        <h6><?= e($rp['name']) ?></h6>
                        <div class="rating"><?= e(render_stars((float)$rp['rating'])) ?> <?= e((string)$rp['rating']) ?></div>
                        <div><strong>$<?= number_format((float)$rp['price'], 0) ?></strong></div>
                    </article>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
