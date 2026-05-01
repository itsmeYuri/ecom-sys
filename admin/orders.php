<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id            = (int)($_POST['id'] ?? 0);
    $status        = $_POST['status'] ?? '';
    $paymentStatus = $_POST['payment_status'] ?? '';
    $allowedS  = ['Pending','Paid','Shipped','Completed','Cancelled'];
    $allowedP  = ['Pending','Paid','Failed','Refunded'];

    if ($id > 0) {
        if (in_array($status, $allowedS, true))        db()->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status,$id]);
        if (in_array($paymentStatus, $allowedP, true)) db()->prepare('UPDATE orders SET payment_status=? WHERE id=?')->execute([$paymentStatus,$id]);
        audit_log('admin', (int)$_SESSION['admin']['id'], $_SESSION['admin']['username'], 'ORDER_STATUS_UPDATE', "Order #{$id}: status={$status}, payment={$paymentStatus}");
        flash('success', "Order #{$id} updated.");
    }
    header('Location: ' . BASE_URL . '/admin/orders.php'); exit;
}

$statusFilter = $_GET['status'] ?? '';
$allowedS     = ['Pending','Paid','Shipped','Completed','Cancelled'];
$where  = ($statusFilter && in_array($statusFilter, $allowedS, true)) ? 'WHERE o.status=?' : '';
$params = $where ? [$statusFilter] : [];
$stmt   = db()->prepare("SELECT o.*, u.full_name AS user_full_name FROM orders o LEFT JOIN users u ON o.user_id=u.id {$where} ORDER BY o.id DESC LIMIT 200");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$counts = db()->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Orders</h1>
        <p class="text-muted small mb-0">Monitor and manage all customer orders</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-dark btn-sm">&#8592; Dashboard</a>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
</div>

<div class="mb-3 d-flex flex-wrap gap-2">
    <a href="?" class="btn btn-sm <?= $statusFilter===''?'btn-dark':'btn-outline-dark' ?>">All (<?= array_sum($counts) ?>)</a>
    <?php foreach ($allowedS as $s): ?>
    <a href="?status=<?= urlencode($s) ?>" class="btn btn-sm <?= $statusFilter===$s?'btn-dark':'btn-outline-dark' ?>"><?= $s ?> (<?= (int)($counts[$s]??0) ?>)</a>
    <?php endforeach; ?>
</div>

<div class="detail-box p-3">
    <?php if ($orders): ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle small">
            <thead class="table-light">
                <tr><th>#</th><th>Customer</th><th>Email</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="fw-bold">#<?= (int)$o['id'] ?></td>
                    <td><?= e($o['customer_name']) ?></td>
                    <td><?= e($o['customer_email'] ?? '—') ?></td>
                    <td>&#8369;<?= number_format((float)$o['total'], 2) ?></td>
                    <td>
                        <?php $pClr=['Pending'=>'warning','Paid'=>'success','Failed'=>'danger','Refunded'=>'info']; ?>
                        <span class="badge text-bg-<?= $pClr[$o['payment_status']??'Pending']??'secondary' ?>"><?= e($o['payment_status']??'Pending') ?></span>
                        <div class="text-muted" style="font-size:10px;"><?= strtoupper(e($o['payment_method']??'COD')) ?></div>
                    </td>
                    <td>
                        <?php $sClr=['Pending'=>'warning','Paid'=>'success','Shipped'=>'info','Completed'=>'dark','Cancelled'=>'danger']; ?>
                        <span class="badge text-bg-<?= $sClr[$o['status']]??'secondary' ?>"><?= e($o['status']) ?></span>
                    </td>
                    <td class="text-muted"><?= e(date('M d Y', strtotime($o['created_at']))) ?></td>
                    <td>
                        <button class="btn btn-xs btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#mo<?= (int)$o['id'] ?>">Edit</button>
                        <div class="modal fade" id="mo<?= (int)$o['id'] ?>" tabindex="-1">
                            <div class="modal-dialog"><div class="modal-content">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                    <div class="modal-header"><h6 class="modal-title">Order #<?= (int)$o['id'] ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <p class="small text-muted mb-3"><?= e($o['customer_name']) ?> &mdash; &#8369;<?= number_format((float)$o['total'],2) ?></p>
                                        <div class="mb-3">
                                            <label class="form-label">Order Status</label>
                                            <select name="status" class="form-select">
                                                <?php foreach ($allowedS as $s): ?><option value="<?=$s?>" <?=$o['status']===$s?'selected':''?>><?=$s?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Payment Status</label>
                                            <select name="payment_status" class="form-select">
                                                <?php foreach (['Pending','Paid','Failed','Refunded'] as $p): ?><option value="<?=$p?>" <?=($o['payment_status']??'Pending')===$p?'selected':''?>><?=$p?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button class="btn btn-dark btn-sm">Save</button></div>
                                </form>
                            </div></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-center text-muted py-4">No orders found.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
