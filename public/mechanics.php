<?php
require_once __DIR__ . '/../src/layout.php';

$make    = trim($_GET['make'] ?? '');
$county  = trim($_GET['county'] ?? '');
$listing = (int)($_GET['listing'] ?? 0);   // carried through to booking

$sql = "SELECT mp.*, u.name,
               (SELECT ROUND(AVG(rating),1) FROM mechanic_reviews r WHERE r.mechanic_id = mp.id) AS avg_rating,
               (SELECT COUNT(*) FROM mechanic_reviews r WHERE r.mechanic_id = mp.id) AS review_count
          FROM mechanic_profiles mp
          JOIN users u ON u.id = mp.user_id
         WHERE mp.verified = 1";
$params = [];
if ($make !== '') {
    $sql .= ' AND mp.id IN (SELECT mechanic_id FROM mechanic_specialties WHERE make = ?)';
    $params[] = $make;
}
if ($county !== '') {
    $sql .= ' AND mp.base_county = ?';
    $params[] = $county;
}
$sql .= ' ORDER BY avg_rating DESC NULLS LAST, review_count DESC';
$mechs = db_all($sql, $params);

$allMakes = db_all('SELECT DISTINCT make FROM mechanic_specialties ORDER BY make');

page_header('Hire a mechanic', 'mechanics');
?>
<div class="wrap section-tight">
  <h1>Hire a mechanic for the viewing</h1>
  <p class="muted" style="max-width:64ch">Every review here names the car it's about, and what the mechanic
    actually caught. A five-star rating on hybrids tells you nothing about timing chains — so we don't blend them.</p>

  <form class="filter-bar" method="get">
    <?php if ($listing): ?><input type="hidden" name="listing" value="<?= $listing ?>"><?php endif; ?>
    <div class="field">
      <label for="m-make">Speciality (make)</label>
      <select id="m-make" name="make">
        <option value="">Any make</option>
        <?php foreach ($allMakes as $m): ?>
          <option <?= $make === $m['make'] ? 'selected' : '' ?>><?= e($m['make']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="m-county">Based in</label>
      <select id="m-county" name="county">
        <option value="">Anywhere</option>
        <?php foreach (COUNTIES as $c): ?>
          <option <?= $county === $c ? 'selected' : '' ?>><?= $c ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-outline" href="mechanics.php<?= $listing ? '?listing=' . $listing : '' ?>">Reset</a>
  </form>

  <?php if ($make): ?>
    <p class="muted small">Showing mechanics with a declared <strong><?= e($make) ?></strong> speciality.</p>
  <?php endif; ?>

  <?php if (!$mechs): ?>
    <div class="panel">
      <h3>No mechanics match yet</h3>
      <p class="muted">Try widening the filters. Mechanic profiles appear here only after verification.</p>
    </div>
  <?php else: ?>
    <div class="grid-2">
      <?php foreach ($mechs as $m):
          $specs = db_all('SELECT * FROM mechanic_specialties WHERE mechanic_id = ?', [$m['id']]);
      ?>
        <a class="card" href="mechanic.php?id=<?= $m['id'] ?><?= $listing ? '&listing=' . $listing : '' ?>">
          <div class="card-body mech-card">
            <div class="mech-head">
              <div class="avatar"><?= e(strtoupper(substr($m['name'], 0, 1))) ?></div>
              <div>
                <h3 style="margin:0"><?= e($m['name']) ?>
                  <?php if ($m['verified']): ?><span class="badge badge-verified">ID verified</span><?php endif; ?>
                </h3>
                <p class="muted small" style="margin:0"><?= e($m['headline'] ?: 'General inspections') ?></p>
              </div>
            </div>
            <div class="tags">
              <?php foreach ($specs as $s): ?><span class="tag"><?= e($s['make']) ?></span><?php endforeach; ?>
            </div>
            <div class="card-meta">
              <?php if ($m['avg_rating']): ?>
                <span><?= stars((float)$m['avg_rating']) ?> <?= e((string)$m['avg_rating']) ?> (<?= $m['review_count'] ?> review<?= $m['review_count'] == 1 ? '' : 's' ?>)</span>
              <?php else: ?>
                <span class="muted">No reviews yet</span>
              <?php endif; ?>
              <span><?= e($m['base_county'] ?: '—') ?></span>
              <span>Callout <?= price_eur((int)$m['callout_fee_eur']) ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
