<?php
/**
 * helpers.php — tiny JSON response helpers shared by the API router.
 * Mirrors Flask's jsonify(...) + status handling.
 */

declare(strict_types=1);

/**
 * Send a JSON body with an HTTP status code and stop.
 * JSON_UNESCAPED_SLASHES keeps image paths like "app-images/x.jpg" readable and
 * byte-identical to what the old Flask/SQLite backend returned.
 */
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a JSON error envelope: {"error": <message>, "status": <code>}.
 * Same shape the frontend's apiRequest() reads for non-validation failures.
 */
function json_error(string $message, int $status): void
{
    json_response(['error' => $message, 'status' => $status], $status);
}

/**
 * Generate a random 6-digit numeric verification code (zero-padded), e.g.
 * "048213". Used by the forgot-password flow.
 */
function generate_reset_code(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * "Send" a password-reset code to an email address.
 *   - If MAIL_ENABLED, attempt real delivery with PHP mail() (errors are
 *     suppressed so a missing mail server never breaks the request).
 *   - If MAIL_DEV_MODE, append the code to storage/password-reset-codes.log so
 *     it can be retrieved on localhost without a configured mail server.
 * This function never throws; delivery is best-effort.
 */
function send_reset_email(string $to, string $code): void
{
    $minutes = (int)round(RESET_CODE_TTL_SECONDS / 60);
    $subject = 'Your Ilocos Sur Tourism Portal verification code';
    $body =
        "Hello,\n\n" .
        "We received a request to reset the password for your Ilocos Sur Tourism Portal account.\n\n" .
        "Your verification code is: {$code}\n\n" .
        "This code expires in {$minutes} minutes. If you did not request a password reset, " .
        "you can safely ignore this email and your password will stay the same.\n";

    if (MAIL_ENABLED) {
        $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";
        @mail($to, $subject, $body, $headers);
    }

    if (MAIL_DEV_MODE) {
        $dir = __DIR__ . '/../storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = '[' . gmdate('Y-m-d\TH:i:s\Z') . "] to={$to} code={$code}\n";
        @file_put_contents($dir . '/password-reset-codes.log', $line, FILE_APPEND | LOCK_EX);
    }
}
