<?php
/**
 * Safe Trade — shared layout. Call page_header() / page_footer() from every page.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

function page_header(string $title, string $active = ''): void
{
    $u = current_user();
    $nav = [
        'listings'  => ['listings.php',  'Buy'],
        'sell'      => ['sell.php',      'Sell'],
        'mechanics' => ['mechanics.php', 'Mechanics'],
        'auctions'  => ['auctions.php',  'Dealer auctions'],
        'safety'    => ['safety.php',    'Safety'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> · Safe Trade</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-head">
  <div class="wrap head-row">
    <a class="brand" href="index.php">
      <span class="plate brand-plate"><span class="plate-band">IRL</span><span class="plate-no">SAFE·TRADE</span></span>
    </a>
    <nav class="main-nav" aria-label="Main">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= $href ?>" <?= $key === $active ? 'class="active"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="head-auth">
      <?php if ($u): ?>
        <a href="dashboard.php" class="btn btn-ghost-light <?= $active === 'dashboard' ? 'active' : '' ?>"><?= e(explode(' ', $u['name'])[0]) ?></a>
        <a href="logout.php" class="head-link">Sign out</a>
      <?php else: ?>
        <a href="login.php" class="head-link">Sign in</a>
        <a href="register.php" class="btn btn-primary btn-sm">Create account</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?= render_flashes() ?>
<main>
    <?php
}

function page_footer(): void
{
    ?>
</main>
<footer class="site-foot">
  <div class="wrap foot-grid">
    <div>
      <span class="plate plate-sm"><span class="plate-band">IRL</span><span class="plate-no">SAFE·TRADE</span></span>
      <!-- <p class="foot-tag">The full file on every car.</p> -->
    </div>
    <div>
      <h4>Marketplace</h4>
      <a href="listings.php">Browse cars</a>
      <a href="sell.php">Sell your car</a>
      <a href="auctions.php">Dealer auctions</a>
    </div>
    <div>
      <h4>Trust</h4>
      <a href="mechanics.php">Hire a mechanic</a>
      <a href="safety.php">Buyer safety</a>
    </div>
    <div>
      <h4>Build</h4>
      <p class="foot-note">Development build — see README.md for the roadmap. Placeholder brand; rename freely.</p>
    </div>
  </div>
</footer>
<script src="assets/js/app.js"></script>
</body>
</html>
    <?php
}
