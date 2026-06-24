<?php
/* validation.php — Registration payload validation */

declare(strict_types=1);

/**
 * Validate + clean a registration payload.
 *
 * @param array    $payload            Decoded JSON body from the AJAX POST.
 * @param string[] $validCategorySlugs Allowed category slugs (from the DB).
 * @return array{0: array<string,mixed>, 1: array<string,string>}
 *               [$cleaned, $errors]. $errors is empty when the payload is valid.
 */
function validate_registration(array $payload, array $validCategorySlugs): array
{
    $errors  = [];
    $cleaned = [];

    // full_name — required, at least 2 chars.
    $fullName = trim((string)($payload['full_name'] ?? ''));
    if (mb_strlen($fullName) < 2) {
        $errors['full_name'] = 'Please enter your full name.';
    }
    $cleaned['full_name'] = $fullName;

    // email — required, simple shape check (mirrors the frontend regex exactly).
    $email = trim((string)($payload['email'] ?? ''));
    if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    $cleaned['email'] = $email;

    // phone — optional, free text.
    $cleaned['phone'] = trim((string)($payload['phone'] ?? ''));

    // category — optional, but if present must be a known slug.
    $category = trim((string)($payload['category'] ?? ''));
    if ($category !== '' && !in_array($category, $validCategorySlugs, true)) {
        $errors['category'] = 'Please choose a valid category.';
    }
    $cleaned['category'] = $category;

    // attraction — optional, free text.
    $cleaned['attraction'] = trim((string)($payload['attraction'] ?? ''));

    // visit_date — optional, but if present must be a real YYYY-MM-DD date.
    $visit = trim((string)($payload['visit_date'] ?? ''));
    if ($visit !== '' && !valid_date($visit)) {
        $errors['visit_date'] = 'Please use a valid date.';
    }
    $cleaned['visit_date'] = $visit;

    // travelers — required, whole number 1..100. filter_var(FILTER_VALIDATE_INT)
    // rejects non-integers like "2.5" or "abc" (matching Python's int() / the
    // frontend's range check), returning false which we treat as "no value".
    $travelersStr = trim((string)($payload['travelers'] ?? ''));
    $parsed = filter_var($travelersStr, FILTER_VALIDATE_INT);
    $travelers = ($parsed === false) ? null : $parsed;
    if ($travelers === null || $travelers < 1 || $travelers > 100) {
        $errors['travelers'] = 'Travelers must be a number between 1 and 100.';
        $travelers = null;
    }
    $cleaned['travelers'] = $travelers;

    // notes — optional, free text.
    $cleaned['notes'] = trim((string)($payload['notes'] ?? ''));

    return [$cleaned, $errors];
}

/**
 * True when $value is a syntactically valid calendar date in YYYY-MM-DD form
 * (e.g. rejects "2026-13-40" and "15-08-2026"), matching the Python/JS checks.
 */
function valid_date(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $value));
    return checkdate($m, $d, $y);
}

/**
 * Validate + clean a sign-up (account creation) payload.
 *
 * @param array $payload Decoded JSON body from the AJAX POST.
 * @return array{0: array<string,mixed>, 1: array<string,string>} [$cleaned, $errors]
 */
function validate_signup(array $payload): array
{
    $errors  = [];
    $cleaned = [];

    // full_name — required, at least 2 chars.
    $fullName = trim((string)($payload['full_name'] ?? ''));
    if (mb_strlen($fullName) < 2) {
        $errors['full_name'] = 'Please enter your full name.';
    } elseif (mb_strlen($fullName) > 120) {
        $errors['full_name'] = 'That name is too long.';
    }
    $cleaned['full_name'] = $fullName;

    // email — required, simple shape check (mirrors the frontend regex).
    $email = trim((string)($payload['email'] ?? ''));
    if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email) || mb_strlen($email) > 254) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    $cleaned['email'] = $email;

    // password — required, at least 8 chars (keep server + client in sync).
    $password = (string)($payload['password'] ?? '');
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 200) {
        $errors['password'] = 'Password is too long.';
    }
    $cleaned['password'] = $password;

    return [$cleaned, $errors];
}

/**
 * Validate a login payload. We only check presence here; the real check is
 * verifyLogin() in the database, which returns a single generic failure.
 *
 * @return array{0: array<string,mixed>, 1: array<string,string>}
 */
