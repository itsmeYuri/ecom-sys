<?php
require_once __DIR__ . '/../includes/functions.php';
require_employee();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '') {
        flash('danger', 'Title is required.');
        header('Location: ' . BASE_URL . '/employee/hero-settings.php'); exit;
    }

    $stmt = db()->prepare('SELECT id FROM hero_settings WHERE id=1 LIMIT 1');
    $stmt->execute();
    $exists = $stmt->fetchColumn();

    if (!empty($_FILES['hero_image']['tmp_name']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['hero_image']['tmp_name'];
        $imgErr = validate_uploaded_image(['tmp_name'=>$tmp,'error'=>$_FILES['hero_image']['error'],'size'=>$_FILES['hero_image']['size'],'name'=>$_FILES['hero_image']['name']]);
        if ($imgErr) {
            flash('danger', "Image error: {$imgErr}");
            header('Location: ' . BASE_URL . '/employee/hero-settings.php'); exit;
        }
        $blob = @file_get_contents($tmp);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmp) ?: 'image/jpeg';

        if ($exists) {
            db()->prepare('UPDATE hero_settings SET title=?,description=?,image_data=?,image_mime=? WHERE id=1')
               ->execute([$title, $description, $blob, $mime]);
        } else {
            db()->prepare('INSERT INTO hero_settings (id,title,description,image_data,image_mime) VALUES (1,?,?,?,?)')
               ->execute([$title, $description, $blob, $mime]);
        }
    } else {
        if ($exists) {
            db()->prepare('UPDATE hero_settings SET title=?,description=? WHERE id=1')
               ->execute([$title, $description]);
        } else {
            db()->prepare('INSERT INTO hero_settings (id,title,description) VALUES (1,?,?)')
               ->execute([$title, $description]);
        }
    }

    audit_log('employee', (int)$_SESSION['employee']['id'], $_SESSION['employee']['username'], 'HERO_UPDATED', 'Hero settings updated');
    flash('success', 'Hero settings saved.');
    header('Location: ' . BASE_URL . '/employee/hero-settings.php'); exit;
}

$stmt = db()->prepare('SELECT * FROM hero_settings WHERE id=1 LIMIT 1');
$stmt->execute();
$hero = $stmt->fetch();

include __DIR__ . '/../header.php';
?>
<h1 class="h3 mb-4">Hero Settings</h1>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="detail-box p-3">
            <h5 class="fw-bold mb-3">Edit Homepage Hero</h5>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Headline</label>
                    <input class="form-control" name="title" value="<?= e($hero['title'] ?? 'FIND CLOTHES THAT MATCHES YOUR STYLE') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="4"><?= e($hero['description'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Hero Image</label>
                    <input class="form-control" type="file" name="hero_image" accept="image/*">
                    <div class="form-text">JPG, PNG, or WebP — max 5MB. Leave blank to keep current image.</div>
                </div>
                <button class="btn btn-dark">Save</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="detail-box p-3">
            <h5 class="fw-bold mb-3">Current Hero Preview</h5>
            <?php if (!empty($hero['image_data'])): ?>
            <img src="<?= BASE_URL ?>/hero-image.php?t=<?= time() ?>" alt="Hero" style="width:100%;border-radius:12px;object-fit:cover;max-height:300px;">
            <?php else: ?>
            <p class="text-muted small">No hero image uploaded yet.</p>
            <?php endif; ?>
            <div class="mt-3">
                <p class="fw-semibold mb-1"><?= e($hero['title'] ?? 'FIND CLOTHES THAT MATCHES YOUR STYLE') ?></p>
                <p class="text-muted small"><?= e($hero['description'] ?? '') ?></p>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
