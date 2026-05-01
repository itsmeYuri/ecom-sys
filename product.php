<?php
require_once __DIR__ . '/includes/functions.php';

$productId = (int)($_GET['id'] ?? 0);
if ($productId < 1) {
    header('Location: ' . BASE_URL . '/shop.php');
    exit;
}

if (is_logged_in() && (int)($_GET['add'] ?? 0) === 1) {
    $soldCheck = db()->prepare('SELECT is_sold FROM products WHERE id = ? LIMIT 1');
    $soldCheck->execute([$productId]);
    $soldRow = $soldCheck->fetch();
    if (!empty($soldRow['is_sold'])) {
        flash('warning', 'This item is marked as sold and cannot be added to cart.');
        header('Location: ' . BASE_URL . '/product.php?id=' . $productId);
        exit;
    }
    if (isset($_SESSION['cart'][$productId])) {
        flash('warning', 'This item is already in your cart.');
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }
    $_SESSION['cart'][$productId] = 1;
    flash('success', 'Product added to cart.');
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    verify_csrf();
    if (!is_logged_in()) {
        flash('warning', 'Please login first to add items to your cart.');
        $returnTo = urlencode('/product.php?id=' . $productId . '&add=1');
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
    if (isset($_SESSION['cart'][$productId])) {
        flash('warning', 'This item is already in your cart.');
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }
    $_SESSION['cart'][$productId] = 1;
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


$colors = array_filter(array_map('trim', explode(',', $product['colors'])));
$sizes = array_filter(array_map('trim', explode(',', $product['sizes'])));

include __DIR__ . '/header.php';
?>
<div class="product-page">
    <div class="small mb-3">
    <a href="<?= BASE_URL ?>/homepage.php" class="text-muted text-decoration-none">Home</a>
    <span class="text-muted"> &gt; </span>
    <a href="<?= BASE_URL ?>/shop.php" class="text-muted text-decoration-none">Shop</a>
    <span class="text-muted"> &gt; </span>
    <a href="<?= BASE_URL ?>/shop.php?category=<?= (int)$product['category_id'] ?>" class="text-muted text-decoration-none"><?= e($product['category_name']) ?></a>
    <span class="text-muted"> &gt; </span>
    <span><?= e($product['name']) ?></span>
</div>

    <div class="product-top">
        <div class="product-gallery">
            <?php if ($images): ?>
            <div id="productCarousel" class="carousel slide" data-bs-ride="false" style="border-radius:16px;overflow:hidden;background:#f8f5f0;">
                <div class="carousel-inner">
                    <?php foreach ($images as $idx => $img): ?>
                    <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
                        <img src="<?= e(image_url((int)$img['image_id'], fallback_image_url())) ?>"
                             alt="<?= e($product['name']) ?>"
                             style="width:100%;max-height:420px;object-fit:contain;display:block;margin:auto;">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($images) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1): ?>
            <div class="d-flex gap-2 mt-2 flex-wrap">
                <?php foreach ($images as $idx => $img): ?>
                <img src="<?= e(image_url((int)$img['image_id'], fallback_image_url())) ?>"
                     alt="thumb"
                     class="carousel-thumb <?= $idx === 0 ? 'active-thumb' : '' ?>"
                     data-thumb-index="<?= $idx ?>"
                     onclick="goToSlide(<?= $idx ?>)"
                     style="width:60px;height:60px;object-fit:cover;border-radius:8px;cursor:pointer;border:2px solid <?= $idx === 0 ? '#111' : '#ddd' ?>;transition:border-color .2s;">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <img src="<?= fallback_image_url() ?>" alt="No image" style="width:100%;max-height:420px;object-fit:contain;border-radius:16px;background:#f8f5f0;">
            <?php endif; ?>
        </div>

        <div class="product-summary">
            <h1 class="product-name"><?= e($product['name']) ?>
                <?php if (!empty($product['is_sold'])): ?>
                    <span class="badge text-bg-danger align-middle ms-1">SOLD</span>
                <?php elseif (!empty($product['is_sale'])): ?>
                    <span class="badge text-bg-warning text-dark align-middle ms-1">SALE</span>
                <?php endif; ?>
            </h1>

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

            <?php if ($colors): ?>
            <div class="product-divider"></div>
            <div class="small text-muted mb-1">Available Colors</div>
            <div class="d-flex flex-wrap gap-1 mb-3">
                <?php foreach ($colors as $color): ?>
                    <span class="badge text-bg-light border"><?= e($color) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($sizes): ?>
            <div class="product-divider"></div>
            <div class="small text-muted mb-1">Available Sizes</div>
            <div class="d-flex flex-wrap gap-1 mb-3">
                <?php foreach ($sizes as $size): ?>
                    <span class="size-pill"><?= e($size) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>


            <div class="product-divider"></div>
            <?php if (!empty($product['is_sold'])): ?>
                <button class="btn btn-secondary px-5" disabled>Sold Out</button>
            <?php else: ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_to_cart">
                    <button class="btn btn-dark product-add-btn">Add to Cart</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function goToSlide(idx) {
    const el = document.getElementById('productCarousel');
    if (!el) return;
    bootstrap.Carousel.getOrCreateInstance(el).to(idx);
}
const carousel = document.getElementById('productCarousel');
if (carousel) {
    carousel.addEventListener('slide.bs.carousel', (e) => {
        document.querySelectorAll('[data-thumb-index]').forEach((t) => {
            t.style.borderColor = parseInt(t.dataset.thumbIndex) === e.to ? '#111' : '#ddd';
        });
    });
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
