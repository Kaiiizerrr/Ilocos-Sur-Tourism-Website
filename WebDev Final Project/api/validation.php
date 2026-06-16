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
