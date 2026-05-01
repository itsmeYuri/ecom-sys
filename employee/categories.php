<?php
require_once __DIR__ . '/../includes/functions.php';
require_employee();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
            $stmt = db()->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
            try {
                $stmt->execute([$name, $slug]);
                audit_log('employee', (int)$_SESSION['employee']['id'], $_SESSION['employee']['username'], 'CATEGORY_ADDED', "Category: {$name}");
                flash('success', 'Category added.');
            } catch (Throwable $e) {
                flash('danger', 'Category already exists.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $productIds = db()->prepare('SELECT id FROM products WHERE category_id=?');
            $productIds->execute([$id]);
            foreach ($productIds->fetchAll() as $p) {
                db()->prepare('UPDATE order_items SET product_id=NULL WHERE product_id=?')->execute([$p['id']]);
                db()->prepare('DELETE FROM product_images WHERE product_id=?')->execute([$p['id']]);
            }
            db()->prepare('DELETE FROM products WHERE category_id=?')->execute([$id]);
            db()->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
            audit_log('employee', (int)$_SESSION['employee']['id'], $_SESSION['employee']['username'], 'CATEGORY_DELETED', "Category #{$id}");
            flash('success', 'Category deleted.');
        }
    }

    header('Location: ' . BASE_URL . '/employee/categories.php');
    exit;
}

$categories = db()->query('SELECT * FROM categories ORDER BY id DESC')->fetchAll();

include __DIR__ . '/../header.php';
?>
<h1 class="h3 mb-3">Manage Categories</h1>
<div class="row g-4">
    <div class="col-md-4">
        <div class="detail-box p-3">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="mb-3"><label class="form-label">Category Name</label><input class="form-control" name="name" required></div>
                <button class="btn btn-dark w-100">Add Category</button>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="detail-box p-3">
            <table class="table">
                <thead><tr><th>ID</th><th>Name</th><th>Slug</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= (int)$cat['id'] ?></td>
                            <td><?= e($cat['name']) ?></td>
                            <td><?= e($cat['slug']) ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete category?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>

