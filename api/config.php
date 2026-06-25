<?php
/* config.php — MySQL connection settings for the Ilocos Sur Tourism Portal */

declare(strict_types=1);

const DB_HOST    = '127.0.0.1';
const DB_PORT    = 3306;
const DB_NAME    = 'ilocos_sur';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

/* ----- Password-reset / email-verification settings -----------------------
 * The "forgot password" flow emails a one-time 6-digit code that must be
 * entered before a new password is accepted, proving the requester controls
 * the inbox.
 *
 *   MAIL_ENABLED  When true, the code is sent with PHP's mail(). A default
 *                 XAMPP install has no mail server configured, so this is OFF
 *                 by default; configure XAMPP sendmail (or an SMTP relay) in
 *                 php.ini, then set this to true to send real email.
 *   MAIL_DEV_MODE Localhost convenience: when true, the code is also written to
 *                 storage/password-reset-codes.log AND returned to the page so
 *                 the reset can be demonstrated without a mail server.
 *                 *** Set to false in production — otherwise codes are exposed. ***
 */
const MAIL_ENABLED            = false;
const MAIL_DEV_MODE           = true;
const MAIL_FROM               = 'no-reply@ilocossur.local';
const MAIL_FROM_NAME          = 'Ilocos Sur Tourism Portal';
const RESET_CODE_TTL_SECONDS  = 900;   // verification code lifetime (15 minutes)
const RESET_MAX_ATTEMPTS      = 5;     // wrong-code guesses before a code is voided

/**
 * Emit a small, safe set of HTTP security headers shared by every API
 * response. These are defence-in-depth headers that pair with the app's
 * server-side output encoding and CSRF tokens:
 *
 *   - X-Content-Type-Options: nosniff   -> stop MIME-type sniffing.
 *   - X-Frame-Options: DENY             -> stop click-jacking via <iframe>.
 *   - Referrer-Policy                   -> don't leak full URLs cross-site.
 *   - Content-Security-Policy           -> the API only ever returns JSON, so
 *     a strict "default-src 'none'" is correct here and blocks any script/
 *     style execution if a response is ever mis-rendered as HTML.
 *
 * Note: the HTML frontend has its own (looser) needs because it loads
 * Bootstrap + Google Fonts from CDNs, so this strict CSP is applied only to
 * the JSON API, not to the page.
 */
function send_api_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
}

/**
 * Open a PDO connection to the MySQL *server* (no database selected yet).
 * Used once at setup time so we can CREATE DATABASE if it does not exist.
 */
function pdo_server(): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/**
 * Open a PDO connection to the project database (ilocos_sur).
 * Creates the database first if it is missing, so a fresh XAMPP install works
 * with zero manual setup — mirroring how the old SQLite file auto-created
 * itself on first run.
 */
function pdo_db(): PDO
{
    // Ensure the database exists (idempotent, cheap).
    $server = pdo_server();
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` '
        . 'CHARACTER SET ' . DB_CHARSET . ' COLLATE ' . DB_CHARSET . '_unicode_ci'
    );

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}
