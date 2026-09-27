<?php
require_once __DIR__ . '/../src/layout.php';

$u         = current_user();
$listingId = (int)($_GET['listing'] ?? $_POST['listing'] ?? 0);
$listing   = $listingId ? db_row('SELECT * FROM listings WHERE id = ?', [$listingId]) : null;
if ($listing && $listing['status'] !== 'live' && (!$u || (int)$listing['user_id'] !== (int)$u['id'])) {
    $listing = null;
}

/* ---- create / close a check-in ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = require_login();

    if (isset($_POST['close_checkin'])) {
        db_exec(
            "UPDATE safety_checkins SET status = 'checked_in'
              WHERE id = ? AND user_id = ? AND status IN ('active','overdue')",
            [(int)$_POST['close_checkin'], $u['id']]
        );
        flash("Glad you're back safe.");
        redirect('safety.php');
    }

    $contactName  = mb_substr(trim($_POST['contact_name'] ?? ''), 0, 120);
    $contactPhone = mb_substr(trim($_POST['contact_phone'] ?? ''), 0, 40);
    $expectedRaw  = trim($_POST['expected_back'] ?? '');
    $expected     = local_datetime_to_utc($expectedRaw);

    if ($contactName === '' || $contactPhone === '' || !$expected) {
        flash('A trusted contact and a valid expected-back time are required.', 'error');
        redirect('safety.php' . ($listingId ? '?listing=' . $listingId : ''));
    }
    if ($expected <= gmdate('Y-m-d H:i:s')) {
        flash('Expected-back time must be in the future.', 'error');
        redirect('safety.php' . ($listingId ? '?listing=' . $listingId : ''));
    }

    db_exec(
        'INSERT INTO safety_checkins (user_id, listing_id, meeting_place, contact_name, contact_phone, expected_back)
         VALUES (?,?,?,?,?,?)',
        [
            $u['id'], $listing ? $listing['id'] : null,
            mb_substr(trim($_POST['meeting_place'] ?? ''), 0, 240) ?: null,
            $contactName, $contactPhone, $expected,
        ]
    );
    flash('Check-in armed. Tap "I\'m back safe" when you\'re done.');
    redirect('safety.php');
}

if ($u) {
    db_exec(
        "UPDATE safety_checkins SET status = 'overdue'
          WHERE user_id = ? AND status = 'active' AND expected_back <= datetime('now')",
        [$u['id']]
    );
}
$active = $u
    ? db_all("SELECT c.*, l.make, l.model, l.year FROM safety_checkins c
              LEFT JOIN listings l ON l.id = c.listing_id
              WHERE c.user_id = ? AND c.status IN ('active','overdue') ORDER BY c.created_at DESC", [$u['id']])
    : [];

page_header('Buyer safety', 'safety');
?>
<div class="wrap section-tight">
  <h1>Safety centre</h1>
  <p class="muted" style="max-width:64ch">Meeting a stranger with cash on one side and keys on the other is
    the sketchiest part of a private sale. These tools take the edge off.</p>

  <div class="check-grid" style="margin-top:24px">
    <div>
      <div class="panel">
        <h2>Before the viewing</h2>
        <ul class="safety-list">
          <li><span class="n">1</span><div><strong>Read the documents first.</strong> Missing paperwork isn't automatically a scam — but it's a question to ask before you travel, not after.</div></li>
          <li><span class="n">2</span><div><strong>Check the story adds up.</strong> Does the mileage match the service history? Does the seller's county match the reg? Underpriced cars with urgent sellers are the classic pattern.</div></li>
          <li><span class="n">3</span><div><strong>Meet in daylight, in public.</strong> A busy car park beats a laneway. If the seller will only meet somewhere odd at odd hours, walk.</div></li>
          <li><span class="n">4</span><div><strong>Bring someone.</strong> A friend — or better, <a href="mechanics.php">a mechanic who knows the make</a>. Two people change the dynamic of a viewing entirely.</div></li>
          <li><span class="n">5</span><div><strong>Never carry the full amount in cash.</strong> Agree the payment method beforehand. Be suspicious of pressure to pay a "holding deposit" before you've seen the car.</div></li>
          <li><span class="n">6</span><div><strong>On the test drive,</strong> confirm insurance cover first, and never leave your own keys, phone or wallet with a stranger "as security" while they drive off in anything.</div></li>
        </ul>
      </div>

      <div class="panel">
        <h3>History check by VIN</h3>
        <p class="muted small">Planned: paste a VIN, get finance/write-off/mileage flags pulled from a history-check API before you travel.</p>
        <div class="field" style="margin-bottom:10px">
          <label for="vin-stub">VIN</label>
          <input type="text" id="vin-stub" maxlength="17" placeholder="<?= e($listing['vin'] ?? '17-character VIN') ?>" disabled>
        </div>
        <div class="todo-note">Not connected in the dev build — see README roadmap for API options and where this wires in.</div>
      </div>
    </div>

    <div>
      <div class="panel">
        <h2>Viewing check-in</h2>
        <p class="muted small">Tell someone you trust where you're going and when you'll be back.
          Your check-in sits here until you close it.</p>

        <?php foreach ($active as $c): ?>
          <div class="premium-note" style="margin-bottom:14px">
            <strong>Active check-in</strong> —
            <?= $c['make'] ? e($c['year'] . ' ' . $c['make'] . ' ' . $c['model']) : 'car viewing' ?><?=
              $c['meeting_place'] ? ' at ' . e($c['meeting_place']) : '' ?>.
            Expected back <span class="mono"><?= date_time_ireland($c['expected_back']) ?></span>.
            <?= $c['status'] === 'overdue' ? '<strong style="color:var(--danger)"> Overdue.</strong>' : '' ?>
            <form method="post" class="inline-form" style="margin-top:8px;display:block">
              <?= csrf_field() ?>
              <button class="btn btn-primary btn-sm" name="close_checkin" value="<?= $c['id'] ?>">I'm back safe</button>
            </form>
          </div>
        <?php endforeach; ?>

        <?php if ($u): ?>
          <form method="post">
            <?= csrf_field() ?>
            <?php if ($listing): ?>
              <input type="hidden" name="listing" value="<?= $listing['id'] ?>">
              <p class="small">Viewing: <strong><?= e($listing['year'] . ' ' . $listing['make'] . ' ' . $listing['model']) ?></strong> <?= reg_plate($listing['reg']) ?></p>
            <?php endif; ?>
            <div class="field" style="margin-bottom:12px">
              <label for="meeting_place">Where are you meeting?</label>
              <input type="text" id="meeting_place" name="meeting_place" placeholder="e.g. Liffey Valley SC car park">
            </div>
            <div class="form-grid">
              <div class="field">
                <label for="contact_name">Trusted contact</label>
                <input type="text" id="contact_name" name="contact_name" placeholder="Name" required>
              </div>
              <div class="field">
                <label for="contact_phone">Their phone</label>
                <input type="tel" id="contact_phone" name="contact_phone" placeholder="08X XXX XXXX" required>
              </div>
            </div>
            <div class="field" style="margin:12px 0 8px">
              <label for="expected_back">Expected back</label>
              <input type="datetime-local" id="expected_back" name="expected_back" required>
            </div>
            <div style="display:flex;gap:8px;margin-bottom:14px">
              <button class="btn btn-outline btn-sm" type="button" data-add-hours="1">+1 hr</button>
              <button class="btn btn-outline btn-sm" type="button" data-add-hours="2">+2 hrs</button>
              <button class="btn btn-outline btn-sm" type="button" data-add-hours="3">+3 hrs</button>
            </div>
            <button class="btn btn-primary" type="submit">Arm check-in</button>
          </form>
          <div class="todo-note" style="margin-top:14px">
            Dev build: nothing is sent to your contact yet. Roadmap: SMS/WhatsApp alert to them
            if a check-in goes overdue, with the listing details and meeting place attached.
          </div>
        <?php else: ?>
          <p><a class="btn btn-primary" href="login.php?next=<?= urlencode('safety.php' . ($listingId ? '?listing=' . $listingId : '')) ?>">Sign in to use check-ins</a></p>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h3>Selling? It cuts both ways</h3>
        <p class="muted small" style="margin:0">Sellers meet strangers too. Same rules: public place,
          someone with you, insurance confirmed before any test drive, and no handing the keys over
          while you wait at the kerb.</p>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>
