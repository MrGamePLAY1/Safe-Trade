<?php
require_once __DIR__ . '/../src/layout.php';

$id = (int)($_GET['id'] ?? 0);
$l  = db_row(
    'SELECT l.*, u.name AS seller_name, u.county AS seller_county, u.created_at AS seller_since
       FROM listings l JOIN users u ON u.id = l.user_id
      WHERE l.id = ?', [$id]
);
if (!$l) {
    http_response_code(404);
    page_header('Not found');
    echo '<div class="wrap section"><div class="panel"><h1>Listing not found</h1><p><a href="listings.php">Back to all cars</a></p></div></div>';
    page_footer();
    exit;
}

$docs    = listing_docs($id);
$dos     = documents($docs);
$u       = current_user();
$isOwner = $u && (int)$u['id'] === (int)$l['user_id'];
$auction = db_row('SELECT * FROM auctions WHERE listing_id = ?', [$id]);
$otherDocs = array_filter($docs, fn($d) => $d['doc_type'] === 'other');

page_header($l['year'] . ' ' . $l['make'] . ' ' . $l['model'], 'listings');
?>
<div class="wrap section-tight">
  <p class="small"><a href="listings.php">← All cars</a></p>

  <?php if ($auction && $auction['status'] === 'open'): ?>
    <div class="premium-note" style="margin-bottom:18px">
      <strong>This car is in a live dealer auction.</strong>
      Trade bidding ends <span class="countdown" data-ends-at="<?= e($auction['ends_at']) ?>"></span> —
      <a href="auction.php?id=<?= $auction['id'] ?>">view the auction</a>.
    </div>
  <?php endif; ?>

  <div class="detail-grid">
    <div>
      <?= car_thumb($l, 'lg') ?>

      <div class="panel" style="margin-top:18px">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:14px;flex-wrap:wrap">
          <div>
            <h1 style="margin-bottom:6px"><?= e($l['year'] . ' ' . $l['make'] . ' ' . $l['model']) ?></h1>
            <?= reg_plate($l['reg']) ?>
          </div>
          <div style="text-align:right">
            <div class="price" style="font-size:1.6rem"><?= price_eur((int)$l['price_eur']) ?></div>
            <?= status_badge($l['status']) ?>
          </div>
        </div>

        <table class="spec-table" style="margin-top:16px">
          <tr><th>Mileage</th><td><?= km((int)$l['mileage_km']) ?></td></tr>
          <tr><th>Fuel</th><td><?= e($l['fuel'] ?: '—') ?></td></tr>
          <tr><th>Transmission</th><td><?= e($l['transmission'] ?: '—') ?></td></tr>
          <tr><th>Colour</th><td><?= e($l['colour'] ?: '—') ?></td></tr>
          <tr><th>County</th><td><?= e($l['county'] ?: '—') ?></td></tr>
          <tr><th>VIN</th><td><?= e($l['vin'] ?: 'Not provided') ?></td></tr>
          <tr><th>Listed</th><td><?= date_short($l['created_at']) ?></td></tr>
        </table>
      </div>

      <?php if ($l['description']): ?>
        <div class="panel">
          <h3>Seller's description</h3>
          <p style="margin:0;white-space:pre-line"><?= e($l['description']) ?></p>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px">
          <h2 style="margin:0">Vehicle documents</h2>
          <span class="mono small"><?= $dos['present'] ?>/<?= $dos['total'] ?></span>
        </div>
        <div style="margin:12px 0 16px"><?= documents_meter($dos, true) ?></div>

        <ul class="doc-checklist">
          <?php foreach ($dos['slots'] as $slot): ?>
            <li class="<?= $slot['present'] ? 'on' : 'missing' ?>">
              <span class="tick">✓</span>
              <span style="flex:1"><?= e($slot['label']) ?></span>
              <?php if ($slot['verified']): ?><span class="stamp">Verified</span>
              <?php elseif (!$slot['present']): ?><span class="small muted">Missing</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($docs): ?>
          <h3 class="small" style="text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft)">Attached documents</h3>
          <ul class="doc-list">
            <?php foreach ($docs as $d): ?>
              <li>
                <div class="doc-row">
                  <strong><?= e($d['title']) ?></strong>
                  <?php if ($d['verified']): ?><span class="stamp">Verified</span><?php endif; ?>
                </div>
                <?php if ($d['note']): ?><p class="small muted" style="margin:4px 0 0"><?= e($d['note']) ?></p><?php endif; ?>
                <?php if ($d['file_path']): ?>
                  <a class="small" href="uploads/<?= e($d['file_path']) ?>" target="_blank" rel="noopener">View file →</a>
                <?php else: ?>
                  <span class="small muted">Declared by seller — file not uploaded yet</span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted small">No documents attached yet. Ask the seller for the paperwork before viewing.</p>
        <?php endif; ?>

        <?php if ($isOwner): ?>
          <div class="form-actions">
            <a class="btn btn-primary btn-sm" href="documents.php?listing=<?= $id ?>">Manage documents</a>
          </div>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h3>Before you view this car</h3>
        <p class="muted small">Bring someone who knows <?= e($l['make']) ?>s better than the seller does.</p>
        <div class="form-actions" style="margin-top:8px">
          <a class="btn btn-primary" href="mechanics.php?make=<?= urlencode($l['make']) ?>&listing=<?= $id ?>">
            Hire a <?= e($l['make']) ?> specialist
          </a>
          <a class="btn btn-outline" href="safety.php?listing=<?= $id ?>">Set up safety check-in</a>
        </div>
      </div>

      <div class="panel">
        <h3>Seller</h3>
        <p style="margin:0 0 4px"><strong><?= e($l['seller_name']) ?></strong></p>
        <p class="muted small" style="margin:0">
          <?= e($l['seller_county'] ?: '—') ?> · Member since <?= date('M Y', strtotime($l['seller_since'])) ?>
        </p>
        <p class="muted small" style="margin-top:10px">
          In-app messaging isn't built yet — see the README roadmap. For now contact details would be exchanged after sign-in.
        </p>
      </div>

      <?php if ($isOwner && !$auction && $l['status'] === 'live'): ?>
        <div class="panel">
          <h3>Open to the trade</h3>
          <p class="muted small">Put this car in front of vetted dealers and let them bid. Premium feature — free in the dev build.</p>
          <a class="btn btn-blue btn-sm" href="auctions.php#create">Create dealer auction</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_footer(); ?>
