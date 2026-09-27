<?php
require_once __DIR__ . '/../src/layout.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$old = ['name'=>'','email'=>'','county'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['name']   = trim($_POST['name'] ?? '');
    $old['email']  = strtolower(trim($_POST['email'] ?? ''));
    $role          = 'private';
    $old['county'] = $_POST['county'] ?? '';
    $password      = $_POST['password'] ?? '';

    if ($old['name'] === '')                                  $errors[] = 'Name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))    $errors[] = 'A valid email is required.';
    if (strlen($password) < 8)                                $errors[] = 'Password must be at least 8 characters.';
    if (!valid_county($old['county']))                          $errors[] = 'Choose a valid county.';
    if (db_row('SELECT id FROM users WHERE email = ?', [$old['email']])) $errors[] = 'That email is already registered.';

    if (!$errors) {
        $id = db_exec(
            'INSERT INTO users (name, email, password_hash, role, county) VALUES (?,?,?,?,?)',
            [$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $role, $old['county'] ?: null]
        );
        login_user($id);
        flash('Welcome to Safe Trade, ' . explode(' ', $old['name'])[0] . '.');
        redirect('index.php');
    }
}

page_header('Create account');
?>
<div class="wrap">
  <div class="form-card auth-card">
    <h1>Create account</h1>
    <p class="muted small">Create a private account to buy and sell. Mechanic and dealer access is granted separately after verification.</p>

    <?php foreach ($errors as $err): ?>
      <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="field" style="margin-bottom:14px">
        <label for="name">Full name</label>
        <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" required>
      </div>
      <div class="field" style="margin-bottom:14px">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
      </div>
      <div class="field" style="margin-bottom:14px">
        <label for="password">Password (8+ characters)</label>
        <input type="password" id="password" name="password" minlength="8" required>
      </div>
      <div class="field" style="margin-bottom:14px">
        <label for="county">County</label>
        <select id="county" name="county">
          <option value="">— Select —</option>
          <?php foreach (COUNTIES as $c): ?>
            <option <?= $old['county']===$c ? 'selected':'' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Create account</button>
        <a class="small" href="login.php">Already registered? Sign in</a>
      </div>
    </form>
  </div>
</div>
<?php page_footer(); ?>
