<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['product_id'] ?? 0);

    if ($action === 'remove' && $id > 0) {
        unset($_SESSION['cart'][$id]);
    }

    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

$products = get_cart_products();
$totals   = calculate_cart_totals($products);

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
                            <img class="cart-item-img" src="<?= e(image_url(isset($item['image_id']) ? (int)$item['image_id'] : 0, fallback_image_url())) ?>" alt="<?= e($item['name']) ?>">
                        </div>
                        <div class="col-7 col-md-8">
                            <h5 class="h6 mb-1 fw-bold"><?= e($item['name']) ?></h5>
                            <div class="small text-muted">Qty: <?= (int)$item['quantity'] ?></div>
                            <div class="fw-bold fs-5 mt-1">₱<?= number_format((float)$item['line_total'], 0) ?></div>
                        </div>
                        <div class="col-2 text-end">
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Remove">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                    </svg>
                                </button>
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
            <p class="d-flex justify-content-between fs-5 mb-3"><span>Total</span><strong>₱<?= number_format($totals['total'], 0) ?></strong></p>
            <a href="<?= BASE_URL ?>/checkout.php" class="btn btn-dark w-100 <?= $products ? '' : 'disabled' ?>">Go to Checkout &rarr;</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
