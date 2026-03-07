<?php
require_once __DIR__ . '/../includes/functions.php';
require_employee();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_sold') {
        $id = (int)($_POST['id'] ?? 0);
        $sold = (int)($_POST['is_sold'] ?? 0) === 1 ? 1 : 0;
        if ($id > 0) {
            db()->prepare('UPDATE products SET is_sold = ? WHERE id = ?')->execute([$sold, $id]);
            flash('success', $sold ? 'Product marked as sold.' : 'Product marked as available.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            flash('success', 'Product deleted.');
        }
    }

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $oldPrice = ($_POST['old_price'] ?? '') !== '' ? (float)$_POST['old_price'] : null;
        $description = trim($_POST['description'] ?? '');
        $colors = trim($_POST['colors'] ?? '');
        $sizes = trim($_POST['sizes'] ?? '');
        $popular = isset($_POST['is_popular']) ? 1 : 0;
        $isSold = isset($_POST['is_sold']) ? 1 : 0;
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        if ($name === '' || $categoryId < 1 || $price <= 0 || $description === '') {
            flash('danger', 'Name, category, price and description are required.');
            header('Location: ' . BASE_URL . '/employee/products.php');
            exit;
        }

        if ($action === 'add') {
            $stmt = db()->prepare('INSERT INTO products (category_id, name, slug, description, price, old_price, colors, sizes, is_popular, is_sold) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$categoryId, $name, $slug . '-' . time(), $description, $price, $oldPrice, $colors, $sizes, $popular, $isSold]);
            $id = (int)db()->lastInsertId();
        } else {
            $stmt = db()->prepare('UPDATE products SET category_id=?, name=?, description=?, price=?, old_price=?, colors=?, sizes=?, is_popular=?, is_sold=? WHERE id=?');
            $stmt->execute([$categoryId, $name, $description, $price, $oldPrice, $colors, $sizes, $popular, $isSold, $id]);
        }

        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $idx => $tmp) {
                if (!is_uploaded_file($tmp)) {
                    continue;
                }
                $blob = @file_get_contents($tmp);
                if ($blob === false) {
                    continue;
                }
                $mime = mime_content_type($tmp) ?: 'application/octet-stream';
                $name = basename($_FILES['images']['name'][$idx]);
                db()->prepare('INSERT INTO product_images (product_id, image_data, image_mime, image_name, is_main) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$id, $blob, $mime, $name, $idx === 0 ? 1 : 0]);
            }
        }

        flash('success', 'Product saved.');
    }

    header('Location: ' . BASE_URL . '/employee/products.php');
    exit;
}

$editProduct = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();
}

$products = db()->query('SELECT p.*, c.name as category_name FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC')->fetchAll();
$categories = get_categories();

include __DIR__ . '/../header.php';
?>
<h1 class="h3 mb-3">Manage Products</h1>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="detail-box p-3">
            <h5><?= $editProduct ? 'Edit Product' : 'Add Product' ?></h5>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $editProduct ? 'edit' : 'add' ?>">
                <input type="hidden" name="id" value="<?= (int)($editProduct['id'] ?? 0) ?>">
                <div class="mb-2"><input class="form-control" name="name" placeholder="Name" value="<?= e($editProduct['name'] ?? '') ?>" required></div>
                <div class="mb-2">
                    <select class="form-select" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (int)($editProduct['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col"><input class="form-control" type="number" step="0.01" name="price" placeholder="Price" value="<?= e((string)($editProduct['price'] ?? '')) ?>" required></div>
                    <div class="col"><input class="form-control" type="number" step="0.01" name="old_price" placeholder="Old price" value="<?= e((string)($editProduct['old_price'] ?? '')) ?>"></div>
                </div>
                <div class="mb-2"><input class="form-control" name="colors" placeholder="Colors comma separated" value="<?= e($editProduct['colors'] ?? '') ?>"></div>
                <div class="mb-2"><input class="form-control" name="sizes" placeholder="Sizes comma separated" value="<?= e($editProduct['sizes'] ?? '') ?>"></div>
                <div class="mb-2"><textarea class="form-control" name="description" rows="3" placeholder="Description" required><?= e($editProduct['description'] ?? '') ?></textarea></div>
                <div class="mb-2"><input class="form-control" type="file" name="images[]" multiple></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_popular" id="isPopular" <?= !empty($editProduct['is_popular']) ? 'checked' : '' ?>><label for="isPopular" class="form-check-label">Most Popular</label></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_sold" id="isSold" <?= !empty($editProduct['is_sold']) ? 'checked' : '' ?>><label for="isSold" class="form-check-label">Mark as Sold</label></div>
                <button class="btn btn-dark w-100">Save Product</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="detail-box p-3">
            <table class="table table-sm">
                <thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= (int)$p['id'] ?></td>
                            <td><?= e($p['name']) ?></td>
                            <td><?= e($p['category_name']) ?></td>
                            <td>$<?= number_format((float)$p['price'], 2) ?></td>
                            <td>
                                <?php if (!empty($p['is_sold'])): ?>
                                    <span class="badge text-bg-danger">SOLD</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">AVAILABLE</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_sold">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="is_sold" value="<?= !empty($p['is_sold']) ? '0' : '1' ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><?= !empty($p['is_sold']) ? 'Mark Available' : 'Mark Sold' ?></button>
                                </form>
                                <a href="?edit=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-dark">Edit</a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete product?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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

