<?php
require_once __DIR__ . '/../includes/functions.php';
require_employee();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_sold') {
        $id   = (int)($_POST['id'] ?? 0);
        $sold = (int)($_POST['is_sold'] ?? 0) === 1 ? 1 : 0;
        if ($id > 0) {
            db()->prepare('UPDATE products SET is_sold=? WHERE id=?')->execute([$sold, $id]);
            audit_log('employee', (int)$_SESSION['employee']['id'], $_SESSION['employee']['username'], 'PRODUCT_TOGGLE', "Product #{$id} sold={$sold}");
            flash('success', $sold ? 'Product marked as sold.' : 'Product marked as available.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('UPDATE order_items SET product_id=NULL WHERE product_id=?')->execute([$id]);
            db()->prepare('DELETE FROM product_images WHERE product_id=?')->execute([$id]);
            db()->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
            audit_log('employee', (int)$_SESSION['employee']['id'], $_SESSION['employee']['username'], 'PRODUCT_DELETED', "Product #{$id}");
            flash('success', 'Product deleted.');
        }
    }

    if ($action === 'delete_image') {
        $imgId = (int)($_POST['image_id'] ?? 0);
        $prdId = (int)($_POST['product_id'] ?? 0);
        if ($imgId > 0 && $prdId > 0) {
            db()->prepare('DELETE FROM product_images WHERE id=? AND product_id=?')->execute([$imgId, $prdId]);
            flash('success', 'Image removed.');
        }
        header('Location: ' . BASE_URL . '/employee/products.php?edit=' . $prdId); exit;
    }

    if ($action === 'add' || $action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $price       = (float)($_POST['price'] ?? 0);
        $oldPrice    = ($_POST['old_price'] ?? '') !== '' ? (float)$_POST['old_price'] : null;
        $description = trim($_POST['description'] ?? '');
        $colors      = trim($_POST['colors'] ?? '');
        $sizes       = trim($_POST['sizes'] ?? '');
        $popular     = isset($_POST['is_popular']) ? 1 : 0;
        $isSold      = isset($_POST['is_sold'])    ? 1 : 0;
        $isSale      = isset($_POST['is_sale'])    ? 1 : 0;
        $slug        = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        if ($name === '' || $categoryId < 1 || $price <= 0 || $description === '') {
            flash('danger', 'Name, category, price, and description are required.');
            header('Location: ' . BASE_URL . '/employee/products.php'); exit;
        }

        if ($action === 'add') {
            $stmt = db()->prepare('INSERT INTO products (category_id, name, slug, description, price, old_price, colors, sizes, stock, is_popular, is_sold, is_sale) VALUES (?,?,?,?,?,?,?,?,0,?,?,?)');
            $stmt->execute([$categoryId, $name, $slug . '-' . time(), $description, $price, $oldPrice, $colors, $sizes, $popular, $isSold, $isSale]);
            $id = (int)db()->lastInsertId();
        } else {
            $stmt = db()->prepare('UPDATE products SET category_id=?,name=?,description=?,price=?,old_price=?,colors=?,sizes=?,is_popular=?,is_sold=?,is_sale=? WHERE id=?');
            $stmt->execute([$categoryId, $name, $description, $price, $oldPrice, $colors, $sizes, $popular, $isSold, $isSale, $id]);
        }

        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $idx => $tmp) {
                $uploadErr = $_FILES['images']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
                if ($uploadErr !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) continue;

                $fakeFile = ['tmp_name' => $tmp, 'error' => $uploadErr, 'size' => $_FILES['images']['size'][$idx], 'name' => $_FILES['images']['name'][$idx]];
                $imgErr = validate_uploaded_image($fakeFile);
                if ($imgErr) { flash('warning', "Image skipped: {$imgErr}"); continue; }

                $blob  = @file_get_contents($tmp);
                if ($blob === false) continue;
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($tmp) ?: 'application/octet-stream';
                $fname = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['images']['name'][$idx]));
                db()->prepare('INSERT INTO product_images (product_id, image_data, image_mime, image_name, is_main) VALUES (?,?,?,?,?)')
                    ->execute([$id, $blob, $mime, $fname, $idx === 0 ? 1 : 0]);
            }
        }

        audit_log('employee', (int)$_SESSION['employee']['id'], $_SESSION['employee']['username'], 'PRODUCT_SAVED', "Product #{$id}: {$name}");
        flash('success', 'Product saved successfully.');
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

$products   = db()->query('SELECT p.*, c.name as category_name, (SELECT pi.id FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_main DESC, pi.id ASC LIMIT 1) AS image_id FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC')->fetchAll();
$categories = get_categories();

