<?php
/**
 * auth.php — session bootstrap, CSRF protection, and authentication helpers
 * for the Ilocos Sur Tourism Portal.
 *
 * This file is an include-only library (blocked from direct web access by
 * api/.htaccess). It is required by the API front controller before any route
 * is dispatched so that every request runs inside a hardened session and so
 * that state-changing requests (POST) can be checked for a valid CSRF token.
 *
 * Security features implemented here:
 *   - Hardened session cookies (HttpOnly, SameSite=Lax, Secure when on HTTPS).
 *   - Per-session CSRF token (double-submit: token is read from the
 *     X-CSRF-Token request header and compared with hash_equals()).
 *   - Helpers to read the logged-in user, require authentication, and to
 *     log a user in/out (with session-id regeneration to prevent fixation).
 */

declare(strict_types=1);

/**
 * Start the PHP session with security-conscious cookie settings.
 * Called once, early, by the API front controller. Safe to call repeatedly.
 */
function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Detect HTTPS so the Secure flag is set only when it actually applies
    // (XAMPP over plain http://localhost still works; a real https host gets
    // the stricter cookie automatically).
    $https = (
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
    );

    session_set_cookie_params([
        'lifetime' => 0,          // session cookie (cleared when browser closes)
        'path'     => '/',
        'httponly' => true,       // JS cannot read the cookie -> mitigates XSS theft
        'secure'   => $https,     // only sent over HTTPS when available
        'samesite' => 'Lax',      // mitigates CSRF on top of the token check
    ]);
    session_name('ILOCOS_SUR_SID');
    session_start();

    // Make sure a CSRF token exists for this session.
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/** Return the current session CSRF token (creating one if needed). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

/**
 * Verify the CSRF token sent by the client against the session token.
 * The frontend sends it in the "X-CSRF-Token" header on every POST.
 * Uses hash_equals() to avoid timing attacks.
 */
function csrf_verify(): bool
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || $sent === '') {
        return false;
    }
    $known = (string)($_SESSION['csrf_token'] ?? '');
    return $known !== '' && hash_equals($known, $sent);
}

/**
 * Log a user in: store the minimal identity in the session and regenerate the
 * session id to prevent session-fixation attacks.
 *
 * @param array<string,mixed> $user A user row (must include id, full_name, email).
 */
function auth_login(array $user): void
{
    // New session id on privilege change (login) — classic fixation defense.
    session_regenerate_id(true);
    $_SESSION['user_id']    = (string)$user['id'];
    $_SESSION['user_name']  = (string)$user['full_name'];
    $_SESSION['user_email'] = (string)$user['email'];
}

/** Destroy the authenticated session (logout) but keep a fresh CSRF token. */
function auth_logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * The currently logged-in user as a small public array, or null if no one is
 * logged in. Never exposes the password hash.
 *
 * @return array{id:string,full_name:string,email:string}|null
 */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'        => (string)$_SESSION['user_id'],
        'full_name' => (string)($_SESSION['user_name'] ?? ''),
        'email'     => (string)($_SESSION['user_email'] ?? ''),
    ];
}

/** True when someone is logged in. */
function is_authenticated(): bool
{
    return !empty($_SESSION['user_id']);
}
