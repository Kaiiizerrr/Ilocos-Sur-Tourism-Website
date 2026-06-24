<?php
/* api/index.php — JSON API front controller for the Ilocos Sur Tourism Portal */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Defence-in-depth HTTP headers on every API response, then start the hardened
// session so we know who (if anyone) is logged in and have a CSRF token ready.
send_api_security_headers();
auth_start_session();

// --- Work out the route segments after ".../api/"
// Subfolder-agnostic: works whether the project is at the web root or under a
// folder like /WebDev_Final_Project/. We slice the path at the "/api/" marker.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$marker = '/api/';
$pos = strpos($path, $marker);
$rest = $pos === false ? '' : substr($path, $pos + strlen($marker));
$rest = trim((string)$rest, '/');
$segments = $rest === '' ? [] : array_map('rawurldecode', explode('/', $rest));

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// CSRF protection: every state-changing request (anything that isn't a safe
// read) must carry a valid X-CSRF-Token header matching the session token.
// The frontend fetches GET /api/csrf once on load and attaches the token to
// every POST it sends.
$isWrite = !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
if ($isWrite && !csrf_verify()) {
    json_error('Invalid or missing CSRF token. Reload the page and try again.', 403);
}

// Routes that need no database — respond immediately so the page can boot
// even when MySQL is slow to start.

// GET /api/csrf  -> { csrf_token }
if ($segments === ['csrf'] && $method === 'GET') {
    json_response(['csrf_token' => csrf_token()]);
}

// GET /api/auth/me  -> the logged-in user (session only, no DB needed).
if ($segments === ['auth', 'me'] && $method === 'GET') {
    json_response(['user' => current_user()]);
}

// All remaining routes need the database.
try {
    $db = new Database();
} catch (Throwable $e) {
    json_error(
        'Database unavailable. In XAMPP, start the MySQL module, then reload. ('
        . $e->getMessage() . ')',
        500
    );
}