include __DIR__ . '/../header.php';
?>
<h1 class="h3 mb-3">Manage Products</h1>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="detail-box p-3">
            <h5 class="fw-bold mb-3"><?= $editProduct ? 'Edit Product' : 'Add Product' ?></h5>
            <?php if ($editProduct):
                $editImgs = db()->prepare('SELECT id FROM product_images WHERE product_id=? ORDER BY is_main DESC, id ASC');
                $editImgs->execute([$editProduct['id']]);
                $editImgRows = $editImgs->fetchAll();
            ?>
            <?php if ($editImgRows): ?>
            <div class="mb-3">
                <div class="small text-muted mb-1">Current Images <span class="text-danger">(click &times; to delete)</span></div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($editImgRows as $eImg): ?>
                    <div class="position-relative">
                        <img src="<?= e(image_url((int)$eImg['id'], fallback_image_url())) ?>"
                             style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #2d2d2d;background:#141414;">
                        <form method="post" style="position:absolute;top:-6px;right:-6px;"
                              onsubmit="return confirm('Remove this image?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_image">
                            <input type="hidden" name="image_id" value="<?= (int)$eImg['id'] ?>">
                            <input type="hidden" name="product_id" value="<?= (int)$editProduct['id'] ?>">
                            <button type="submit" style="width:20px;height:20px;border-radius:50%;border:none;background:#dc3545;color:#fff;font-size:11px;line-height:1;cursor:pointer;padding:0;">&times;</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editProduct ? 'edit' : 'add' ?>">
                <input type="hidden" name="id"     value="<?= (int)($editProduct['id'] ?? 0) ?>">

                <div class="mb-2">
                    <input class="form-control" name="name" placeholder="Product Name" value="<?= e($editProduct['name'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <select class="form-select" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (int)($editProduct['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col"><input class="form-control" type="number" step="0.01" name="price" placeholder="Price (₱)" value="<?= e((string)($editProduct['price'] ?? '')) ?>" required></div>
                    <div class="col"><input class="form-control" type="number" step="0.01" name="old_price" placeholder="Old Price (₱)" value="<?= e((string)($editProduct['old_price'] ?? '')) ?>"></div>
                </div>
                <div class="mb-2">
                    <input class="form-control" name="colors" placeholder="Colors (e.g. Black,White,Red)" value="<?= e($editProduct['colors'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <input class="form-control" name="sizes" placeholder="Sizes (e.g. S,M,L,XL)" value="<?= e($editProduct['sizes'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <textarea class="form-control" name="description" rows="3" placeholder="Description" required><?= e($editProduct['description'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <input class="form-control" type="file" name="images[]" multiple accept="image/*">
                    <div class="form-text">Upload product images (JPG, PNG, WebP — max 5MB each)</div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_sale" id="isSale" <?= !empty($editProduct['is_sale']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isSale">Tag as Sale</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_sold" id="isSoldCheck" <?= !empty($editProduct['is_sold']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isSoldCheck">Mark as Sold Out</label>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-dark flex-grow-1">Save Product</button>
                    <?php if ($editProduct): ?>
                        <a href="<?= BASE_URL ?>/employee/products.php" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="detail-box p-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td style="width:52px;">
                                <img src="<?= e(image_url(isset($p['image_id']) ? (int)$p['image_id'] : 0, fallback_image_url())) ?>"
                                     style="width:48px;height:48px;object-fit:cover;border-radius:8px;background:#141414;">
                            </td>
                            <td><?= e($p['name']) ?></td>
                            <td><?= e($p['category_name']) ?></td>
                            <td>₱<?= number_format((float)$p['price'], 0) ?></td>
                            <td>
                                <?php if (!empty($p['is_sold'])): ?>
                                    <span class="badge text-bg-danger">SOLD</span>
                                <?php elseif (!empty($p['is_sale'])): ?>
                                    <span class="badge text-bg-warning text-dark">SALE</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">AVAILABLE</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end" style="white-space:nowrap;">
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_sold">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="is_sold" value="<?= !empty($p['is_sold']) ? '0' : '1' ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><?= !empty($p['is_sold']) ? 'Unmark' : 'Mark Sold' ?></button>
                                </form>
                                <a href="?edit=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-dark">Edit</a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this product?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$products): ?>
                        <tr><td colspan="6" class="text-muted py-3 text-center">No products yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
(function(){
    const input = document.querySelector('input[name="images[]"]');
    if (!input) return;
    const wrap = document.createElement('div');
    wrap.id = 'imgPreview';
    wrap.className = 'd-flex flex-wrap gap-2 mt-2';
    input.parentNode.insertBefore(wrap, input.nextSibling);
    input.addEventListener('change', function() {
        wrap.innerHTML = '';
        Array.from(this.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = ev => {
                const img = document.createElement('img');
                img.src = ev.target.result;
                img.style.cssText = 'width:72px;height:72px;object-fit:cover;border-radius:8px;border:2px solid #3b82f6;';
                wrap.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    });
})();
</script>
<?php include __DIR__ . '/../footer.php'; ?>
