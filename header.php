<?php
require_once __DIR__ . '/includes/functions.php';
check_session_timeout();

$isEmbedded         = (($_GET['embed'] ?? '') === '1') || (strtolower($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'iframe');
$isUserLoggedIn     = is_logged_in();
$isAdminLoggedIn    = is_admin_logged_in();
$isEmpLoggedIn      = is_employee_logged_in();
$isBackOffice       = $isAdminLoggedIn || $isEmpLoggedIn;
$showStorefront     = !$isEmbedded && !$isBackOffice;
$showPromoBar       = $showStorefront && !$isUserLoggedIn;
$categoriesNav      = get_categories();
$sessionTimeout     = (int)get_setting('session_timeout_minutes', '120');
$maintenanceMode    = get_setting('maintenance_mode', '0') === '1';
$navShowShop        = get_setting('nav_show_shop',   '1') === '1';
$navShowNewIn       = get_setting('nav_show_new_in', '1') === '1';
$navShowSale        = get_setting('nav_show_sale',   '1') === '1';

if (!$isUserLoggedIn && !$isAdminLoggedIn && !$isEmpLoggedIn) {
    $_SESSION['cart'] = [];
}

if ($maintenanceMode && !$isAdminLoggedIn) {
    if (!defined('BYPASS_MAINTENANCE')) {
        http_response_code(503);
        echo '<!doctype html><html><head><title>Maintenance &mdash; ' . APP_NAME . '</title></head><body style="font-family:Arial;text-align:center;padding:80px;"><h2>&#128736;&#65039; Under Maintenance</h2><p>We\'ll be back shortly. Thank you for your patience.</p></body></html>';
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
:root { --bs-font-sans-serif: 'Poppins', system-ui, sans-serif; }
.cookie-banner {
    position:fixed;bottom:0;left:0;right:0;z-index:9999;
    background:#111;color:#fff;padding:14px 24px;
    display:flex;flex-wrap:wrap;align-items:center;gap:12px;
    justify-content:space-between;box-shadow:0 -2px 10px rgba(0,0,0,.4);
}
.cookie-banner p{margin:0;font-size:13px;}
.cookie-banner a{color:#f5a10a;}
.admin-bar{background:#080808;color:#f0f0f0;font-size:14px;padding:10px 20px;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px;border-bottom:1px solid #2d2d2d;}
.admin-bar a{color:#ffffff;text-decoration:none;margin-right:8px;}
.admin-bar a:hover{color:#aaaaaa;}
.session-warn{display:none;position:fixed;bottom:80px;right:20px;z-index:9998;background:#fff3cd;border:1px solid #ffc107;border-radius:10px;padding:14px 18px;font-size:13px;box-shadow:0 2px 12px rgba(0,0,0,.15);max-width:280px;}
</style>
</head>
<body class="<?= $showStorefront ? 'storefront-dark' : ($isBackOffice ? 'admin-dark' : '') ?>">


<?php if ($showPromoBar): ?>
<div class="promo-bar" id="promoBar">
    <div class="container d-flex justify-content-center align-items-center gap-2 position-relative">
        <span>Sign up and get 20% off your first order.</span>
        <a href="<?= BASE_URL ?>/register.php">Sign Up Now</a>
        <button class="promo-close" id="promoClose" aria-label="Close">&times;</button>
    </div>
</div>
<?php endif; ?>

<?php if ($isBackOffice): ?>
<div class="admin-bar">
    <div>
        <?php if ($isAdminLoggedIn): ?>
        <span>&#9733; Admin: <strong><?= e($_SESSION['admin']['username'] ?? '') ?></strong></span>
        <a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/admin/accounts.php">Accounts</a>
        <a href="<?= BASE_URL ?>/admin/orders.php">Orders</a>
        <a href="<?= BASE_URL ?>/admin/locked-accounts.php">Locked</a>
        <a href="<?= BASE_URL ?>/admin/audit-logs.php">Audit Logs</a>
        <a href="<?= BASE_URL ?>/admin/settings.php">Settings</a>
        <?php else: ?>
        <span>&#128100; Employee: <strong><?= e($_SESSION['employee']['username'] ?? '') ?></strong></span>
        <a href="<?= BASE_URL ?>/employee/index.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/employee/products.php">Products</a>
        <a href="<?= BASE_URL ?>/employee/orders.php">Orders</a>
        <a href="<?= BASE_URL ?>/employee/categories.php">Categories</a>
        <a href="<?= BASE_URL ?>/employee/nav-settings.php">Navbar</a>
        <a href="<?= BASE_URL ?>/employee/hero-settings.php">Hero</a>
        <?php endif; ?>
    </div>
    <?php if ($isAdminLoggedIn): ?>
    <a href="<?= BASE_URL ?>/logout.php" style="color:#ffffff;text-decoration:none;">
        Logout &#10140;
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($showStorefront): ?>
<header class="sticky-top bg-white main-header">
    <nav class="container navbar navbar-expand-lg" style="padding-top:1.1rem;padding-bottom:1.1rem;">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/homepage.php" style="font-size:1.45rem;letter-spacing:-0.5px;"><?= e(APP_NAME) ?></a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mb-2 mb-lg-0 me-3" style="font-size:1rem;font-weight:600;gap:4px;">
                <?php if ($navShowNewIn): ?>
                <li class="nav-item"><a class="nav-link px-3" href="<?= BASE_URL ?>/shop.php?sort=new">New In</a></li>
                <?php endif; ?>
                <?php if ($navShowSale): ?>
                <li class="nav-item"><a class="nav-link px-3" href="<?= BASE_URL ?>/shop.php?sale=1">Sale</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-3 nav-actions ms-3">
                <a href="<?= BASE_URL ?>/cart.php" class="icon-link position-relative" title="Cart">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l1.313 7h8.17l1.313-7H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM5 13a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                    </svg>
                    <?php $cc = cart_items_count(); if ($cc > 0): ?>
                    <span class="cart-count"><?= $cc ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($isUserLoggedIn): ?>
                    <a href="<?= BASE_URL ?>/profile.php" class="icon-link" title="My Profile">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.029 10 8 10c-2.029 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                        </svg>
                    </a>
                    <a href="<?= BASE_URL ?>/logout.php" class="icon-link" title="Logout">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                            <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                        </svg>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-dark btn-sm" style="border-radius:20px;padding:7px 20px;font-size:.9rem;">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>
<?php endif; ?>

<main class="py-4">
<div class="container">
<?php foreach (get_flashes() as $flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>
