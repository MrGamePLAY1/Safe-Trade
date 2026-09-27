<?php
require_once __DIR__ . '/../src/layout.php';

db_exec("UPDATE auctions SET status = 'closed' WHERE status = 'open' AND ends_at <= datetime('now')");

$id = (int)($_GET['id'] ?? 0);
$a  = db_row(
    'SELECT a.*, l.*, a.id AS auction_id, a.status AS auction_status, a.created_at AS auction_created,
            u.name AS seller_name
       FROM auctions a
       JOIN listings l ON l.id = a.listing_id
       JOIN users u ON u.id = l.user_id
      WHERE a.id = ?', [$id]
);
if (!$a) {
    http_response_code(404);
    page_header('Not found');
    echo '<div class="wrap section"><div class="panel"><h1>Auction not found</h1><p><a href="auctions.php">All auctions</a></p></div></div>';
    page_footer();
    exit;
}

$u        = current_user();
$isSeller = $u && (int)$u['id'] === (int)$a['user_id'];
$isDealer = $u && $u['role'] === 'dealer' && !$isSeller;
$bids     = db_all(
    'SELECT b.*, u.name FROM bids b JOIN users u ON u.id = b.dealer_id
      WHERE b.auction_id = ? ORDER BY b.amount_eur DESC, b.created_at ASC, b.id ASC', [$id]
);
$high     = $bids[0]['amount_eur'] ?? 0;
$open     = $a['auction_status'] === 'open';

