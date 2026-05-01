<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();

$orderStmt = db()->prepare('SELECT id, total, status, created_at, tracking_link FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 20');
$orderStmt->execute([(int)$user['id']]);
$orders = $orderStmt->fetchAll();

$orderItems = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("
        SELECT oi.order_id, oi.product_name, oi.quantity, oi.unit_price,
               (SELECT pi.id FROM product_images pi
                WHERE pi.product_id = oi.product_id
                ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_id
        FROM order_items oi WHERE oi.order_id IN ({$ph}) ORDER BY oi.order_id, oi.id
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $orderItems[$row['order_id']][] = $row;
    }
}

include __DIR__ . '/header.php';
?>
<h1 class="h2 mb-4">My Profile</h1>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="detail-box p-3">
            <h5 class="mb-3">Account Details</h5>
            <p class="mb-2"><strong>Name:</strong> <?= e($user['full_name']) ?></p>
            <p class="mb-2"><strong>Email:</strong> <?= e($user['email'] ?? '-') ?></p>
            <p class="mb-2"><strong>Phone:</strong> <?= e($user['phone'] ?? '-') ?></p>
            <?php
            $totpRow = db()->prepare('SELECT totp_enabled FROM users WHERE id=? LIMIT 1');
            $totpRow->execute([(int)$user['id']]);
            $totpActive = (bool)$totpRow->fetchColumn();
            ?>
            <p class="mb-3"><strong>Authenticator:</strong>
                <?php if ($totpActive): ?>
                    <span class="badge text-bg-success ms-1">Active</span>
                <?php else: ?>
                    <span class="badge text-bg-secondary ms-1">Not set up</span>
                <?php endif; ?>
            </p>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-dark btn-sm">Logout</a>
                <a href="<?= BASE_URL ?>/setup-totp.php" class="btn btn-outline-dark btn-sm">&#128241; Authenticator</a>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="detail-box p-3">
            <h5 class="mb-3">Recent Orders</h5>
            <?php if (!$orders): ?>
                <p class="mb-0 text-muted">No orders yet.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                <?php foreach ($orders as $order):
                    $items = $orderItems[(int)$order['id']] ?? [];
                    $firstItem = $items[0] ?? null;
                    $cls = ['Pending'=>'warning','Paid'=>'success','Shipped'=>'info','Completed'=>'dark','Cancelled'=>'danger'];
                ?>
                    <div class="border rounded overflow-hidden">
                        <div class="d-flex align-items-center gap-3 p-3">
                            <?php if ($firstItem): ?>
                            <img src="<?= e(image_url(isset($firstItem['image_id']) ? (int)$firstItem['image_id'] : 0, fallback_image_url())) ?>"
                                 style="width:56px;height:56px;object-fit:cover;border-radius:8px;flex-shrink:0;background:#f0e6e6;">
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= $firstItem ? e($firstItem['product_name']) : 'Order' ?><?= count($items) > 1 ? ' <span class="text-muted">+'.( count($items)-1).' more</span>' : '' ?></div>
                                <div class="text-muted" style="font-size:11px;">Order #<?= (int)$order['id'] ?> &bull; <?= e(date('M d, Y', strtotime($order['created_at']))) ?></div>
                                <span class="badge text-bg-<?= $cls[$order['status']] ?? 'secondary' ?>"><?= e($order['status']) ?></span>
                                <?php if (!empty($order['tracking_link'])): ?>
                                <div style="font-size:11px;margin-top:3px;">
                                    <a href="<?= e($order['tracking_link']) ?>" target="_blank" rel="noopener" class="fw-semibold" style="color:#3b82f6;">Track Order &rarr;</a>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold">₱<?= number_format((float)$order['total'], 0) ?></span>
                                <?php if (count($items) > 0): ?>
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#order-<?= (int)$order['id'] ?>">
                                    Items
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (count($items) > 0): ?>
                        <div class="collapse" id="order-<?= (int)$order['id'] ?>">
                            <div class="border-top p-3 bg-light">
                                <div class="d-flex flex-column gap-2">
                                <?php foreach ($items as $item): ?>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e(image_url(isset($item['image_id']) ? (int)$item['image_id'] : 0, fallback_image_url())) ?>"
                                             style="width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0;background:#f0e6e6;">
                                        <div class="flex-grow-1">
                                            <div class="small fw-semibold"><?= e($item['product_name']) ?></div>
                                            <div class="text-muted" style="font-size:11px;">Qty: <?= (int)$item['quantity'] ?> &bull; ₱<?= number_format((float)$item['unit_price'], 0) ?> each</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
