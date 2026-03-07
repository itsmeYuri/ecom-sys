<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['product_id'] ?? 0);

    if ($action === 'update' && $id > 0) {
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $_SESSION['cart'][$id] = $qty;
    }

    if ($action === 'remove' && $id > 0) {
        unset($_SESSION['cart'][$id]);
    }

    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

$products = get_cart_products();
$totals = calculate_cart_totals($products);

include __DIR__ . '/header.php';
?>
<div class="small text-muted mb-2">Home &gt; Cart</div>
<h1 class="h1 fw-bold mb-4">YOUR CART</h1>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="cart-box p-3 p-md-4">
            <?php if (!$products): ?>
                <p class="mb-0">Your cart is empty. <a href="<?= BASE_URL ?>/shop.php">Go shopping</a>.</p>
            <?php else: ?>
                <?php foreach ($products as $item): ?>
                    <div class="row g-3 align-items-center border-bottom py-3">
                        <div class="col-3 col-md-2">
                            <img class="cart-item-img" src="<?= e(image_url(isset($item['image_id']) ? (int)$item['image_id'] : 0, BASE_URL . '/assets/images/model1.png')) ?>" alt="<?= e($item['name']) ?>">
                        </div>
                        <div class="col-9 col-md-5">
                            <h5 class="h6 mb-1 fw-bold"><?= e($item['name']) ?></h5>
                            <div class="small text-muted">Size: Large</div>
                            <div class="small text-muted">Color: White</div>
                            <div class="fw-bold fs-4 mt-1">$<?= number_format((float)$item['price'], 0) ?></div>
                        </div>
                        <div class="col-8 col-md-3">
                            <form method="post" class="d-flex align-items-center gap-2">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                                <div class="qty-compact">
                                    <button type="button">-</button>
                                    <input type="number" name="quantity" min="1" value="<?= (int)$item['quantity'] ?>">
                                    <button type="button">+</button>
                                </div>
                                <button class="btn btn-sm btn-outline-dark">Update</button>
                            </form>
                        </div>
                        <div class="col-4 col-md-2 text-end">
                            <form method="post">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                                <button class="btn btn-sm btn-danger">??</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="summary-box p-4">
            <h3 class="h4 fw-bold mb-3">Order Summary</h3>
            <p class="d-flex justify-content-between"><span class="text-muted">Subtotal</span><strong>$<?= number_format($totals['subtotal'], 0) ?></strong></p>
            <p class="d-flex justify-content-between"><span class="text-muted">Discount (-20%)</span><strong class="text-danger">-$<?= number_format($totals['discount'], 0) ?></strong></p>
            <p class="d-flex justify-content-between"><span class="text-muted">Delivery Fee</span><strong>$<?= number_format($totals['delivery'], 0) ?></strong></p>
            <hr>
            <p class="d-flex justify-content-between fs-5"><span>Total</span><strong>$<?= number_format($totals['total'], 0) ?></strong></p>
            <div class="d-flex gap-2 mb-3">
                <input class="form-control" placeholder="Add promo code">
                <button class="btn btn-outline-dark">Apply</button>
            </div>
            <a href="<?= BASE_URL ?>/checkout.php" class="btn btn-dark w-100 <?= $products ? '' : 'disabled' ?>">Go to Checkout ?</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
