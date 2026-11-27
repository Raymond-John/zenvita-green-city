<?php
require_once __DIR__ . '/api/bootstrap.php';
$pdo = db();
ensure_product_landing_columns($pdo);

$landingProducts = $pdo->query(
    "SELECT name, description, image_path, features, price
     FROM products
     WHERE status = 'active' AND show_on_landing = 1
     ORDER BY sort_order ASC, id ASC"
)->fetchAll();

$landingFeatures = $pdo->query(
    "SELECT title
     FROM landing_features
     WHERE status = 'active'
     ORDER BY sort_order ASC, id ASC"
)->fetchAll(PDO::FETCH_COLUMN);

$minPrice = null;
foreach ($landingProducts as $row) {
    $price = (float) $row['price'];
    if ($minPrice === null || $price < $minPrice) {
        $minPrice = $price;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Cellvigor Biorevive LTD</title>
  <meta name="description" content="Cellvigor Biorevive LTD — herbal wellness products, member registration, product orders, network growth, and Eagle land and Homes LTD reserves. Register to get started.">
  <link rel="icon" href="assets/images/cellvigor-logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body class="landing-page">
  <a class="landing-skip" href="#main">Skip to content</a>

  <header class="landing-header" id="landingHeader">
    <a class="landing-brand" href="index" aria-label="Cellvigor Biorevive LTD home">
      <img src="assets/images/cellvigor-logo.png" alt="">
      <span>
        <strong>CELLVIGOR</strong>
        <small>BIOREVIVE LTD</small>
      </span>
    </a>
    <nav class="landing-nav" aria-label="Primary">
      <a href="#about">About</a>
      <a href="#products">Products</a>
      <a href="#how-it-works">How it works</a>
      <a href="#membership">Membership</a>
      <a class="landing-link-login" href="login">Member login</a>
      <a class="landing-btn landing-btn-solid" href="register">Register</a>
    </nav>
  </header>

  <main id="main">
    <section class="landing-hero" aria-label="Cellvigor introduction">
      <div class="landing-video-bg" aria-hidden="true">
        <video
          class="landing-video"
          autoplay
          muted
          loop
          playsinline
          preload="auto"
          poster="assets/images/cellvigor-bottle.png"
        >
          <source src="assets/videos/cellvigor-hero.mp4" type="video/mp4">
        </video>
      </div>
      <div class="landing-hero-copy">
        <span class="landing-kicker">Biorevive Capsules</span>
        <span class="brand-mark">Cellvigor</span>
        <h1>Herbal medicine formulated for everyday vitality.</h1>
        <p>Revitalize · Rebuild · Thrive — 100% natural support for immunity, energy, and cellular wellness.</p>
        <div class="landing-cta-row">
          <a class="landing-btn landing-btn-solid" href="register">Register to begin</a>
          <a class="landing-btn landing-btn-ghost" href="#about">Explore the platform</a>
        </div>
      </div>
    </section>

    <section class="landing-section landing-about" id="about" aria-labelledby="about-title">
      <div class="landing-section-inner">
        <span class="landing-kicker">The company</span>
        <h2 id="about-title">What Cellvigor Biorevive LTD is</h2>
        <p class="landing-lead">Cellvigor Biorevive LTD is a wellness and member platform built around natural herbal products. Members register, order products through approved stores, track earnings, grow a binary network, and take part in the Eagle land and Homes LTD reserve.</p>
        <div class="landing-split">
          <div>
            <h3>For your health</h3>
            <p>Our capsules are positioned as everyday herbal support — helping you revitalize energy, rebuild balance, and support cellular wellness.</p>
          </div>
          <div>
            <h3>For your membership</h3>
            <p>Once registered, you get a member dashboard for packages, product orders, commissions, withdrawals, network growth, and your Eagle land and Homes LTD reserve balance.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="landing-section landing-benefits" id="benefits" aria-labelledby="benefits-title">
      <div class="landing-section-inner">
        <span class="landing-kicker">Formula focus</span>
        <h2 id="benefits-title">Why people choose Cellvigor</h2>
        <p class="landing-lead">The Biorevive formula is built around clear wellness goals that match the product label.</p>
        <?php if ($landingFeatures): ?>
          <ul class="landing-benefit-list">
            <?php foreach ($landingFeatures as $featureTitle): ?>
              <li><?= htmlspecialchars((string) $featureTitle) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="landing-lead">Features will appear here once added from the admin panel.</p>
        <?php endif; ?>
      </div>
    </section>

    <section class="landing-section landing-products" id="products" aria-labelledby="products-title">
      <div class="landing-section-inner">
        <span class="landing-kicker">Product line</span>
        <h2 id="products-title">Our products</h2>
        <p class="landing-lead">
          Members can order through the platform after registration.
          <?php if ($minPrice !== null): ?>
            Current products start from ₦<?= number_format($minPrice, 0) ?>.
          <?php endif; ?>
        </p>
        <div class="landing-product-grid">
          <?php if (!$landingProducts): ?>
            <article class="landing-product">
              <h3>Products coming soon</h3>
              <p>Admin can add products from the Products page.</p>
            </article>
          <?php endif; ?>
          <?php foreach ($landingProducts as $product): ?>
            <?php
              $productFeatures = parse_feature_list($product['features'] ?? '[]');
              $desc = trim((string) ($product['description'] ?? ''));
            ?>
            <article class="landing-product">
              <?php
                $imageUrl = product_image_public_url($product['image_path'] ?? '');
              ?>
              <?php if ($imageUrl !== ''): ?>
                <div class="landing-product-media">
                  <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                </div>
              <?php endif; ?>
              <h3><?= htmlspecialchars($product['name']) ?></h3>
              <?php if ($desc !== ''): ?>
                <p><?= htmlspecialchars($desc) ?></p>
              <?php endif; ?>
              <?php if ($productFeatures): ?>
                <ul class="landing-product-features">
                  <?php foreach ($productFeatures as $feature): ?>
                    <li><?= htmlspecialchars($feature) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
              <div class="landing-price">₦<?= number_format((float) $product['price'], 0) ?></div>
              <div class="landing-cta-row">
                <a class="landing-btn landing-btn-brand" href="register">Register to order</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="landing-section landing-how" id="how-it-works" aria-labelledby="how-title">
      <div class="landing-section-inner">
        <span class="landing-kicker">Platform flow</span>
        <h2 id="how-title">How the platform works</h2>
        <p class="landing-lead">From first registration to product delivery and earnings, everything runs inside your member account.</p>
        <ol class="landing-steps">
          <li>
            <strong>Register</strong>
            <span>Create your member account with a sponsor referral code and choose your package.</span>
          </li>
          <li>
            <strong>Order products</strong>
            <span>Book Cellvigor or Vigormax from your dashboard, pick a store, and choose home delivery or pickup.</span>
          </li>
          <li>
            <strong>Grow your network</strong>
            <span>Invite people into your left and right teams, track PV, and earn commissions from the binary structure.</span>
          </li>
          <li>
            <strong>Withdraw on Saturdays</strong>
            <span>Cash withdrawals open on Saturdays only. You can withdraw up to 40% of your wallet balance.</span>
          </li>
          <li>
            <strong>Build Eagle land and Homes LTD</strong>
            <span>The matching 60% is reserved into Eagle land and Homes LTD — a dedicated section in your account.</span>
          </li>
        </ol>
      </div>
    </section>

    <section class="landing-section landing-membership" id="membership" aria-labelledby="membership-title">
      <div class="landing-section-inner">
        <span class="landing-kicker">Member access</span>
        <h2 id="membership-title">What you get as a member</h2>
        <p class="landing-lead">Your dashboard is the control center for products, earnings, and growth.</p>
        <div class="landing-feature-grid">
          <div>
            <h3>Member dashboard</h3>
            <p>See wallet balance, package status, team PV, and recent activity in one place.</p>
          </div>
          <div>
            <h3>Product ordering</h3>
            <p>Place bookings with store owners for delivery or pickup and track each order status.</p>
          </div>
          <div>
            <h3>Commissions &amp; network</h3>
            <p>Follow direct, indirect, pairing, and upgrade earnings while your binary tree expands.</p>
          </div>
          <div>
            <h3>Eagle land and Homes LTD</h3>
            <p>View the reserved 60% balance set aside whenever you process a Saturday withdrawal.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="landing-section landing-promise" aria-labelledby="promise-title">
      <div class="landing-section-inner">
        <span class="landing-kicker">Begin today</span>
        <h2 id="promise-title">Ready to join Cellvigor Biorevive LTD?</h2>
        <p>Register now to create your account, access the member dashboard, order products, and start building with the network.</p>
        <p class="landing-tagline">Connect · Grow · Thrive</p>
        <div class="landing-cta-row">
          <a class="landing-btn landing-btn-solid" href="register">Go to registration</a>
          <a class="landing-btn landing-btn-ghost" href="login">Member login</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="landing-footer">
    <span>© <?= date('Y') ?> Cellvigor Biorevive LTD</span>
    <span>
      <a href="#about">About</a>
      ·
      <a href="#products">Products</a>
      ·
      <a href="register">Register</a>
      ·
      <a href="login">Login</a>
    </span>
  </footer>

  <script>
    (function () {
      var header = document.getElementById("landingHeader");
      if (!header) return;
      function sync() {
        header.classList.toggle("is-scrolled", window.scrollY > 24);
      }
      sync();
      window.addEventListener("scroll", sync, { passive: true });
    })();
  </script>
</body>
</html>
