<?php
require_once __DIR__ . '/../src/layout.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$next  = $_GET['next'] ?? $_POST['next'] ?? 'dashboard.php';
// Only allow same-site relative redirects.
if (!preg_match('~^[a-z0-9_\-]+\.php(\?.*)?$~i', $next)) {
    $next = 'dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $user  = db_row('SELECT * FROM users WHERE email = ?', [$email]);

    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        login_user((int)$user['id']);
        redirect($next);
    }
    $error = 'Email or password not recognised.';
}

page_header('Sign in');
?>
<div class="wrap">
  <div class="form-card auth-card">
    <h1>Sign in</h1>

    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="field" style="margin-bottom:14px">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus>
      </div>
      <div class="field" style="margin-bottom:14px">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Sign in</button>
        <a class="small" href="register.php">New here? Create an account</a>
      </div>
    </form>

    <p class="small muted" style="margin-top:20px">
      Demo accounts all use the password <span class="mono">password123</span> —
      try <span class="mono">aoife@example.com</span> (seller),
      <span class="mono">dara@example.com</span> (mechanic) or
      <span class="mono">dealer@example.com</span> (dealer).
    </p>
  </div>
</div>
<?php page_footer(); ?>
