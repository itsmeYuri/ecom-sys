<?php
require_once __DIR__ . '/../includes/functions.php';
require_employee();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id           = (int)($_POST['id'] ?? 0);
    $status       = $_POST['status'] ?? 'Pending';
    $trackingLink = trim($_POST['tracking_link'] ?? '');
    $allowed = ['Pending', 'Paid', 'Shipped', 'Completed', 'Cancelled'];
    if ($id > 0 && in_array($status, $allowed, true)) {
        db()->prepare('UPDATE orders SET status=?, tracking_link=? WHERE id=?')
           ->execute([$status, $trackingLink !== '' ? $trackingLink : null, $id]);
        audit_log('employee', (int)$_SESSION['employee']['id'], $_SESSION['employee']['username'], 'ORDER_STATUS_UPDATED', "Order #{$id} -> {$status}");
        flash('success', 'Order updated.');
    }
    header('Location: ' . BASE_URL . '/employee/orders.php');
    exit;
}

$orders = db()->query('SELECT id,customer_name,total,status,created_at,tracking_link FROM orders ORDER BY id DESC')->fetchAll();

// Pre-fetch all order items with product images
$orderIds = array_column($orders, 'id');
$orderItems = [];
if ($orderIds) {
    $ph   = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = db()->prepare("
        SELECT oi.order_id, oi.product_name, oi.quantity, oi.unit_price,
               (SELECT pi.id FROM product_images pi WHERE pi.product_id = oi.product_id
                ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_id
        FROM order_items oi
        WHERE oi.order_id IN ({$ph})
        ORDER BY oi.order_id, oi.id
    ");
    $stmt->execute($orderIds);
    foreach ($stmt->fetchAll() as $row) {
        $orderItems[$row['order_id']][] = $row;
    }
}

include __DIR__ . '/../header.php';
?>
<h1 class="h3 mb-3">Orders</h1>
<div class="detail-box p-3">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o):
                $items = $orderItems[(int)$o['id']] ?? [];
            ?>
                <tr>
                    <td>#<?= (int)$o['id'] ?></td>
                    <td><?= e($o['customer_name']) ?></td>
                    <td>₱<?= number_format((float)$o['total'], 2) ?></td>
                    <td>
                        <?php
                        $cls = ['Pending'=>'warning','Paid'=>'success','Shipped'=>'info','Completed'=>'dark','Cancelled'=>'danger'];
                        echo '<span class="badge text-bg-'.($cls[$o['status']]??'secondary').'">'.e($o['status']).'</span>';
                        ?>
                    </td>
                    <td class="small text-muted"><?= e(date('M d, Y', strtotime($o['created_at']))) ?></td>
                    <td>
                        <?php if ($items): ?>
                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#items-<?= (int)$o['id'] ?>"
                                aria-expanded="false">
                            View Items (<?= count($items) ?>)
                        </button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                            <div class="d-flex gap-2 mb-1">
                                <select name="status" class="form-select form-select-sm" style="width:auto;">
                                    <?php foreach (['Pending','Paid','Shipped','Completed','Cancelled'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-dark">Save</button>
                            </div>
                            <input type="url" name="tracking_link" class="form-control form-control-sm"
                                   placeholder="Tracking link (e.g. Lalamove URL)"
                                   value="<?= e($o['tracking_link'] ?? '') ?>"
                                   style="max-width:280px;">
                        </form>
                    </td>
                </tr>
                <?php if ($items): ?>
                <tr class="collapse" id="items-<?= (int)$o['id'] ?>">
                    <td colspan="7" class="px-4 py-3" style="background:#0a0a0a;">
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($items as $item): ?>
                            <div class="d-flex align-items-center gap-2" style="min-width:200px;background:#141414;border-radius:10px;padding:8px 12px;border:1px solid #2d2d2d;">
                                <img src="<?= e(image_url(isset($item['image_id']) ? (int)$item['image_id'] : 0, fallback_image_url())) ?>"
                                     style="width:52px;height:52px;object-fit:cover;border-radius:8px;flex-shrink:0;background:#1a1a1a;">
                                <div>
                                    <div class="fw-semibold" style="font-size:13px;"><?= e($item['product_name']) ?></div>
                                    <div class="text-muted" style="font-size:11px;">
                                        Qty: <?= (int)$item['quantity'] ?> &bull; ₱<?= number_format((float)$item['unit_price'], 0) ?> each
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="7" class="text-muted text-center py-3">No orders yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