// Dispatch
try {
    // GET /api/health  -> row counts (handy sanity check)
    if ($segments === ['health'] && $method === 'GET') {
        json_response([
            'status'        => 'ok',
            'categories'    => count($db->listCategories()),
            'registrations' => $db->countRegistrations(),
            'authenticated' => is_authenticated(),
        ]);
    }

    // GET /api/csrf  -> already handled above, won't reach here.
    // GET /api/auth/me  -> already handled above, won't reach here.

    // ----- Account system -------------------------------------------------

    if (isset($segments[0]) && $segments[0] === 'auth') {

        // POST /api/auth/register  -> create account, log in, return user.
        if ($segments === ['auth', 'register'] && $method === 'POST') {
            $payload = read_json_body();
            [$cleaned, $errors] = validate_signup($payload);
            if (!empty($errors)) {
                json_response(['errors' => $errors], 422);
            }
            if ($db->emailExists($cleaned['email'])) {
                json_response(['errors' => ['email' => 'An account with this email already exists.']], 422);
            }
            $user = $db->createUser($cleaned);
            auth_login($user);
            json_response(['user' => current_user()], 201);
        }

        // POST /api/auth/login  -> verify credentials, start session.
        if ($segments === ['auth', 'login'] && $method === 'POST') {
            $payload = read_json_body();
            [$cleaned, $errors] = validate_login($payload);
            if (!empty($errors)) {
                json_response(['errors' => $errors], 422);
            }
            $user = $db->verifyLogin($cleaned['email'], $cleaned['password']);
            if ($user === null) {
                // Generic message: don't reveal whether the email exists.
                json_response(['errors' => ['_server' => 'Incorrect email or password.']], 401);
            }
            auth_login($user);
            json_response(['user' => current_user()]);
        }

        // POST /api/auth/logout  -> end the session.
        if ($segments === ['auth', 'logout'] && $method === 'POST') {
            auth_logout();
            json_response(['user' => null]);
        }

        // POST /api/auth/reset-password  -> update password for a matching email.
        if ($segments === ['auth', 'reset-password'] && $method === 'POST') {
            $payload = read_json_body();
            $email   = trim((string)($payload['email']        ?? ''));
            $newPw   =      (string)($payload['new_password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(['errors' => ['email' => 'Please enter a valid email address.']], 422);
            }
            if (strlen($newPw) < 8) {
                json_response(['errors' => ['new_password' => 'Password must be at least 8 characters.']], 422);
            }
            $ok = $db->resetPassword($email, $newPw);
            if (!$ok) {
                json_error('No account found with that email address.', 404);
            }
            json_response(['success' => true]);
        }
    }

    // ----- Itinerary booking system + history -----------------------------

    if (isset($segments[0]) && $segments[0] === 'bookings') {
        // All booking routes require a logged-in user.
        $user = current_user();
        if ($user === null) {
            json_error('Please log in to manage bookings.', 401);
        }

        // POST /api/bookings  -> create an itinerary booking for this user.
        if (count($segments) === 1 && $method === 'POST') {
            $payload = read_json_body();
            [$cleaned, $errors] = validate_booking($payload, $db->allItemsMap());
            if (!empty($errors)) {
                json_response(['errors' => $errors], 422);
            }
            $booking = $db->createBooking($user['id'], $cleaned);
            json_response($booking, 201);
        }

        // GET /api/bookings  -> this user's booking history (newest first).
        if (count($segments) === 1 && $method === 'GET') {
            json_response(['bookings' => $db->listBookings($user['id'])]);
        }

        // GET /api/bookings/<id>  -> one of this user's bookings (with items).
        if (count($segments) === 2 && $method === 'GET') {
            $booking = $db->getBooking($user['id'], $segments[1]);
            if ($booking === null) {
                json_error('Booking not found', 404);
            }
            json_response($booking);
        }

        // DELETE /api/bookings/<id>  -> cancel one of this user's bookings.
        if (count($segments) === 2 && $method === 'DELETE') {
            $deleted = $db->deleteBooking($user['id'], $segments[1]);
            if (!$deleted) {
                json_error('Booking not found', 404);
            }
            json_response(['deleted' => true, 'id' => $segments[1]]);
        }
    }

    // GET /api/categories  -> list the ten categories
    if ($segments === ['categories'] && $method === 'GET') {
        json_response($db->listCategories());
    }

    // /api/categories/<slug>/items[/<id>]
    if (count($segments) >= 3 && $segments[0] === 'categories' && $segments[2] === 'items') {
        $slug = $segments[1];

        // GET /api/categories/<slug>/items  -> {category, items}
        if (count($segments) === 3 && $method === 'GET') {
            $category = $db->getCategory($slug);
            if ($category === null) {
                json_error('Unknown category', 404);
            }
            json_response([
                'category' => $category,
                'items'    => $db->getItems($slug),
            ]);
        }

        // GET /api/categories/<slug>/items/<id>  -> one item
        if (count($segments) === 4 && $method === 'GET') {
            if ($db->getCategory($slug) === null) {
                json_error('Unknown category', 404);
            }
            $item = $db->getItem($slug, $segments[3]);
            if ($item === null) {
                json_error('Item not found', 404);
            }
            json_response($item);
        }
    }

    // /api/registrations  (POST create)  and  /api/registrations/<id>  (GET read)
    // Retained for backward compatibility with the original traveler-profile
    // flow; the new itinerary booking system above supersedes it for trips.
    if (isset($segments[0]) && $segments[0] === 'registrations') {

        // POST /api/registrations  -> validate, insert, return record
        if (count($segments) === 1 && $method === 'POST') {
            $payload = read_json_body();
            $validSlugs = array_map(
                static fn(array $c): string => (string)$c['slug'],
                $db->listCategories()
            );
            [$cleaned, $errors] = validate_registration($payload, $validSlugs);
            if (!empty($errors)) {
                // 422 Unprocessable Entity; field-keyed messages render inline.
                json_response(['errors' => $errors], 422);
            }
            $record = $db->createRegistration($cleaned);
            json_response($record, 201);
        }

        // GET /api/registrations/<id>  -> read back for the confirmation page
        if (count($segments) === 2 && $method === 'GET') {
            $record = $db->getRegistration($segments[1]);
            if ($record === null) {
                json_error('Registration not found', 404);
            }
            json_response($record);
        }
    }

    // Nothing matched.
    json_error('Not found', 404);

} catch (Throwable $e) {
    // Any unexpected server/SQL error -> JSON 500 (frontend shows the message).
    json_error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Read and JSON-decode the request body for POSTs. Returns [] on empty/invalid
 * input (mirrors Flask's request.get_json(silent=True) or {}).
 *
 * @return array<string,mixed>
 */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