function validate_login(array $payload): array
{
    $errors  = [];
    $cleaned = [
        'email'    => trim((string)($payload['email'] ?? '')),
        'password' => (string)($payload['password'] ?? ''),
    ];
    if ($cleaned['email'] === '') {
        $errors['email'] = 'Please enter your email.';
    }
    if ($cleaned['password'] === '') {
        $errors['password'] = 'Please enter your password.';
    }
    return [$cleaned, $errors];
}

/**
 * Validate + clean an itinerary booking payload.
 *
 * The chosen attractions are validated against the real catalog passed in
 * ($catalogItems maps item_id => ['name'=>..., 'category'=>...]); this means a
 * client cannot inject arbitrary names — every saved item is one that actually
 * exists in the database, and we store the server's copy of its name/category.
 *
 * @param array                                         $payload      Decoded JSON body.
 * @param array<string,array{name:string,category:string}> $catalogItems Allowed items.
 * @return array{0: array<string,mixed>, 1: array<string,string>} [$cleaned, $errors]
 */
function validate_booking(array $payload, array $catalogItems): array
{
    $errors  = [];
    $cleaned = [];

    // trip_title — required, 2..120 chars.
    $title = trim((string)($payload['trip_title'] ?? ''));
    if (mb_strlen($title) < 2) {
        $errors['trip_title'] = 'Please name your trip.';
    } elseif (mb_strlen($title) > 120) {
        $errors['trip_title'] = 'That trip name is too long.';
    }
    $cleaned['trip_title'] = $title;

    // visit_date — optional, but if present must be a real YYYY-MM-DD date.
    $visit = trim((string)($payload['visit_date'] ?? ''));
    if ($visit !== '' && !valid_date($visit)) {
        $errors['visit_date'] = 'Please use a valid date.';
    }
    $cleaned['visit_date'] = $visit;

    // travelers — required whole number 1..100.
    $parsed    = filter_var(trim((string)($payload['travelers'] ?? '')), FILTER_VALIDATE_INT);
    $travelers = ($parsed === false) ? null : $parsed;
    if ($travelers === null || $travelers < 1 || $travelers > 100) {
        $errors['travelers'] = 'Travelers must be a number between 1 and 100.';
        $travelers = null;
    }
    $cleaned['travelers'] = $travelers;

    // notes — optional free text, capped to keep the column sane.
    $cleaned['notes'] = mb_substr(trim((string)($payload['notes'] ?? '')), 0, 2000);

    // checkin_date — optional, but if present must be a valid YYYY-MM-DD date.
    $checkin = trim((string)($payload['checkin_date'] ?? ''));
    if ($checkin !== '' && !valid_date($checkin)) {
        $errors['checkin_date'] = 'Please use a valid check-in date.';
    }
    $cleaned['checkin_date'] = $checkin !== '' ? $checkin : null;

    // stay_days — optional integer 1..365 (number of nights at the hotel).
    $stayDaysStr = trim((string)($payload['stay_days'] ?? ''));
    if ($stayDaysStr !== '') {
        $stayDaysParsed = filter_var($stayDaysStr, FILTER_VALIDATE_INT);
        $stayDays = ($stayDaysParsed === false) ? null : (int)$stayDaysParsed;
        if ($stayDays === null || $stayDays < 1 || $stayDays > 365) {
            $errors['stay_days'] = 'Stay duration must be between 1 and 365 days.';
            $stayDays = null;
        }
        $cleaned['stay_days'] = $stayDays;
    } else {
        $cleaned['stay_days'] = null;
    }

    // items — required: at least one valid catalog item id. We rebuild each
    // entry from the trusted catalog, ignoring any name/category the client sent.
    $rawItems = $payload['items'] ?? [];
    $items    = [];
    if (is_array($rawItems)) {
        $seen = [];
        foreach ($rawItems as $entry) {
            $itemId = is_array($entry) ? (string)($entry['id'] ?? '') : (string)$entry;
            $itemId = trim($itemId);
            if ($itemId === '' || isset($seen[$itemId])) {
                continue;
            }
            if (isset($catalogItems[$itemId])) {
                $seen[$itemId] = true;
                $items[] = [
                    'id'       => $itemId,
                    'name'     => $catalogItems[$itemId]['name'],
                    'category' => $catalogItems[$itemId]['category'],
                ];
            }
        }
    }
    if (count($items) === 0) {
        $errors['items'] = 'Add at least one attraction to your itinerary.';
    } elseif (count($items) > 50) {
        $errors['items'] = 'That itinerary has too many stops (max 50).';
        $items = array_slice($items, 0, 50);
    }
    $cleaned['items'] = $items;

    return [$cleaned, $errors];
}
