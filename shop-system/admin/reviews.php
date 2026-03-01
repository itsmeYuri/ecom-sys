<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        flash('success', 'Review deleted.');
    }
    header('Location: ' . BASE_URL . '/admin/reviews.php');
    exit;
}

$reviews = db()->query('SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON p.id = r.product_id ORDER BY r.id DESC')->fetchAll();

include __DIR__ . '/../header.php';
?>
<h1 class="h3 mb-3">Manage Reviews</h1>
<div class="detail-box p-3">
    <table class="table">
        <thead><tr><th>ID</th><th>Product</th><th>Reviewer</th><th>Rating</th><th>Comment</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($reviews as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= e($r['product_name']) ?></td>
                    <td><?= e($r['reviewer_name']) ?></td>
                    <td><?= (int)$r['rating'] ?></td>
                    <td><?= e($r['comment']) ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Delete review?')">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