/* ---- place a bid (dealers only) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = require_role('dealer');
    $amount = (int)($_POST['amount'] ?? 0);

    $pdo = db();
    try {
        // Serialize competing bid requests so two dealers cannot both win at the same amount.
        $pdo->exec('BEGIN IMMEDIATE');

        $fresh = db_row(
            'SELECT a.status, a.ends_at, a.reserve_eur, l.user_id AS seller_id
               FROM auctions a
               JOIN listings l ON l.id = a.listing_id
              WHERE a.id = ?',
            [$id]
        );
        if (!$fresh) {
            $pdo->exec('ROLLBACK');
            http_response_code(404);
            exit('Auction not found.');
        }
        if ((int)$fresh['seller_id'] === (int)$u['id']) {
            $pdo->exec('ROLLBACK');
            flash('You cannot bid on your own vehicle.', 'error');
            redirect('auction.php?id=' . $id);
        }

        $now = gmdate('Y-m-d H:i:s');
        if ($fresh['status'] !== 'open' || $fresh['ends_at'] <= $now) {
            db_exec("UPDATE auctions SET status = 'closed' WHERE id = ? AND status = 'open'", [$id]);
            $pdo->exec('COMMIT');
            flash('This auction has ended.', 'warn');
            redirect('auction.php?id=' . $id);
        }

        $row = db_row('SELECT MAX(amount_eur) AS high_bid FROM bids WHERE auction_id = ?', [$id]);
        $currentHigh = (int)($row['high_bid'] ?? 0);
        $minimum = $currentHigh + 50;
        if ($amount < $minimum) {
            $pdo->exec('ROLLBACK');
            flash('Your bid must be at least ' . price_eur($minimum) . '.', 'error');
            redirect('auction.php?id=' . $id);
        }

        db_exec('INSERT INTO bids (auction_id, dealer_id, amount_eur) VALUES (?,?,?)', [$id, $u['id'], $amount]);
        $pdo->exec('COMMIT');

        flash('Bid placed: ' . price_eur($amount)
            . ($fresh['reserve_eur'] && $amount < $fresh['reserve_eur'] ? ' — note: still below reserve.' : ''));
        redirect('auction.php?id=' . $id);
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $e;
    }
}

$reserveMet = $a['reserve_eur'] ? $high >= $a['reserve_eur'] : true;

page_header('Auction — ' . $a['year'] . ' ' . $a['make'] . ' ' . $a['model'], 'auctions');
?>
<div class="wrap section-tight">
  <p class="small"><a href="auctions.php">← All auctions</a></p>

  <div class="detail-grid">
    <div>
      <?= car_thumb($a, 'lg') ?>
      <div class="panel" style="margin-top:18px">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:14px;flex-wrap:wrap">
          <div>
            <h1 style="margin-bottom:6px"><?= e($a['year'] . ' ' . $a['make'] . ' ' . $a['model']) ?></h1>
            <?= reg_plate($a['reg']) ?>
          </div>
          <div style="text-align:right">
            <?php if ($open): ?>
              <span class="badge badge-auction">Live</span>
              <div class="countdown" style="margin-top:6px" data-ends-at="<?= e($a['ends_at']) ?>"></div>
            <?php else: ?>
              <span class="badge badge-sold">Ended</span>
            <?php endif; ?>
          </div>
        </div>
        <table class="spec-table" style="margin-top:16px">
          <tr><th>Mileage</th><td><?= km($a['mileage_km']) ?></td></tr>
          <tr><th>Fuel / gearbox</th><td><?= e($a['fuel']) ?> / <?= e($a['transmission']) ?></td></tr>
          <tr><th>County</th><td><?= e($a['county']) ?></td></tr>
          <tr><th>Seller's asking price</th><td><?= price_eur((int)$a['price_eur']) ?></td></tr>
          <tr><th>Reserve</th><td><?= $a['reserve_eur'] ? ($reserveMet ? '<span class="stamp-ink">Met</span>' : 'Not met') : 'None' ?></td></tr>
        </table>
        <p class="small" style="margin-top:12px">
          <a href="listing.php?id=<?= $a['listing_id'] ?>">Full listing &amp; documents →</a>
        </p>
      </div>
    </div>

    <div>
      <div class="panel">
        <h2 style="margin-top:0">Bidding</h2>
        <p class="price" style="font-size:1.6rem;margin:0">
          <?= $high ? price_eur((int)$high) : 'No bids yet' ?>
        </p>
        <p class="muted small"><?= count($bids) ?> bid<?= count($bids) == 1 ? '' : 's' ?></p>

        <?php if ($open && $isDealer): ?>
          <form method="post" data-bid-form data-min-bid="<?= (int)$high ?>">
            <?= csrf_field() ?>
            <div class="field" style="margin-bottom:12px">
              <label for="amount">Your bid (€)</label>
              <input type="number" id="amount" name="amount" min="<?= (int)$high + 50 ?>" step="50"
                     placeholder="<?= (int)$high + 100 ?>" required>
            </div>
            <button class="btn btn-blue" type="submit">Place bid</button>
          </form>
        <?php elseif ($open && !$u): ?>
          <p><a class="btn btn-blue" href="login.php?next=<?= urlencode('auction.php?id=' . $id) ?>">Dealer sign-in to bid</a></p>
        <?php elseif ($open && !$isDealer): ?>
          <p class="muted small"><?= $isSeller ? "You cannot bid on your own vehicle — watch the dealer bids here." : "Bidding is limited to approved dealer accounts." ?></p>
        <?php else: ?>
          <p class="muted small">Auction ended <?= date_short($a['ends_at']) ?>.
            <?php if ($high && $reserveMet): ?>Winning bid: <strong><?= price_eur((int)$high) ?></strong>.
            <?php elseif ($high): ?>High bid <?= price_eur((int)$high) ?> did not meet the reserve.
            <?php else: ?>No bids were placed.<?php endif; ?>
          </p>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h3>Bid history</h3>
        <?php if (!$bids): ?>
          <p class="muted small" style="margin:0">Nothing yet<?= $open ? ' — be first' : '' ?>.</p>
        <?php endif; ?>
        <?php foreach ($bids as $i => $b): ?>
          <div class="bid-row">
            <span><?= $isSeller || $isDealer ? e($b['name']) : 'Dealer ' . chr(65 + ($i % 26)) ?><?= $i === 0 ? ' · high' : '' ?></span>
            <span><?= price_eur((int)$b['amount_eur']) ?></span>
          </div>
        <?php endforeach; ?>
        <p class="small muted" style="margin-top:10px">Seller: <?= e($a['seller_name']) ?></p>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>
