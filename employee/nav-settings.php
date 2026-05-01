<?php
require_once __DIR__ . '/../includes/functions.php';
require_employee();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    set_setting('nav_show_new_in', isset($_POST['nav_show_new_in']) ? '1' : '0');
    set_setting('nav_show_sale',   isset($_POST['nav_show_sale'])   ? '1' : '0');
    audit_log('employee', (int)$_SESSION['employee']['id'], $_SESSION['employee']['username'], 'NAV_SETTINGS_UPDATED', 'Navbar visibility updated');
    flash('success', 'Navbar settings saved.');
    header('Location: ' . BASE_URL . '/employee/nav-settings.php'); exit;
}

$navNewIn = get_setting('nav_show_new_in', '1') === '1';
$navSale  = get_setting('nav_show_sale',   '1') === '1';

include __DIR__ . '/../header.php';
?>
<h1 class="h3 mb-1">Navbar Settings</h1>
<p class="text-muted small mb-4">Control which links appear in the storefront navigation bar.</p>

<div class="row justify-content-start">
    <div class="col-md-5">
        <div class="detail-box p-4">
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="nav_show_new_in" id="navNewIn" <?= $navNewIn ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="navNewIn">Show "New In"</label>
                    <div class="form-text">Displays newly uploaded products.</div>
                </div>
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="nav_show_sale" id="navSale" <?= $navSale ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="navSale">Show "Sale"</label>
                    <div class="form-text">Displays products tagged as Sale.</div>
                </div>
                <button class="btn btn-dark w-100">Save Settings</button>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
