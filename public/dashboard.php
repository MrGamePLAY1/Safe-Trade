<?php
require_once __DIR__ . '/../src/layout.php';

$u = require_login();

/* =====================================================================
   POST actions (all CSRF-checked, all ownership-checked)
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Seller: change listing status
    if (isset($_POST['listing_status'], $_POST['listing_id'])) {
        $allowed = ['live','sale_agreed','sold','archived'];
        $target = $_POST['listing_status'];
        $listingId = (int)$_POST['listing_id'];

        if (in_array($target, $allowed, true)) {
            $owned = db_row('SELECT id FROM listings WHERE id = ? AND user_id = ?', [$listingId, $u['id']]);
            if ($owned) {
                if ($target === 'live' && !listing_can_publish($listingId)) {
                    flash('Upload at least one core document file before publishing.', 'error');
                    redirect('dashboard.php');
                }
                db_exec('UPDATE listings SET status = ? WHERE id = ? AND user_id = ?', [$target, $listingId, $u['id']]);
                if ($target !== 'live') {
                    db_exec("UPDATE auctions SET status = 'cancelled' WHERE listing_id = ? AND status = 'open'", [$listingId]);
                }
                flash('Listing updated.');
            }
        }
        redirect('dashboard.php');
    }

    // Mechanic: move an inspection along using explicit state transitions.
    if (isset($_POST['inspection_status'], $_POST['inspection_id']) && $u['role'] === 'mechanic') {
        $inspectionId = (int)$_POST['inspection_id'];
        $target = (string)$_POST['inspection_status'];
        $row = db_row(
            'SELECT i.status FROM inspections i
              JOIN mechanic_profiles mp ON mp.id = i.mechanic_id
             WHERE i.id = ? AND mp.user_id = ?',
            [$inspectionId, $u['id']]
        );
        if ($row && inspection_transition_allowed($row['status'], $target, 'mechanic')) {
            db_exec('UPDATE inspections SET status = ? WHERE id = ?', [$target, $inspectionId]);
            flash('Inspection ' . $target . '.');
        } else {
            flash('That inspection status change is not allowed.', 'error');
        }
        redirect('dashboard.php');
    }

    // Buyer: cancel only a requested or confirmed inspection.
    if (isset($_POST['cancel_inspection'])) {
        $inspectionId = (int)$_POST['cancel_inspection'];
        $row = db_row('SELECT status FROM inspections WHERE id = ? AND buyer_id = ?', [$inspectionId, $u['id']]);
        if ($row && inspection_transition_allowed($row['status'], 'cancelled', 'buyer')) {
            db_exec("UPDATE inspections SET status = 'cancelled' WHERE id = ? AND buyer_id = ?", [$inspectionId, $u['id']]);
            flash('Request cancelled.');
        } else {
            flash('That inspection can no longer be cancelled.', 'error');
        }
        redirect('dashboard.php');
    }

    // Mechanic: profile + specialities
    if (isset($_POST['save_profile']) && $u['role'] === 'mechanic') {
        $county = trim($_POST['base_county'] ?? '');
        if (!valid_county($county)) {
            flash('Choose a valid county.', 'error');
            redirect('dashboard.php#profile');
        }
        $fee = max(0, min(10000, (int)($_POST['callout_fee_eur'] ?? 0)));
        $years = max(0, min(80, (int)($_POST['years_experience'] ?? 0)));
        db_exec(
            'UPDATE mechanic_profiles SET headline = ?, bio = ?, base_county = ?, callout_fee_eur = ?, years_experience = ?
              WHERE user_id = ?',
            [
                mb_substr(trim($_POST['headline'] ?? ''), 0, 160) ?: null,
                mb_substr(trim($_POST['bio'] ?? ''), 0, 3000) ?: null,
                $county ?: null,
                $fee,
                $years,
                $u['id'],
            ]
        );
        flash('Profile saved.');
        redirect('dashboard.php#profile');
    }
    if (isset($_POST['add_specialty']) && $u['role'] === 'mechanic') {
        $make = trim($_POST['spec_make'] ?? '');
        if ($make !== '') {
            $profile = db_row('SELECT id FROM mechanic_profiles WHERE user_id = ?', [$u['id']]);
            $exists = $profile ? db_row(
                'SELECT id FROM mechanic_specialties WHERE mechanic_id = ? AND lower(make) = lower(?)',
                [$profile['id'], $make]
            ) : null;
            if ($exists) {
                flash('That speciality is already on your profile.', 'warn');
            } else {
                db_exec(
                    'INSERT INTO mechanic_specialties (mechanic_id, make, focus)
                     VALUES ((SELECT id FROM mechanic_profiles WHERE user_id = ?), ?, ?)',
                    [$u['id'], mb_substr($make, 0, 80), mb_substr(trim($_POST['spec_focus'] ?? ''), 0, 300) ?: null]
                );
                flash('Speciality added.');
            }
        }
        redirect('dashboard.php#profile');
    }
    if (isset($_POST['delete_specialty']) && $u['role'] === 'mechanic') {
        db_exec(
            'DELETE FROM mechanic_specialties
              WHERE id = ? AND mechanic_id = (SELECT id FROM mechanic_profiles WHERE user_id = ?)',
            [(int)$_POST['delete_specialty'], $u['id']]
        );
        redirect('dashboard.php#profile');
    }
}

/* =====================================================================
   Data per role
   ===================================================================== */
