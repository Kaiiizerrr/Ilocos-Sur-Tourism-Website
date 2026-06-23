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
