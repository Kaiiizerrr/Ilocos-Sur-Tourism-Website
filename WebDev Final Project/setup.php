<?php
/**
 * setup.php — one-click setup & health check (open this once in your browser)
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/api/config.php';

$ok = false;
$error = '';
$counts = ['categories' => 0, 'items' => 0, 'registrations' => 0];

try {
    require_once __DIR__ . '/api/db.php';
    $db = new Database();                 // creates DB + tables + seeds if empty
    $cats = $db->listCategories();
    $itemTotal = 0;
    foreach ($cats as $c) {
        $itemTotal += count($db->getItems((string)$c['slug']));
    }
    $counts = [
        'categories'    => count($cats),
        'items'         => $itemTotal,
        'registrations' => $db->countRegistrations(),
    ];
    $ok = true;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// Work out a link back to the app root (this file's directory).
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup — Ilocos Sur Tourism Portal</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
           background:#f7f3ec; color:#2b2622; margin:0; padding:2.5rem 1rem; }
    .card { max-width:640px; margin:0 auto; background:#fff; border:1px solid #e7ded0;
            border-radius:14px; padding:1.75rem 2rem; box-shadow:0 10px 30px rgba(0,0,0,.06); }
    h1 { font-size:1.4rem; margin:0 0 .25rem; }
    .sub { color:#7a7065; margin:0 0 1.25rem; font-size:.95rem; }
    .badge { display:inline-block; padding:.3rem .7rem; border-radius:999px; font-weight:600; font-size:.85rem; }
    .ok { background:#e6f4ea; color:#1e7a3d; }
    .bad { background:#fdecec; color:#b3261e; }
    table { width:100%; border-collapse:collapse; margin:1.25rem 0; }
    td { padding:.5rem .25rem; border-bottom:1px solid #efe8dc; }
    td.n { text-align:right; font-variant-numeric:tabular-nums; font-weight:600; }
    code { background:#f0eadf; padding:.1rem .35rem; border-radius:6px; }
    a.btn { display:inline-block; margin-top:.5rem; background:#1f6f6b; color:#fff;
            text-decoration:none; padding:.6rem 1.1rem; border-radius:10px; font-weight:600; }
    .err { background:#fdecec; border:1px solid #f5c6c2; color:#7a1b16;
           padding:.85rem 1rem; border-radius:10px; white-space:pre-wrap; font-size:.9rem; }
    ul { margin:.5rem 0 0; padding-left:1.2rem; } li { margin:.25rem 0; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Ilocos Sur Tourism Portal — Setup</h1>
    <p class="sub">MySQL initialization &amp; health check for XAMPP.</p>

    <?php if ($ok): ?>
      <span class="badge ok">&#10003; MySQL connected &amp; database ready</span>
      <table>
        <tr><td>Database</td><td class="n"><code><?= htmlspecialchars(DB_NAME) ?></code></td></tr>
        <tr><td>Categories</td><td class="n"><?= (int)$counts['categories'] ?></td></tr>
        <tr><td>Items</td><td class="n"><?= (int)$counts['items'] ?></td></tr>
        <tr><td>Registrations</td><td class="n"><?= (int)$counts['registrations'] ?></td></tr>
      </table>
      <p>Everything is ready. Open the site:</p>
      <a class="btn" href="<?= htmlspecialchars($base) ?>">Launch the portal &rarr;</a>
    <?php else: ?>
      <span class="badge bad">&#10007; Could not connect to MySQL</span>
      <p style="margin-top:1rem">The app couldn't reach MySQL. Most often this is fixed by:</p>
      <ul>
        <li>Open the <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> on the <strong>MySQL</strong> module.</li>
        <li>Make sure Apache is also running and you're viewing this via <code>http://localhost/...</code> (not opening the file directly).</li>
        <li>If you set a MySQL root password, put it in <code>api/config.php</code> (<code>DB_PASS</code>).</li>
      </ul>
      <p style="margin-top:1rem">Details from MySQL:</p>
      <div class="err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
