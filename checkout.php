<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$products = get_cart_products();
$totals   = calculate_cart_totals($products);
$user     = current_user();

if (!$products) {
    flash('warning', 'Your cart is empty.');
    header('Location: ' . BASE_URL . '/shop.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $paymentMethod  = $_POST['payment_method'] ?? 'gcash';
    $shippingLine1  = trim($_POST['shipping_line1'] ?? '');
    $shippingCity   = trim($_POST['shipping_city']  ?? '');
    $shippingZip    = trim($_POST['shipping_zip']   ?? '');
    $allowedMethods = ['gcash', 'maya'];
    if (!in_array($paymentMethod, $allowedMethods, true)) $paymentMethod = 'gcash';

    if ($shippingLine1 === '' || $shippingCity === '') {
        flash('danger', 'Please fill in your street/barangay address and city before placing the order.');
        header('Location: ' . BASE_URL . '/checkout.php'); exit;
    }

    $shippingAddress = encrypt_data("{$shippingLine1}, {$shippingCity} {$shippingZip}");

    $stmt = db()->prepare('INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, subtotal, discount, delivery_fee, total, payment_method, payment_status, shipping_address, status) VALUES (?,?,?,?,?,?,?,?,?,"Pending",?,"Pending")');
    $stmt->execute([$user['id'], $user['full_name'], $user['email']??null, $user['phone']??null, $totals['subtotal'], 0, 0, $totals['total'], $paymentMethod, $shippingAddress]);
    $orderId = (int)db()->lastInsertId();

    $itemStmt = db()->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?,?,?,?,?,?)');
    foreach ($products as $item) {
        $itemStmt->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], $item['line_total']]);
        db()->prepare('UPDATE products SET is_sold=1 WHERE id=?')->execute([$item['id']]);
    }

    audit_log('user', (int)$user['id'], $user['email'], 'ORDER_PLACED', "Order #{$orderId} via {$paymentMethod}, ₱" . number_format($totals['total'], 2));
    $_SESSION['cart'] = [];

    $checkoutUrl = paymongo_create_checkout($orderId, (float)$totals['total'], APP_NAME . ' Order #' . $orderId, $paymentMethod);
    if ($checkoutUrl) {
        header('Location: ' . $checkoutUrl);
        exit;
    }

    // Fallback: PayMongo not configured — treat as manual payment
    flash('success', "Order #{$orderId} placed! Complete your " . strtoupper($paymentMethod) . " payment and we'll confirm shortly.");
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}

include __DIR__ . '/header.php';
?>
<div class="mb-4">
    <h1 class="h2 fw-bold mb-1">Checkout</h1>
    <p class="text-muted small">Fill in your shipping details and choose a payment method</p>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="detail-box p-4 mb-4">
            <h5 class="fw-bold mb-3">&#128230; Shipping Details</h5>
            <form id="checkoutForm" method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="payment_method" id="selectedMethod" value="gcash">
                <div class="mb-3">
                    <label class="form-label">Street / Barangay <span class="text-danger">*</span></label>
                    <input name="shipping_line1" class="form-control" placeholder="123 Rizal St, Barangay San Jose" required>
                </div>
                <div class="row g-3">
                    <div class="col-8">
                        <label class="form-label">City <span class="text-danger">*</span></label>
                        <input name="shipping_city" class="form-control" placeholder="Makati City" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">ZIP</label>
                        <input name="shipping_zip" class="form-control" placeholder="1200" maxlength="10">
                    </div>
                </div>
            </form>
        </div>

        <div class="detail-box p-4">
            <h5 class="fw-bold mb-3">&#128722; Order Items</h5>
            <ul class="list-group list-group-flush">
                <?php foreach ($products as $item): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?= e(image_url(isset($item['image_id'])?(int)$item['image_id']:0)) ?>"
                             style="width:52px;height:52px;object-fit:cover;border-radius:8px;background:#f0e6e6;">
                        <div>
                            <div class="fw-semibold small"><?= e($item['name']) ?></div>
                            <div class="text-muted" style="font-size:12px;">Qty: <?= (int)$item['quantity'] ?></div>
                        </div>
                    </div>
                    <span class="fw-semibold">&#8369;<?= number_format((float)$item['line_total'], 2) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="detail-box p-4 mb-4">
            <h5 class="fw-bold mb-3">&#128181; Order Summary</h5>
            <hr>
            <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span>&#8369;<?= number_format($totals['total'], 2) ?></span></div>
        </div>

        <div class="detail-box p-4">
            <h5 class="fw-bold mb-3">&#128179; Payment Method</h5>
            <div class="d-grid gap-2 mb-4">
                <button type="button" class="btn btn-outline-dark payment-btn selected" data-method="gcash">&#128247; GCash</button>
                <button type="button" class="btn btn-outline-dark payment-btn" data-method="maya">&#128179; Maya</button>
            </div>
            <button type="submit" form="checkoutForm" class="btn btn-dark w-100 btn-lg">Place Order &rarr;</button>
            <p class="text-center mt-2 small text-muted">&#128274; Your information is encrypted</p>
        </div>
    </div>
</div>

<style>.payment-btn.selected{background:#111!important;color:#fff!important;border-color:#111!important;}</style>
<script>
document.querySelectorAll('.payment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.payment-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        document.getElementById('selectedMethod').value = btn.dataset.method;
    });
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
