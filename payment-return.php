<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$orderId = (int)($_GET['order'] ?? 0);
$result  = $_GET['result'] ?? '';
$user    = current_user();

$stmt = db()->prepare('SELECT * FROM orders WHERE id=? AND user_id=? LIMIT 1');
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    flash('danger', 'Order not found.');
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}

if ($result === 'success') {
    flash('info', "Payment submitted for Order #{$orderId}. We'll confirm once it clears — usually within a few minutes.");
} else {
    // Payment was cancelled — release the products back to available so the user can retry
    $items = db()->prepare('SELECT product_id FROM order_items WHERE order_id=?');
    $items->execute([$orderId]);
    foreach ($items->fetchAll() as $row) {
        db()->prepare('UPDATE products SET is_sold=0 WHERE id=?')->execute([$row['product_id']]);
    }
    db()->prepare("UPDATE orders SET status='Cancelled', payment_status='Failed' WHERE id=?")->execute([$orderId]);
    audit_log('user', (int)$user['id'], $user['email'] ?? '', 'ORDER_CANCELLED', "Order #{$orderId} cancelled at payment step");
    flash('warning', "Payment cancelled. Order #{$orderId} has been voided — your items are available again.");
}

header('Location: ' . BASE_URL . '/profile.php');
exit;
