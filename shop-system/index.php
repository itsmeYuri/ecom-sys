<?php
require_once __DIR__ . '/includes/functions.php';

$newArrivals = db()->query("SELECT p.*, COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.product_id = p.id), 0) AS rating, (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_main DESC, id ASC LIMIT 1) AS image_path FROM products p ORDER BY p.id ASC LIMIT 4")->fetchAll();
$topSelling = db()->query("SELECT p.*, COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.product_id = p.id), 0) AS rating, (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_main DESC, id ASC LIMIT 1) AS image_path FROM products p ORDER BY p.id DESC LIMIT 4")->fetchAll();

function index_stars(float $rating): string {
    $full = (int)round($rating);
    $full = max(0, min(5, $full));
    return str_repeat('?', $full) . str_repeat('?', 5 - $full);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Threap Glailz</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/index-reference.css" />
</head>
<body>
  <header class="navbar">
    <div class="container nav-inner">
      <div class="logo">
        <span>Threap Glailz</span>
      </div>
      <nav class="nav-links">
        <a href="<?= BASE_URL ?>/shop.php">New In</a>
        <a href="<?= BASE_URL ?>/shop.php?category=1">T-Shirt</a>
        <a href="<?= BASE_URL ?>/shop.php?category=4">Jorts</a>
        <a href="<?= BASE_URL ?>/shop.php">footWare</a>
        <a href="<?= BASE_URL ?>/shop.php?sort=popular">Sale</a>
      </nav>
      <div class="nav-actions" aria-label="Search and cart actions">
        <a class="icon-btn" href="<?= BASE_URL ?>/shop.php">search logo</a>
        <a class="icon-btn" href="<?= BASE_URL ?>/cart.php">cart logo</a>
      </div>
    </div>
  </header>

  <main>
    <div class="container hero-wrap">
      <section class="hero">
        <div>
          <h1>FIND CLOTHES THAT MATCHES YOUR STYLE</h1>
          <p>Browse unique vintage finds and budget-friendly treasures made for everyday confidence. Discover one-of-a-kind pieces, timeless classics, and hidden gems curated to elevate your personal look — sustainably and affordably.</p>
          <a href="<?= BASE_URL ?>/shop.php" class="btn-dark">Shop Now</a>
        </div>
        <div class="hero-image">
          <img src="<?= BASE_URL ?>/assets/images/model.png" alt="Fashion model couple wearing modern outfits" />
        </div>
      </section>
    </div>

    <div class="brand-strip">
      <div class="container brand-row">
        <span>Champion</span>
        <span>Levi’s</span>
        <span>Nike</span>
        <span>Adidas</span>
        <span>Tommy Hilfiger</span>
      </div>
    </div>

    <section class="stats">
      <div class="container stats-grid">
        <div class="stats-item"><strong>200+</strong><span>International Brands</span></div>
        <div class="stats-item"><strong>2,000+</strong><span>High-Quality Products</span></div>
        <div class="stats-item"><strong>30,000+</strong><span>Happy Customers</span></div>
      </div>
    </section>

    <section>
      <div class="container">
        <h2 class="section-title">NEW ARRIVALS</h2>
        <div class="products">
          <?php foreach ($newArrivals as $product): ?>
            <a class="card-link" href="<?= BASE_URL ?>/product.php?id=<?= (int)$product['id'] ?>">
              <article class="card">
                <img class="card-img" src="<?= e($product['image_path'] ?: (BASE_URL . '/assets/images/model1.png')) ?>" alt="<?= e($product['name']) ?>" />
                <div class="card-body">
                  <h3 class="card-title"><?= e($product['name']) ?></h3>
                  <div class="rating"><?= e(index_stars((float)$product['rating'])) ?> <?= e((string)$product['rating']) ?></div>
                  <div class="price"><span class="new">$<?= number_format((float)$product['price'], 0) ?></span><?php if (!empty($product['old_price'])): ?><span class="old">$<?= number_format((float)$product['old_price'], 0) ?></span><?php endif; ?></div>
                </div>
              </article>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section>
      <div class="container">
        <h2 class="section-title">TOP SELLING</h2>
        <div class="products">
          <?php foreach ($topSelling as $product): ?>
            <a class="card-link" href="<?= BASE_URL ?>/product.php?id=<?= (int)$product['id'] ?>">
              <article class="card">
                <img class="card-img" src="<?= e($product['image_path'] ?: (BASE_URL . '/assets/images/model1.png')) ?>" alt="<?= e($product['name']) ?>" />
                <div class="card-body">
                  <h3 class="card-title"><?= e($product['name']) ?></h3>
                  <div class="rating"><?= e(index_stars((float)$product['rating'])) ?> <?= e((string)$product['rating']) ?></div>
                  <div class="price"><span class="new">$<?= number_format((float)$product['price'], 0) ?></span><?php if (!empty($product['old_price'])): ?><span class="old">$<?= number_format((float)$product['old_price'], 0) ?></span><?php endif; ?></div>
                </div>
              </article>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section>
      <div class="container styles-wrap">
        <h2 class="section-title">BROWSE BY DRESS STYLE</h2>
        <div class="styles-grid">
          <a class="style-card" href="<?= BASE_URL ?>/shop.php"><img src="<?= BASE_URL ?>/assets/images/model1.png" alt="CASUAL" /><span>CASUAL</span></a>
          <a class="style-card" href="<?= BASE_URL ?>/shop.php"><img src="<?= BASE_URL ?>/assets/images/model.png" alt="FORMAL" /><span>FORMAL</span></a>
          <a class="style-card" href="<?= BASE_URL ?>/shop.php"><img src="<?= BASE_URL ?>/assets/images/model1.png" alt="PARTY" /><span>PARTY</span></a>
          <a class="style-card" href="<?= BASE_URL ?>/shop.php"><img src="<?= BASE_URL ?>/assets/images/model.png" alt="GYM" /><span>GYM</span></a>
        </div>
      </div>
    </section>

    <section class="newsletter">
      <div class="container newsletter-box">
        <h3>STAY UP TO DATE ABOUT OUR<br>LATEST OFFERS</h3>
        <form class="newsletter-form" onsubmit="event.preventDefault()">
          <input type="email" placeholder="Enter your email address" aria-label="Email address" />
          <button type="submit">Subscribe</button>
        </form>
      </div>
    </section>
  </main>

  <footer>
    <div class="container footer-grid">
      <div class="footer-col">
        <h5>Threap Glailz</h5>
        <p>We handpick stylish thrift finds that look premium without the premium price.</p>
        <div class="socials" aria-label="Social media links">
          <span>in</span>
          <span>ig</span>
          <span>fb</span>
          <span>x</span>
        </div>
      </div>
      <div class="footer-col">
        <h5>Company</h5>
        <a href="#">About</a>
        <a href="#">Features</a>
        <a href="#">Works</a>
        <a href="#">Career</a>
      </div>
      <div class="footer-col">
        <h5>Help</h5>
        <a href="#">Customer Support</a>
        <a href="#">Delivery Details</a>
        <a href="#">Terms & Conditions</a>
        <a href="#">Privacy Policy</a>
      </div>
      <div class="footer-col">
        <h5>FAQ</h5>
        <a href="#">Account</a>
        <a href="#">Manage Deliveries</a>
        <a href="#">Orders</a>
        <a href="#">Payments</a>
      </div>
      <div class="footer-col">
        <h5>Resources</h5>
        <a href="#">Free eBooks</a>
        <a href="#">Development Tutorial</a>
        <a href="#">How to Blog</a>
        <a href="#">YouTube Playlist</a>
      </div>
    </div>
  </footer>
</body>
</html>


