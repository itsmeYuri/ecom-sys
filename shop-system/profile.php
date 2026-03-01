<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();

$orderStmt = db()->prepare('SELECT id, total, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10');
$orderStmt->execute([(int)$user['id']]);
$orders = $orderStmt->fetchAll();

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
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-dark btn-sm mt-2">Logout</a>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="detail-box p-3">
            <h5 class="mb-3">Recent Orders</h5>
            <?php if (!$orders): ?>
                <p class="mb-0 text-muted">No orders yet.</p>
            <?php else: ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= (int)$order['id'] ?></td>
                            <td>$<?= number_format((float)$order['total'], 2) ?></td>
                            <td><?= e($order['status']) ?></td>
                            <td><?= e(date('M d, Y', strtotime($order['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
