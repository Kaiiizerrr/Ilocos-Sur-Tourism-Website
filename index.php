<?php
/**
 * index.php — serves the single-page frontend at the project root.
 *
 * This mirrors the old Flask "/" route: it streams the unchanged
 * frontend/ilocos-sur-tourism.html so the page's base URL is this project
 * folder. That keeps the HTML's relative asset paths (e.g. "app-images/...")
 * and the AJAX calls ("/api/..." rewritten to this folder) all resolving
 * correctly whether the project sits at the web root or in a subfolder under
 * XAMPP's htdocs (e.g. http://localhost/WebDev_Final_Project/).
 *
 * The HTML itself is byte-for-byte the original design — only the AJAX
 * transport learned to find the API under a subfolder. The look is untouched.
 */

declare(strict_types=1);

$frontend = __DIR__ . '/frontend/ilocos-sur-tourism.html';

if (!is_file($frontend)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Frontend file not found: frontend/ilocos-sur-tourism.html";
    exit;
}

header('Content-Type: text/html; charset=utf-8');
readfile($frontend);
