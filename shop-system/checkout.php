<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$products = get_cart_products();
$totals = calculate_cart_totals($products);

if (!$products) {
    flash('warning', 'Your cart is empty.');
    header('Location: ' . BASE_URL . '/shop.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = current_user();

    $stmt = db()->prepare('INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, subtotal, discount, delivery_fee, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending")');
    $stmt->execute([
        $user['id'],
        $user['full_name'],
        $user['email'] ?? null,
        $user['phone'] ?? null,
        $totals['subtotal'],
        $totals['discount'],
        $totals['delivery'],
        $totals['total'],
    ]);
    $orderId = (int)db()->lastInsertId();

    $itemStmt = db()->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($products as $item) {
        $itemStmt->execute([
            $orderId,
            $item['id'],
            $item['name'],
            $item['price'],
            $item['quantity'],
            $item['line_total'],
        ]);
    }

    $_SESSION['cart'] = [];
    flash('success', 'Order placed successfully. Order ID #' . $orderId);
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}

include __DIR__ . '/header.php';
?>
<h1 class="h2 mb-4">Checkout</h1>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="detail-box p-3">
            <h5>Order Items</h5>
            <ul class="list-group list-group-flush">
                <?php foreach ($products as $item): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($item['name']) ?> x <?= (int)$item['quantity'] ?></span>
                        <span>$<?= number_format((float)$item['line_total'], 2) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="summary-box p-3">
            <h5>Payment Summary</h5>
            <p class="d-flex justify-content-between"><span>Subtotal</span><span>$<?= number_format($totals['subtotal'], 2) ?></span></p>
            <p class="d-flex justify-content-between text-danger"><span>Discount</span><span>-$<?= number_format($totals['discount'], 2) ?></span></p>
            <p class="d-flex justify-content-between"><span>Delivery</span><span>$<?= number_format($totals['delivery'], 2) ?></span></p>
            <hr>
            <p class="d-flex justify-content-between fw-bold"><span>Total</span><span>$<?= number_format($totals['total'], 2) ?></span></p>
            <form method="post">
                <button class="btn btn-dark w-100">Place Order</button>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>

