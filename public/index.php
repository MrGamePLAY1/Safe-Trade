<?php
require_once __DIR__ . '/../src/layout.php';

// Featured car = live listing with the most core documents present.
$featured = db_row(
    "SELECT l.*, COUNT(DISTINCT d.doc_type) AS doc_kinds
       FROM listings l
       LEFT JOIN documents d ON d.listing_id = l.id AND d.doc_type != 'other' AND d.file_path IS NOT NULL
      WHERE l.status = 'live'
      GROUP BY l.id
      ORDER BY doc_kinds DESC, l.created_at DESC
      LIMIT 1"
);

$latest = db_all(
    "SELECT * FROM listings WHERE status = 'live' ORDER BY created_at DESC LIMIT 6"
);

$mechs = db_all(
    "SELECT mp.*, u.name,
            (SELECT ROUND(AVG(rating),1) FROM mechanic_reviews r WHERE r.mechanic_id = mp.id) AS avg_rating,
            (SELECT COUNT(*) FROM mechanic_reviews r WHERE r.mechanic_id = mp.id) AS review_count
       FROM mechanic_profiles mp
       JOIN users u ON u.id = mp.user_id
      WHERE mp.verified = 1
      ORDER BY avg_rating DESC
      LIMIT 2"
);

$makeModels = live_make_models();

page_header('Buy and sell cars with the full paperwork', 'home');
?>
<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <p class="eyebrow">Private sales · Vehicle paperwork · Ireland</p>
      <h1>Buy your car <br>Safely.</h1>
      <p class="lede">Every listing on Safe Trade carries car documents: NCT cert, service history,
        finance clearance, the lot. Also hire a specialist mechanic to check it before you hand over a cent.</p>

      <form class="hero-search" action="listings.php" method="get">
        <div class="field">
          <label for="hs-make">Make</label>
          <select id="hs-make" name="make" data-make-select="hs-model">
            <option value="">Any make</option>
            <?php foreach ($makeModels as $mk => $models): ?><option><?= e($mk) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="hs-model">Model</label>
          <select id="hs-model" name="model"
                  data-models='<?= e(json_encode($makeModels, JSON_UNESCAPED_UNICODE)) ?>'>
            <option value="">Any model</option>
          </select>
        </div>
        <div class="field">
          <label for="hs-county">County</label>
          <select id="hs-county" name="county">
            <option value="">Anywhere</option>
            <?php foreach (COUNTIES as $c): ?><option><?= $c ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="hs-max">Max price €</label>
          <input type="number" id="hs-max" name="max_price" min="0" step="500" placeholder="Any">
        </div>
        <button class="btn btn-primary" type="submit" style="align-self:end">Search</button>
      </form>
    </div>

    <?php if ($featured): $dos = documents(listing_docs((int)$featured['id'])); ?>
    <a class="documents-card" href="listing.php?id=<?= $featured['id'] ?>" style="display:block;color:inherit;text-decoration:none">
      <div class="dc-head">
        <span>Vehicle documents</span>
        <span><?= $dos['present'] ?>/<?= $dos['total'] ?> documents</span>
      </div>
      <?= car_thumb($featured) ?>
      <div class="dc-body">
        <h3><?= e($featured['year'] . ' ' . $featured['make'] . ' ' . $featured['model']) ?></h3>
        <p class="muted small" style="margin-bottom:10px">
          <?= km($featured['mileage_km']) ?> · <?= e($featured['fuel']) ?> · <?= e($featured['county']) ?>
        </p>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
          <?= reg_plate($featured['reg']) ?>
          <span class="price"><?= price_eur((int)$featured['price_eur']) ?></span>
        </div>
        <div style="margin-top:12px"><?= documents_meter($dos, true) ?></div>
      </div>
    </a>
    <?php endif; ?>
  </div>
</section>

<section class="section" style="background:#fff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)">
  <div class="wrap">
    <div class="grid-3">
      <div class="how-step">
        <p class="k">List</p>
        <h3>Add vehicle documents</h3>
        <p class="muted">Attach the NCT cert, service history and finance clearance to your listing.
          A complete file answers the questions buyers were going to ask anyway.</p>
      </div>
      <div class="how-step">
        <p class="k">Verify</p>
        <h3>Bring a specialist</h3>
        <p class="muted">Buyers hire a mechanic who knows that exact make — reviews are tied to
          the cars they've inspected, not a blended star rating.</p>
      </div>
      <div class="how-step">
        <p class="k">Sell</p>
        <h3>Private or trade</h3>
        <p class="muted">Sell privately with safety tools built in, or open your car to a
          dealer-only auction and let the trade bid it up.</p>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <h2>Latest cars</h2>
      <a href="listings.php">Browse all →</a>
    </div>
    <div class="grid-3">
      <?php foreach ($latest as $l): $dos = documents(listing_docs((int)$l['id'])); ?>
        <a class="card" href="listing.php?id=<?= $l['id'] ?>">
          <?= car_thumb($l) ?>
          <div class="card-body">
            <div class="card-title">
              <h3><?= e($l['year'] . ' ' . $l['make'] . ' ' . $l['model']) ?></h3>
              <span class="price"><?= price_eur((int)$l['price_eur']) ?></span>
            </div>
            <div class="card-meta">
              <span><?= km($l['mileage_km']) ?></span>
              <span><?= e($l['fuel']) ?></span>
              <span><?= e($l['county']) ?></span>
            </div>
            <div class="card-foot">
              <?= reg_plate($l['reg']) ?>
              <?= documents_meter($dos) ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="wrap">
    <div class="section-head">
      <h2>Mechanics for hire</h2>
      <a href="mechanics.php">See all mechanics →</a>
    </div>
    <div class="grid-2">
      <?php foreach ($mechs as $m): ?>
        <a class="card" href="mechanic.php?id=<?= $m['id'] ?>">
          <div class="card-body mech-card">
            <div class="mech-head">
              <div class="avatar"><?= e(strtoupper(substr($m['name'], 0, 1))) ?></div>
              <div>
                <h3 style="margin:0"><?= e($m['name']) ?></h3>
                <p class="muted small" style="margin:0"><?= e($m['headline']) ?></p>
              </div>
            </div>
            <div class="card-meta">
              <?php if ($m['avg_rating']): ?>
                <span><?= stars((float)$m['avg_rating']) ?> <?= e((string)$m['avg_rating']) ?> (<?= $m['review_count'] ?>)</span>
              <?php endif; ?>
              <span><?= e($m['base_county']) ?></span>
              <span>Callout <?= price_eur((int)$m['callout_fee_eur']) ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="panel" style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap">
      <div>
        <h3 style="margin:0 0 4px">Meeting a stranger with cash and keys?</h3>
        <p class="muted" style="margin:0">Use the safety centre: viewing checklist, safe-meeting guidance and a check-in timer that keeps someone you trust in the loop.</p>
      </div>
      <a class="btn btn-outline" href="safety.php">Open safety centre</a>
    </div>
  </div>
</section>
<?php page_footer(); ?>
