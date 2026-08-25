<?php
require_once __DIR__ . '/../src/layout.php';

// Lazy close: any open auction past its end time gets closed on page load.
// TODO: replace with a cron/queue job so winners are notified promptly.
db_exec("UPDATE auctions SET status = 'closed' WHERE status = 'open' AND ends_at <= datetime('now','localtime')");

$u = current_user();

/* ---- seller creates an auction ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = require_login();
    $listingId = (int)($_POST['listing_id'] ?? 0);
    $listing = db_row(
        "SELECT * FROM listings WHERE id = ? AND user_id = ? AND status = 'live'",
        [$listingId, $u['id']]
    );
    if (!$listing) {
        flash('Pick one of your own live listings.', 'error');
        redirect('auctions.php#create');
    }
    if (db_row('SELECT id FROM auctions WHERE listing_id = ?', [$listingId])) {
        flash('That car already has an auction.', 'warn');
        redirect('auctions.php');
    }
    $reserve = (int)($_POST['reserve_eur'] ?? 0) ?: null;
    $days    = max(1, min(7, (int)($_POST['days'] ?? 3)));
    $aid = db_exec(
        'INSERT INTO auctions (listing_id, reserve_eur, ends_at) VALUES (?,?,?)',
        [$listingId, $reserve, date('Y-m-d H:i:s', strtotime("+{$days} days"))]
    );
    flash('Auction is live — dealers can bid for the next ' . $days . ' day' . ($days == 1 ? '' : 's') . '.');
    redirect('auction.php?id=' . $aid);
}

$open = db_all(
    "SELECT a.*, l.make, l.model, l.year, l.reg, l.mileage_km, l.county, l.price_eur, l.id AS lid,
            (SELECT MAX(amount_eur) FROM bids b WHERE b.auction_id = a.id) AS high_bid,
            (SELECT COUNT(*) FROM bids b WHERE b.auction_id = a.id) AS bid_count
       FROM auctions a JOIN listings l ON l.id = a.listing_id
      WHERE a.status = 'open'
      ORDER BY a.ends_at"
);

// A private seller's live listings that don't yet have an auction.
$eligible = [];
if ($u && $u['role'] === 'private') {
    $eligible = db_all(
        "SELECT * FROM listings
          WHERE user_id = ? AND status = 'live'
            AND id NOT IN (SELECT listing_id FROM auctions)",
        [$u['id']]
    );
}

page_header('Dealer auctions', 'auctions');
?>
<div class="wrap section-tight">
  <h1>Dealer auctions</h1>
  <p class="muted" style="max-width:64ch">Skip the "get three quotes" run-around: open your car to the trade
    and let vetted dealers bid against each other. Buying trade-in convenience shouldn't mean taking the first offer.</p>

  <div class="premium-note" style="margin-bottom:24px">
    <strong>Premium feature.</strong> In production this is the paid tier (listing fee or a cut on completion).
    It's free in the dev build — see README for the monetisation notes.
  </div>

  <div class="section-head"><h2>Open now</h2></div>
  <?php if (!$open): ?>
    <div class="panel"><p class="muted" style="margin:0">No live auctions at the minute.</p></div>
  <?php else: ?>
    <div class="grid-3">
      <?php foreach ($open as $a): ?>
        <a class="card" href="auction.php?id=<?= $a['id'] ?>">
          <?= car_thumb(['make'=>$a['make'],'model'=>$a['model'],'year'=>$a['year']]) ?>
          <div class="card-body">
            <div class="card-title">
              <h3><?= e($a['year'] . ' ' . $a['make'] . ' ' . $a['model']) ?></h3>
            </div>
            <div class="card-meta">
              <span><?= km((int)$a['mileage_km']) ?></span>
              <span><?= e($a['county']) ?></span>
              <span><?= $a['bid_count'] ?> bid<?= $a['bid_count'] == 1 ? '' : 's' ?></span>
            </div>
            <div class="card-foot">
              <span class="price"><?= $a['high_bid'] ? price_eur((int)$a['high_bid']) : 'No bids' ?></span>
              <span class="countdown" data-ends-at="<?= e($a['ends_at']) ?>"></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div id="create" class="section-head" style="margin-top:40px"><h2>Put your car to the trade</h2></div>
  <?php if (!$u): ?>
    <div class="panel">
      <p style="margin:0"><a href="login.php?next=auctions.php">Sign in</a> with a private account to auction one of your listings,
        or <a href="register.php">register as a dealer</a> to bid.</p>
    </div>
  <?php elseif ($u['role'] === 'dealer'): ?>
    <div class="panel">
      <p style="margin:0">You're signed in as a dealer — pick any open auction above and get bidding.
        Your bids appear in your <a href="dashboard.php">dashboard</a>.</p>
    </div>
  <?php elseif ($u['role'] === 'mechanic'): ?>
    <div class="panel"><p style="margin:0" class="muted">Auctions are between private sellers and dealers.</p></div>
  <?php elseif (!$eligible): ?>
    <div class="panel">
      <p style="margin:0" class="muted">You've no live listings without an auction.
        <a href="sell.php">List a car first</a> — a full dossier gets stronger trade bids.</p>
    </div>
  <?php else: ?>
    <div class="form-card">
      <form method="post">
        <?= csrf_field() ?>
        <div class="field" style="margin-bottom:14px">
          <label for="listing_id">Which car?</label>
          <select id="listing_id" name="listing_id" required>
            <?php foreach ($eligible as $l): ?>
              <option value="<?= $l['id'] ?>">
                <?= e($l['year'] . ' ' . $l['make'] . ' ' . $l['model'] . ' — ' . $l['reg']) ?> (asking <?= price_eur((int)$l['price_eur']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="reserve_eur">Reserve € (optional — lowest you'd accept)</label>
            <input type="number" id="reserve_eur" name="reserve_eur" min="0" step="100" placeholder="No reserve">
          </div>
          <div class="field">
            <label for="days">Run for</label>
            <select id="days" name="days">
              <option value="1">1 day</option>
              <option value="3" selected>3 days</option>
              <option value="5">5 days</option>
              <option value="7">7 days</option>
            </select>
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-blue" type="submit">Start dealer auction</button>
          <span class="small muted">Dealers see the dossier — complete it first.</span>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
