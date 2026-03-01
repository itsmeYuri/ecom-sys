<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    $allowed = ['Pending', 'Paid', 'Shipped', 'Completed', 'Cancelled'];
    if ($id > 0 && in_array($status, $allowed, true)) {
        db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
        flash('success', 'Order status updated.');
    }
    header('Location: ' . BASE_URL . '/admin/orders.php');
    exit;
}

$orders = db()->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll();

include __DIR__ . '/../header.php';
?>
<h1 class="h3 mb-3">Orders</h1>
<div class="detail-box p-3">
    <table class="table table-sm">
        <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= (int)$o['id'] ?></td>
                    <td><?= e($o['customer_name']) ?></td>
                    <td>$<?= number_format((float)$o['total'], 2) ?></td>
                    <td><?= e($o['status']) ?></td>
                    <td><?= e($o['created_at']) ?></td>
                    <td>
                        <form method="post" class="d-flex gap-2">
                            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                            <select name="status" class="form-select form-select-sm">
                                <?php foreach (['Pending','Paid','Shipped','Completed','Cancelled'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-dark">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