$myListings = db_all(
    'SELECT l.*,
            (SELECT COUNT(DISTINCT doc_type) FROM documents d WHERE d.listing_id = l.id AND d.doc_type != "other" AND d.file_path IS NOT NULL) AS doc_kinds,
            (SELECT id FROM auctions a WHERE a.listing_id = l.id) AS auction_id
       FROM listings l WHERE user_id = ? ORDER BY created_at DESC', [$u['id']]
);

$myInspections = db_all(
    'SELECT i.*, mu.name AS mechanic_name, mp.id AS mech_profile_id,
            l.make, l.model, l.year
       FROM inspections i
       JOIN mechanic_profiles mp ON mp.id = i.mechanic_id
       JOIN users mu ON mu.id = mp.user_id
       LEFT JOIN listings l ON l.id = i.listing_id
      WHERE i.buyer_id = ? ORDER BY i.created_at DESC', [$u['id']]
);

db_exec(
    "UPDATE safety_checkins SET status = 'overdue'
      WHERE user_id = ? AND status = 'active' AND expected_back <= datetime('now')",
    [$u['id']]
);
$myCheckins = db_all(
    "SELECT c.*, l.make, l.model, l.year FROM safety_checkins c
     LEFT JOIN listings l ON l.id = c.listing_id
     WHERE c.user_id = ? ORDER BY c.created_at DESC LIMIT 8", [$u['id']]
);

$mechProfile = $mechQueue = $mechSpecs = null;
if ($u['role'] === 'mechanic') {
    $mechProfile = db_row('SELECT * FROM mechanic_profiles WHERE user_id = ?', [$u['id']]);
    if (!$mechProfile) {
        $profileId = db_exec('INSERT INTO mechanic_profiles (user_id, base_county) VALUES (?,?)', [$u['id'], $u['county'] ?: null]);
        $mechProfile = db_row('SELECT * FROM mechanic_profiles WHERE id = ?', [$profileId]);
    }
    $mechSpecs   = db_all('SELECT * FROM mechanic_specialties WHERE mechanic_id = ?', [$mechProfile['id']]);
    $mechQueue   = db_all(
        'SELECT i.*, bu.name AS buyer_name, bu.phone AS buyer_phone, l.make, l.model, l.year, l.county
           FROM inspections i
           JOIN users bu ON bu.id = i.buyer_id
           LEFT JOIN listings l ON l.id = i.listing_id
          WHERE i.mechanic_id = ? ORDER BY
            CASE i.status WHEN "requested" THEN 0 WHEN "confirmed" THEN 1 ELSE 2 END,
            i.created_at DESC', [$mechProfile['id']]
    );
}

