<?php
require_once __DIR__ . '/includes/functions.php';

$isEmbeddedView = (($_GET['embed'] ?? '') === '1')
    || (strtolower($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'iframe');

$isUserLoggedIn = is_logged_in();
$isAdminLoggedIn = is_admin_logged_in();

// Always show promo bar when no customer session exists.
$showPromoBar = !$isUserLoggedIn;
$categoriesNav = get_categories();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Threap Glailz</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<?php if (!$isEmbeddedView && $showPromoBar): ?>
<div class="promo-bar" id="promoBar">
    <div class="container d-flex justify-content-center align-items-center gap-2 position-relative">
        <span>Sign up and get 20% off your first order.</span>
        <a href="<?= BASE_URL ?>/register.php">Sign Up Now</a>
        <button class="promo-close" id="promoClose" aria-label="Close">&times;</button>
    </div>
</div>
<?php endif; ?>

<?php if (!$isEmbeddedView): ?>
<header class="sticky-top bg-white main-header">
    <nav class="container navbar navbar-expand-lg py-3">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/homepage.php">Threap Glailz</a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mb-2 mb-lg-0 me-3">
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/shop.php">Shop</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/shop.php?sort=popular">On Sale</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/shop.php">New Arrivals</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/shop.php">Brands</a></li>
            </ul>
            <form class="d-flex nav-search me-auto" method="get" action="<?= BASE_URL ?>/shop.php">
                <input class="form-control" type="search" name="q" placeholder="Search for products...">
            </form>
            <div class="d-flex align-items-center gap-2 nav-actions">
                <a href="<?= BASE_URL ?>/cart.php" class="icon-link" title="Cart">&#128722;<span class="cart-count"><?= cart_items_count() ?></span></a>
                <?php if ($isUserLoggedIn): ?>
                    <a href="<?= BASE_URL ?>/profile.php" class="icon-link" title="My Profile">&#128100;</a>
                    <a href="<?= BASE_URL ?>/logout.php" class="icon-link" title="Logout">&#10140;</a>
                <?php elseif ($isAdminLoggedIn): ?>
                    <a href="<?= BASE_URL ?>/admin/index.php" class="icon-link" title="Admin Dashboard">&#128100;</a>
                    <a href="<?= BASE_URL ?>/admin/logout.php" class="icon-link" title="Admin Logout">&#10140;</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/login.php" class="icon-link" title="Login">&#128100;</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>
<?php endif; ?>

<main class="py-4">
<div class="container">
<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

