<?php
require_once __DIR__ . '/../src/layout.php';

$id = (int)($_GET['id'] ?? 0);
$m  = db_row(
    'SELECT mp.*, u.name FROM mechanic_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.id = ?',
    [$id]
);
if (!$m) {
    http_response_code(404);
    page_header('Not found');
    echo '<div class="wrap section"><div class="panel"><h1>Mechanic not found</h1><p><a href="mechanics.php">All mechanics</a></p></div></div>';
    page_footer();
    exit;
}

$viewer = current_user();
$isOwnProfile = $viewer && (int)$viewer['id'] === (int)$m['user_id'];
if (!$m['verified'] && !$isOwnProfile) {
    http_response_code(404);
    page_header('Not found');
    echo '<div class="wrap section"><div class="panel"><h1>Mechanic not found</h1><p><a href="mechanics.php">All mechanics</a></p></div></div>';
    page_footer();
    exit;
}

$listingId = (int)($_GET['listing'] ?? $_POST['listing'] ?? 0);
$listing   = $listingId ? db_row("SELECT * FROM listings WHERE id = ? AND status = 'live'", [$listingId]) : null;

/* ---- booking request ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = require_login();
    if ($u['role'] === 'mechanic' && (int)$m['user_id'] === (int)$u['id']) {
        flash("That's your own profile.", 'warn');
        redirect('mechanic.php?id=' . $id);
    }
    if ($listing && (int)$listing['user_id'] === (int)$u['id']) {
        flash('You cannot request an inspection on your own listing.', 'error');
        redirect('mechanic.php?id=' . $id . '&listing=' . $listing['id']);
    }

    $whenRaw = trim($_POST['scheduled_for'] ?? '');
    $when = $whenRaw === '' ? null : local_datetime_to_utc($whenRaw);
    if ($whenRaw !== '' && (!$when || $when <= gmdate('Y-m-d H:i:s'))) {
        flash('Choose a valid future inspection time.', 'error');
        redirect('mechanic.php?id=' . $id . ($listing ? '&listing=' . $listing['id'] : ''));
    }

    $message = mb_substr(trim($_POST['message'] ?? ''), 0, 1000);
    db_exec(
        'INSERT INTO inspections (mechanic_id, buyer_id, listing_id, scheduled_for, message)
         VALUES (?,?,?,?,?)',
        [
            $id, $u['id'],
            $listing ? $listing['id'] : null,
            $when,
            $message ?: null,
        ]
    );
    flash('Inspection requested. ' . explode(' ', $m['name'])[0] . ' will confirm from their dashboard.');
    redirect('dashboard.php');
}

$specs   = db_all('SELECT * FROM mechanic_specialties WHERE mechanic_id = ?', [$id]);
$reviews = db_all(
    'SELECT r.*, u.name AS reviewer FROM mechanic_reviews r
       JOIN users u ON u.id = r.reviewer_id
      WHERE r.mechanic_id = ? ORDER BY r.created_at DESC', [$id]
);
$avg = $reviews ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : null;

// Per-make rating breakdown — the whole point of speciality-anchored reviews.
$byMake = [];
foreach ($reviews as $r) {
    $byMake[$r['car_make']]['sum']   = ($byMake[$r['car_make']]['sum'] ?? 0) + $r['rating'];
    $byMake[$r['car_make']]['count'] = ($byMake[$r['car_make']]['count'] ?? 0) + 1;
}

page_header($m['name'] . ' — mechanic', 'mechanics');
?>
<div class="wrap section-tight">
  <p class="small"><a href="mechanics.php">← All mechanics</a></p>

  <div class="detail-grid">
    <div>
      <div class="panel">
        <div class="mech-head">
          <div class="avatar" style="width:64px;height:64px;font-size:1.5rem"><?= e(strtoupper(substr($m['name'], 0, 1))) ?></div>
          <div>
            <h1 style="margin:0"><?= e($m['name']) ?></h1>
            <p class="muted" style="margin:2px 0 0"><?= e($m['headline'] ?: 'General inspections') ?></p>
          </div>
        </div>
        <div class="card-meta" style="margin-top:14px">
          <?php if ($avg): ?><span><?= stars($avg) ?> <?= $avg ?> · <?= count($reviews) ?> reviews</span><?php endif; ?>
          <span><?= e($m['base_county'] ?: '—') ?></span>
          <span><?= (int)$m['years_experience'] ?> yrs experience</span>
          <span>Callout <?= price_eur((int)$m['callout_fee_eur']) ?></span>
          <?php if ($m['verified']): ?><span class="badge badge-verified">ID verified</span><?php endif; ?>
        </div>
        <?php if ($m['bio']): ?><p style="margin-top:14px"><?= e($m['bio']) ?></p><?php endif; ?>

        <h3>Specialities</h3>
        <ul class="doc-checklist">
          <?php foreach ($specs as $s): ?>
            <li class="on">
              <span class="tick">✓</span>
              <span><strong><?= e($s['make']) ?></strong><?= $s['focus'] ? ' — ' . e($s['focus']) : '' ?>
                <?php if (isset($byMake[$s['make']])):
                    $b = $byMake[$s['make']]; ?>
                  <span class="small muted">(<?= number_format($b['sum'] / $b['count'], 1) ?>★ across <?= $b['count'] ?> <?= e($s['make']) ?> inspection<?= $b['count'] == 1 ? '' : 's' ?>)</span>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="panel">
        <h2>Reviews — anchored to the car</h2>
        <p class="muted small">Each review names the make and what was actually caught on the day.</p>
        <?php if (!$reviews): ?>
          <p class="muted">No reviews yet.</p>
        <?php endif; ?>
        <?php foreach ($reviews as $r): ?>
          <div class="review">
            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
              <strong><?= e($r['reviewer']) ?></strong>
              <span><?= stars((float)$r['rating']) ?></span>
            </div>
            <p class="small" style="margin:4px 0 0">
              <span class="tag"><?= e($r['car_make']) ?></span>
              <?php if ($r['car_model']): ?><span class="muted"> <?= e($r['car_model']) ?></span><?php endif; ?>
              <span class="muted"> · <?= date_short($r['created_at']) ?></span>
            </p>
            <?php if ($r['found_issues']): ?>
              <div class="found"><strong>What they caught:</strong> <?= e($r['found_issues']) ?></div>
            <?php endif; ?>
            <?php if ($r['comment']): ?><p style="margin:6px 0 0"><?= e($r['comment']) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <div class="panel">
        <h3>Request an inspection</h3>
        <?php if ($listing): ?>
          <p class="small">For: <strong><?= e($listing['year'] . ' ' . $listing['make'] . ' ' . $listing['model']) ?></strong>
            in <?= e($listing['county']) ?> — <?= reg_plate($listing['reg']) ?></p>
        <?php else: ?>
          <p class="muted small">Tip: open a listing first and tap "Hire a specialist" to attach the car automatically.</p>
        <?php endif; ?>

        <?php if (is_logged_in()): ?>
          <form method="post">
            <?= csrf_field() ?>
            <?php if ($listing): ?><input type="hidden" name="listing" value="<?= $listing['id'] ?>"><?php endif; ?>
            <div class="field" style="margin-bottom:14px">
              <label for="scheduled_for">Preferred date &amp; time</label>
              <input type="datetime-local" id="scheduled_for" name="scheduled_for">
            </div>
            <div class="field" style="margin-bottom:14px">
              <label for="message">Message (optional)</label>
              <textarea id="message" name="message" placeholder="Where's the car, and anything you're worried about?"></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Send request · <?= price_eur((int)$m['callout_fee_eur']) ?> callout</button>
            <p class="small muted" style="margin-top:10px">No payment is taken in the dev build — payments &amp; escrow are on the roadmap.</p>
          </form>
        <?php else: ?>
          <p><a class="btn btn-primary" href="login.php?next=<?= urlencode('mechanic.php?id=' . $id . ($listingId ? '&listing=' . $listingId : '')) ?>">Sign in to book</a></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>
