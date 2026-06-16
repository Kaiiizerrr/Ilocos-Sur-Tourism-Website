<?php
/* api/index.php — JSON API front controller for the Ilocos Sur Tourism Portal */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/db.php';

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

// Open the database
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
        ]);
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
