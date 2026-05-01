<?php
require_once __DIR__ . '/includes/functions.php';

$newArrivals = db()->query("
    SELECT p.*,
           (SELECT pi.id FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_main DESC, id ASC LIMIT 1) AS image_id
    FROM products p
    ORDER BY p.id DESC
    LIMIT 8
")->fetchAll();

$heroStmt = db()->prepare('SELECT title, description, image_data FROM hero_settings WHERE id=1 LIMIT 1');
$heroStmt->execute();
$hero = $heroStmt->fetch();
$heroTitle = $hero['title'] ?? 'FIND CLOTHES THAT MATCHES YOUR STYLE';
$heroDesc  = $hero['description'] ?? 'Browse unique vintage finds and budget-friendly treasures made for everyday confidence. Discover one-of-a-kind pieces, timeless classics, and hidden gems curated to elevate your personal look — sustainably and affordably.';
$heroHasImg = !empty($hero['image_data']);

$showPromoBar = !is_logged_in();
$userLoggedIn = is_logged_in();
$cartCount    = cart_items_count();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/index-reference.css" />
</head>
<body>

  <?php if ($showPromoBar): ?>
  <div class="promo-bar" id="promoBar">
    <div class="container promo-inner">
      <span>Sign up and get 20% off to your first order.</span>
      <a href="<?= BASE_URL ?>/register.php">Sign Up Now</a>
      <button class="promo-close" id="promoClose" aria-label="Close">&times;</button>
    </div>
  </div>
  <?php endif; ?>

  <header class="navbar">
    <div class="container nav-inner">
      <a class="logo" href="<?= BASE_URL ?>/homepage.php"><?= e(APP_NAME) ?></a>
      <nav class="nav-links">
        <a href="<?= BASE_URL ?>/shop.php?sort=new">New In</a>
        <a href="<?= BASE_URL ?>/shop.php?sale=1">Sale</a>
      </nav>
      <div class="nav-actions">
        <form method="get" action="<?= BASE_URL ?>/shop.php" class="nav-search-form">
          <div class="nav-search-wrap">
            <input class="nav-search-input" type="search" name="q" placeholder="Search products...">
            <button type="submit" class="icon-btn" aria-label="Search">
              <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="currentColor" viewBox="0 0 16 16">
                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
              </svg>
            </button>
          </div>
        </form>
        <a class="icon-btn cart-btn" href="<?= BASE_URL ?>/cart.php" aria-label="Cart">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
            <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l1.313 7h8.17l1.313-7H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM5 13a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
          </svg>
          <?php if ($cartCount > 0): ?>
          <span class="cart-badge"><?= $cartCount ?></span>
          <?php endif; ?>
        </a>
        <?php if ($userLoggedIn): ?>
        <a class="icon-btn" href="<?= BASE_URL ?>/profile.php" aria-label="My Profile">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.029 10 8 10c-2.029 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
          </svg>
        </a>
        <a class="icon-btn" href="<?= BASE_URL ?>/logout.php" aria-label="Logout">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
            <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
          </svg>
        </a>
        <?php else: ?>
        <a class="btn-login" href="<?= BASE_URL ?>/login.php">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main>
    <div class="container hero-wrap">
      <section class="hero">
        <div>
          <h1><?= e($heroTitle) ?></h1>
          <p><?= e($heroDesc) ?></p>
          <a href="<?= BASE_URL ?>/shop.php" class="btn-dark">Shop Now</a>
        </div>
        <div class="hero-image">
          <img src="<?= $heroHasImg ? BASE_URL . '/hero-image.php' : BASE_URL . '/assets/images/model.png' ?>" alt="Hero">
        </div>
      </section>
    </div>

    <section>
      <div class="container">
        <h2 class="section-title">NEW ARRIVALS</h2>
        <div class="products">
          <?php foreach ($newArrivals as $p): ?>
          <a class="card-link" href="<?= BASE_URL ?>/product.php?id=<?= (int)$p['id'] ?>">
            <article class="card">
              <img class="card-img" src="<?= e(image_url(isset($p['image_id']) ? (int)$p['image_id'] : 0, fallback_image_url())) ?>" alt="<?= e($p['name']) ?>">
              <div class="card-body">
                <h3 class="card-title"><?= e($p['name']) ?><?php if (!empty($p['is_sold'])): ?> <span class="badge-sold">SOLD</span><?php elseif (!empty($p['is_sale'])): ?> <span class="badge-sale">SALE</span><?php endif; ?></h3>
                <div class="price">
                  <span class="new">₱<?= number_format((float)$p['price'], 0) ?></span>
                  <?php if (!empty($p['old_price'])): ?><span class="old">₱<?= number_format((float)$p['old_price'], 0) ?></span><?php endif; ?>
                </div>
              </div>
            </article>
          </a>
          <?php endforeach; ?>
          <?php if (empty($newArrivals)): ?>
          <p class="text-muted">No products yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <footer>
    <div class="container footer-simple">
      <h5><?= e(APP_NAME) ?></h5>
      <p>We handpick stylish thrift finds that look premium without the premium price.</p>
      <div class="socials">
        <a href="https://www.instagram.com/threapgrailz?igsh=emJzM2Q1MDJtNHBi" target="_blank" rel="noopener" class="social-icon" title="Instagram">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z"/>
          </svg>
        </a>
        <a href="https://www.facebook.com/share/1CJv2xzVTq/" target="_blank" rel="noopener" class="social-icon" title="Facebook">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
            <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/>
          </svg>
        </a>
      </div>
    </div>
  </footer>

  <script>
  (function(){
    const close = document.getElementById('promoClose');
    const bar   = document.getElementById('promoBar');
    if (close && bar) {
      close.addEventListener('click', () => { bar.style.display='none'; localStorage.setItem('promoDismissed','1'); });
    }
    if (bar && localStorage.getItem('promoDismissed')==='1') bar.style.display='none';
  })();
  </script>
</body>
</html>
