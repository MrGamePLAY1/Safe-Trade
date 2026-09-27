<?php
require_once __DIR__ . '/../src/layout.php';

$u = require_login();

$errors = [];
$old = ['make'=>'','model'=>'','year'=>'','reg'=>'','vin'=>'','mileage_km'=>'','price_eur'=>'',
        'fuel'=>'','transmission'=>'','colour'=>'','county'=>$u['county'] ?? '','description'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $v) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    if ($old['make'] === '')  $errors[] = 'Make is required.';
    if ($old['model'] === '') $errors[] = 'Model is required.';
    if (mb_strlen($old['make']) > 60 || mb_strlen($old['model']) > 80) $errors[] = 'Make or model is too long.';

    $year = (int)$old['year'];
    if ($year < 1980 || $year > (int)date('Y') + 1) $errors[] = 'Enter a valid year.';

    $price = (int)$old['price_eur'];
    if ($price <= 0) $errors[] = 'Enter an asking price.';

    $mileage = $old['mileage_km'] === '' ? null : (int)$old['mileage_km'];
    if ($mileage !== null && $mileage < 0) $errors[] = 'Mileage cannot be negative.';

    $fuels = ['Petrol','Diesel','Hybrid','Plug-in Hybrid','Electric'];
    $transmissions = ['Manual','Automatic'];
    if ($old['fuel'] !== '' && !in_array($old['fuel'], $fuels, true)) $errors[] = 'Choose a valid fuel type.';
    if ($old['transmission'] !== '' && !in_array($old['transmission'], $transmissions, true)) $errors[] = 'Choose a valid transmission.';
    if (!valid_county($old['county'])) $errors[] = 'Choose a valid county.';

    $old['vin'] = normalize_vin($old['vin']);
    if (!valid_vin($old['vin'])) $errors[] = 'VIN must be 17 characters and cannot contain I, O or Q.';
    $old['reg'] = strtoupper($old['reg']);

    if (!$errors) {
        $id = db_exec(
            'INSERT INTO listings (user_id, make, model, year, reg, vin, mileage_km, price_eur,
                                   fuel, transmission, colour, county, description, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $u['id'], $old['make'], $old['model'], $year,
                $old['reg'] ?: null, $old['vin'] ?: null,
                $mileage, $price,
                $old['fuel'] ?: null, $old['transmission'] ?: null,
                $old['colour'] ?: null, $old['county'] ?: null,
                $old['description'] ?: null, 'draft',
            ]
        );
        flash('Draft created. Upload at least one core vehicle document, then publish when you are ready.');
        redirect('documents.php?listing=' . $id);
    }
}

page_header('Sell your car', 'sell');
?>
<div class="wrap section-tight">
  <h1>Sell your car</h1>
  <p class="muted" style="max-width:60ch">Create the car as a private draft, add its documents, then publish it.
    A listing cannot go live until at least one core document file has been uploaded.</p>

  <?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="form-card">
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field">
          <label for="make">Make</label>
          <input type="text" id="make" name="make" value="<?= e($old['make']) ?>" placeholder="e.g. Toyota" required>
        </div>
        <div class="field">
          <label for="model">Model</label>
          <input type="text" id="model" name="model" value="<?= e($old['model']) ?>" placeholder="e.g. Corolla Hybrid" required>
        </div>
        <div class="field">
          <label for="year">Year</label>
          <input type="number" id="year" name="year" value="<?= e($old['year']) ?>" min="1980" max="<?= date('Y') + 1 ?>" required>
        </div>
        <div class="field">
          <label for="reg">Registration</label>
          <input type="text" id="reg" name="reg" value="<?= e($old['reg']) ?>" placeholder="e.g. 191-D-12345">
        </div>
        <div class="field">
          <label for="mileage_km">Mileage (km)</label>
          <input type="number" id="mileage_km" name="mileage_km" value="<?= e($old['mileage_km']) ?>" min="0">
        </div>
        <div class="field">
          <label for="price_eur">Asking price (€)</label>
          <input type="number" id="price_eur" name="price_eur" value="<?= e($old['price_eur']) ?>" min="1" required>
        </div>
        <div class="field">
          <label for="fuel">Fuel</label>
          <select id="fuel" name="fuel">
            <option value="">—</option>
            <?php foreach (['Petrol','Diesel','Hybrid','Plug-in Hybrid','Electric'] as $f): ?>
              <option <?= $old['fuel']===$f?'selected':'' ?>><?= $f ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="transmission">Transmission</label>
          <select id="transmission" name="transmission">
            <option value="">—</option>
            <?php foreach (['Manual','Automatic'] as $t): ?>
              <option <?= $old['transmission']===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="colour">Colour</label>
          <input type="text" id="colour" name="colour" value="<?= e($old['colour']) ?>">
        </div>
        <div class="field">
          <label for="county">County</label>
          <select id="county" name="county">
            <option value="">—</option>
            <?php foreach (COUNTIES as $c): ?>
              <option <?= $old['county']===$c?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field span-2">
          <label for="vin">VIN (optional — lets buyers run their own history check)</label>
          <input type="text" id="vin" name="vin" value="<?= e($old['vin']) ?>" maxlength="17">
        </div>
        <div class="field span-2">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="Ownership history, why you're selling, anything a serious buyer would want to know."><?= e($old['description']) ?></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Create draft → add documents</button>
      </div>
    </form>
  </div>
</div>
<?php page_footer(); ?>
