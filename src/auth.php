<?php
/**
 * Safe Trade — sessions, current user, role gates, CSRF.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

/** The logged-in user row, or null. Cached per request. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = isset($_SESSION['user_id'])
            ? db_row('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']])
            : null;
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): ?string
{
    return current_user()['role'] ?? null;
}

/** Redirect to login (remembering where we were) unless signed in. */
function require_login(): array
{
    $u = current_user();
    if ($u === null) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: login.php?next=' . $next);
        exit;
    }
    return $u;
}

/** Require a specific role (e.g. 'dealer' for bidding). */
function require_role(string $role): array
{
    $u = require_login();
    if ($u['role'] !== $role) {
        flash("That area is for {$role} accounts.", 'warn');
        header('Location: index.php');
        exit;
    }
    return $u;
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

/** Hidden input for forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/** Call at the top of every POST handler. */
function csrf_check(): void
{
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid request token. Go back and try again.');
    }
}