$myBids = [];
if ($u['role'] === 'dealer') {
    $myBids = db_all(
        'SELECT b.*, a.status AS auction_status, a.ends_at, a.reserve_eur, a.id AS aid,
                l.make, l.model, l.year,
                (SELECT MAX(amount_eur) FROM bids b2 WHERE b2.auction_id = a.id) AS high_bid
           FROM bids b
           JOIN auctions a ON a.id = b.auction_id
           JOIN listings l ON l.id = a.listing_id
          WHERE b.dealer_id = ?
            AND b.id = (
                SELECT b3.id FROM bids b3
                 WHERE b3.auction_id = a.id AND b3.dealer_id = ?
                 ORDER BY b3.amount_eur DESC, b3.created_at ASC, b3.id ASC
                 LIMIT 1
            )
          ORDER BY a.ends_at DESC', [$u['id'], $u['id']]
    );
}

page_header('Dashboard', 'dashboard');
?>
<div class="wrap section-tight">
  <h1>Hello, <?= e(explode(' ', $u['name'])[0]) ?></h1>
  <p class="muted"><?= ucfirst($u['role']) ?> account · <?= e($u['county'] ?: 'no county set') ?></p>

  <?php /* ---------------- MECHANIC ---------------- */ ?>
  <?php if ($u['role'] === 'mechanic'): ?>
    <div class="section-head" style="margin-top:24px"><h2>Inspection requests</h2></div>
    <div class="panel" style="padding:0 20px">
      <?php if (!$mechQueue): ?>
        <p class="muted" style="padding:16px 0">No requests yet. A sharper profile below helps —
          buyers filter by make speciality.</p>
      <?php else: ?>
        <table class="dash-table">
          <tr><th>Car</th><th>Buyer</th><th>When</th><th>Status</th><th></th></tr>
          <?php foreach ($mechQueue as $q): ?>
            <tr>
              <td><?= $q['make'] ? e($q['year'] . ' ' . $q['make'] . ' ' . $q['model']) . '<span class="small muted"> · ' . e($q['county']) . '</span>' : '<span class="muted">No listing attached</span>' ?>
                <?php if ($q['message']): ?><br><span class="small muted">"<?= e($q['message']) ?>"</span><?php endif; ?>
              </td>
              <td><?= e($q['buyer_name']) ?><?= $q['status'] !== 'requested' && $q['buyer_phone'] ? '<br><span class="small mono">' . e($q['buyer_phone']) . '</span>' : '' ?></td>
              <td class="mono small"><?= $q['scheduled_for'] ? date_time_ireland($q['scheduled_for']) : 'TBC' ?></td>
              <td><span class="badge badge-<?= $q['status'] === 'completed' ? 'sold' : ($q['status'] === 'confirmed' ? 'live' : ($q['status'] === 'cancelled' ? 'archived' : 'sale_agreed')) ?>"><?= e($q['status']) ?></span></td>
              <td>
                <?php if ($q['status'] === 'requested'): ?>
                  <form method="post" class="inline-form"><?= csrf_field() ?>
                    <input type="hidden" name="inspection_id" value="<?= $q['id'] ?>">
                    <button class="btn btn-primary btn-sm" name="inspection_status" value="confirmed">Confirm</button>
                    <button class="btn btn-danger btn-sm" name="inspection_status" value="cancelled">Decline</button>
                  </form>
                <?php elseif ($q['status'] === 'confirmed'): ?>
                  <form method="post" class="inline-form"><?= csrf_field() ?>
                    <input type="hidden" name="inspection_id" value="<?= $q['id'] ?>">
                    <button class="btn btn-outline btn-sm" name="inspection_status" value="completed">Mark completed</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <div id="profile" class="section-head" style="margin-top:36px"><h2>My mechanic profile</h2></div>
    <div class="grid-2">
      <div class="form-card" style="max-width:none">
        <form method="post">
          <?= csrf_field() ?>
          <div class="field" style="margin-bottom:14px">
            <label for="headline">Headline</label>
            <input type="text" id="headline" name="headline" value="<?= e($mechProfile['headline']) ?>" placeholder="e.g. German marques — BMW, Audi, VW diesels">
          </div>
          <div class="field" style="margin-bottom:14px">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio"><?= e($mechProfile['bio']) ?></textarea>
          </div>
          <div class="form-grid">
            <div class="field">
              <label for="base_county">Base county</label>
              <select id="base_county" name="base_county">
                <option value="">—</option>
                <?php foreach (COUNTIES as $c): ?>
                  <option <?= $mechProfile['base_county'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="callout_fee_eur">Callout fee €</label>
              <input type="number" id="callout_fee_eur" name="callout_fee_eur" min="0" value="<?= (int)$mechProfile['callout_fee_eur'] ?>">
            </div>
            <div class="field">
              <label for="years_experience">Years experience</label>
              <input type="number" id="years_experience" name="years_experience" min="0" value="<?= (int)$mechProfile['years_experience'] ?>">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" name="save_profile" value="1">Save profile</button>
            <a class="small" href="mechanic.php?id=<?= $mechProfile['id'] ?>">View public profile →</a>
          </div>
        </form>
      </div>

      <div class="panel">
        <h3>Specialities</h3>
        <p class="muted small">These drive the make filter and anchor your reviews.</p>
        <?php foreach ($mechSpecs as $s): ?>
          <div class="doc-row" style="padding:8px 0;border-bottom:1px solid var(--line-soft)">
            <span><span class="tag"><?= e($s['make']) ?></span> <?= e($s['focus'] ?: '') ?></span>
            <form method="post" class="inline-form"><?= csrf_field() ?>
              <button class="btn btn-danger btn-sm" name="delete_specialty" value="<?= $s['id'] ?>">×</button>
            </form>
          </div>
        <?php endforeach; ?>
        <form method="post" style="margin-top:14px">
          <?= csrf_field() ?>
          <div class="form-grid">
            <div class="field">
              <label for="spec_make">Make</label>
              <input type="text" id="spec_make" name="spec_make" placeholder="e.g. BMW">
            </div>
            <div class="field">
              <label for="spec_focus">Known for (optional)</label>
              <input type="text" id="spec_focus" name="spec_focus" placeholder="e.g. timing chain wear">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn-outline btn-sm" name="add_specialty" value="1">Add speciality</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php /* ---------------- DEALER ---------------- */ ?>
  <?php if ($u['role'] === 'dealer'): ?>
    <div class="section-head" style="margin-top:24px">
      <h2>My auction activity</h2>
      <a href="auctions.php">Open auctions →</a>
    </div>
    <div class="panel" style="padding:0 20px">
      <?php if (!$myBids): ?>
        <p class="muted" style="padding:16px 0">No bids yet — <a href="auctions.php">see what's on</a>.</p>
      <?php else: ?>
        <table class="dash-table">
          <tr><th>Car</th><th>My best bid</th><th>High bid</th><th>Status</th><th>Ends</th></tr>
          <?php foreach ($myBids as $b):
              $winning = (int)$b['amount_eur'] === (int)$b['high_bid'];
              $won     = $b['auction_status'] === 'closed' && $winning && (!$b['reserve_eur'] || $b['high_bid'] >= $b['reserve_eur']);
          ?>
            <tr>
              <td><a href="auction.php?id=<?= $b['aid'] ?>"><?= e($b['year'] . ' ' . $b['make'] . ' ' . $b['model']) ?></a></td>
              <td class="mono"><?= price_eur((int)$b['amount_eur']) ?></td>
              <td class="mono"><?= price_eur((int)$b['high_bid']) ?></td>
              <td>
                <?php if ($b['auction_status'] === 'open'): ?>
                  <span class="badge badge-<?= $winning ? 'live' : 'sale_agreed' ?>"><?= $winning ? 'Winning' : 'Outbid' ?></span>
                <?php else: ?>
                  <span class="badge badge-<?= $won ? 'live' : 'sold' ?>"><?= $won ? 'Won' : 'Lost' ?></span>
                <?php endif; ?>
              </td>
              <td class="mono small"><?= $b['auction_status'] === 'open' ? '<span class="countdown" data-ends-at="' . e($b['ends_at']) . '"></span>' : date_short($b['ends_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php /* ---------------- LISTINGS (any role can sell) ---------------- */ ?>
  <div class="section-head" style="margin-top:36px">
    <h2>My listings</h2>
    <a class="btn btn-primary btn-sm" href="sell.php">List a car</a>
  </div>
  <div class="panel" style="padding:0 20px">
    <?php if (!$myListings): ?>
      <p class="muted" style="padding:16px 0">Nothing listed yet.</p>
    <?php else: ?>
      <table class="dash-table">
        <tr><th>Car</th><th>Price</th><th>Documents</th><th>Status</th><th></th></tr>
        <?php foreach ($myListings as $l): ?>
          <tr>
            <td>
              <a href="listing.php?id=<?= $l['id'] ?>"><?= e($l['year'] . ' ' . $l['make'] . ' ' . $l['model']) ?></a>
              <?php if ($l['auction_id']): ?> <a class="badge badge-auction" href="auction.php?id=<?= $l['auction_id'] ?>">Auction</a><?php endif; ?>
            </td>
            <td class="mono"><?= price_eur((int)$l['price_eur']) ?></td>
            <td class="mono small"><?= (int)$l['doc_kinds'] ?>/6 · <a class="small" href="documents.php?listing=<?= $l['id'] ?>">edit</a></td>
            <td><?= status_badge($l['status']) ?></td>
            <td>
              <form method="post" class="inline-form"><?= csrf_field() ?>
                <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                <select name="listing_status" onchange="this.form.submit()" style="width:auto;padding:6px 8px">
                  <option value="">Move to…</option>
                  <?php foreach (['live'=>'Live','sale_agreed'=>'Sale agreed','sold'=>'Sold','archived'=>'Archived'] as $k => $label): ?>
                    <?php if ($k !== $l['status']): ?><option value="<?= $k ?>"><?= $label ?></option><?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>

  <?php /* ---------------- INSPECTIONS AS BUYER ---------------- */ ?>
  <div class="section-head" style="margin-top:36px"><h2>My inspection requests</h2></div>
  <div class="panel" style="padding:0 20px">
    <?php if (!$myInspections): ?>
      <p class="muted" style="padding:16px 0">None yet — from any listing, tap "Hire a specialist".</p>
    <?php else: ?>
      <table class="dash-table">
        <tr><th>Mechanic</th><th>Car</th><th>When</th><th>Status</th><th></th></tr>
        <?php foreach ($myInspections as $i): ?>
          <tr>
            <td><a href="mechanic.php?id=<?= $i['mech_profile_id'] ?>"><?= e($i['mechanic_name']) ?></a></td>
            <td><?= $i['make'] ? e($i['year'] . ' ' . $i['make'] . ' ' . $i['model']) : '<span class="muted">—</span>' ?></td>
            <td class="mono small"><?= $i['scheduled_for'] ? date_time_ireland($i['scheduled_for']) : 'TBC' ?></td>
            <td><span class="badge badge-<?= $i['status'] === 'completed' ? 'sold' : ($i['status'] === 'confirmed' ? 'live' : ($i['status'] === 'cancelled' ? 'archived' : 'sale_agreed')) ?>"><?= e($i['status']) ?></span></td>
            <td>
              <?php if (in_array($i['status'], ['requested','confirmed'], true)): ?>
                <form method="post" class="inline-form"><?= csrf_field() ?>
                  <button class="btn btn-danger btn-sm" name="cancel_inspection" value="<?= $i['id'] ?>">Cancel</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>

  <?php /* ---------------- CHECK-INS ---------------- */ ?>
  <div class="section-head" style="margin-top:36px">
    <h2>Safety check-ins</h2>
    <a href="safety.php">Safety centre →</a>
  </div>
  <div class="panel" style="padding:0 20px">
    <?php if (!$myCheckins): ?>
      <p class="muted" style="padding:16px 0">No check-ins yet.</p>
    <?php else: ?>
      <table class="dash-table">
        <tr><th>Viewing</th><th>Contact</th><th>Expected back</th><th>Status</th></tr>
        <?php foreach ($myCheckins as $c): ?>
          <tr>
            <td><?= $c['make'] ? e($c['year'] . ' ' . $c['make'] . ' ' . $c['model']) : '—' ?><?= $c['meeting_place'] ? '<br><span class="small muted">' . e($c['meeting_place']) . '</span>' : '' ?></td>
            <td><?= e($c['contact_name']) ?> <span class="small mono"><?= e($c['contact_phone']) ?></span></td>
            <td class="mono small"><?= date_time_ireland($c['expected_back']) ?></td>
            <td><span class="badge badge-<?= $c['status'] === 'overdue' ? 'archived' : ($c['status'] === 'active' ? 'sale_agreed' : 'live') ?>"><?= e(str_replace('_', ' ', $c['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>
