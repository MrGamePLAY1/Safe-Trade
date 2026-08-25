<?php
require_once __DIR__ . '/../src/layout.php';

$make     = trim($_GET['make'] ?? '');
$county   = trim($_GET['county'] ?? '');
$maxPrice = (int)($_GET['max_price'] ?? 0);
$sort     = $_GET['sort'] ?? 'newest';

$where  = ["status = 'live'"];
$params = [];
if ($make !== '')   { $where[] = 'make = ?';        $params[] = $make; }
if ($county !== '') { $where[] = 'county = ?';      $params[] = $county; }
if ($maxPrice > 0)  { $where[] = 'price_eur <= ?';  $params[] = $maxPrice; }

$orderBy = match ($sort) {
    'price_low'  => 'price_eur ASC',
    'price_high' => 'price_eur DESC',
    'mileage'    => 'mileage_km ASC',
    default      => 'created_at DESC',
};

$listings = db_all(
    'SELECT * FROM listings WHERE ' . implode(' AND ', $where) . " ORDER BY $orderBy",
    $params
);
$makes = db_all("SELECT DISTINCT make FROM listings WHERE status='live' ORDER BY make");

page_header('Browse cars', 'listings');
?>
<div class="wrap section-tight">
  <h1>Browse cars</h1>

  <form class="filter-bar" method="get">
    <div class="field">
      <label for="f-make">Make</label>
      <select id="f-make" name="make">
        <option value="">Any make</option>
        <?php foreach ($makes as $m): ?>
          <option <?= $make === $m['make'] ? 'selected' : '' ?>><?= e($m['make']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="f-county">County</label>
      <select id="f-county" name="county">
        <option value="">Anywhere</option>
        <?php foreach (COUNTIES as $c): ?>
          <option <?= $county === $c ? 'selected' : '' ?>><?= $c ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field field-sm">
      <label for="f-max">Max price €</label>
      <input type="number" id="f-max" name="max_price" min="0" step="500"
             value="<?= $maxPrice ?: '' ?>" placeholder="Any">
    </div>
    <div class="field field-sm">
      <label for="f-sort">Sort</label>
      <select id="f-sort" name="sort">
        <option value="newest"     <?= $sort==='newest'     ? 'selected':'' ?>>Newest</option>
        <option value="price_low"  <?= $sort==='price_low'  ? 'selected':'' ?>>Price: low → high</option>
        <option value="price_high" <?= $sort==='price_high' ? 'selected':'' ?>>Price: high → low</option>
        <option value="mileage"    <?= $sort==='mileage'    ? 'selected':'' ?>>Lowest mileage</option>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-outline" href="listings.php">Reset</a>
  </form>

  <?php if (!$listings): ?>
    <div class="panel">
      <h3>No cars match those filters</h3>
      <p class="muted">Widen the search, or <a href="sell.php">be the first to list one</a>.</p>
    </div>
  <?php else: ?>
    <p class="muted small"><?= count($listings) ?> car<?= count($listings) === 1 ? '' : 's' ?> · dossier meter shows core documents attached</p>
    <div class="grid-3">
      <?php foreach ($listings as $l): $dos = dossier(listing_docs((int)$l['id'])); ?>
        <a class="card" href="listing.php?id=<?= $l['id'] ?>">
          <?= car_thumb($l) ?>
          <div class="card-body">
            <div class="card-title">
              <h3><?= e($l['year'] . ' ' . $l['make'] . ' ' . $l['model']) ?></h3>
              <span class="price"><?= price_eur((int)$l['price_eur']) ?></span>
            </div>
            <div class="card-meta">
              <span><?= km((int)$l['mileage_km']) ?></span>
              <span><?= e($l['fuel']) ?></span>
              <span><?= e($l['transmission']) ?></span>
              <span><?= e($l['county']) ?></span>
            </div>
            <div class="card-foot">
              <?= reg_plate($l['reg']) ?>
              <?= dossier_meter($dos) ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
