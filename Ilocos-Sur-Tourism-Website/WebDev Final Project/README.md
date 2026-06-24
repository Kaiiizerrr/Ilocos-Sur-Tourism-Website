# Ilocos Sur Tourism Portal

A functioning tourism portal for the province of Ilocos Sur with real database
interactions and a full **account + itinerary booking** workflow:

- Visitors browse ten tourism categories (food, beaches, churches, heritage
  sites, festivals, and more), each loaded over AJAX from a PHP/MySQL backend.
- Visitors **create an account** (name, email, password) and **log in**.
- Logged-in users build an **itinerary** — name the trip, pick attractions from
  the catalog, set a date and party size — and **book** it (saved to MySQL).
- Each user has a private **booking history** they can revisit any time.
- The whole thing is hardened with the web-security measures listed below.

The frontend design, layout, and images are unchanged from the original
prototype; only the application logic (accounts, bookings, security) was added.

## Quick start (XAMPP)

1. Install/open **XAMPP** and start **Apache** and **MySQL** from the control panel.
   You can download XAMPP at its official website:
   ```
   https://www.apachefriends.org/
   ```
2. Place the whole **`WebDev Final Project`** folder into XAMPP's **`htdocs`**
   directory.
3. (Optional) In your browser, open the setup check once:
   ```
   http://localhost/WebDev_Final_Project/setup.php
   ```
   It connects to MySQL, creates the `ilocos_sur` database + tables, seeds the
   catalog from `seed_data.json`, and shows a green "ready" panel.
4. Launch the site:
   ```
   http://localhost/WebDev_Final_Project/
   ```

> Bootstrap and the web fonts load from their CDNs, so an internet connection is
> needed for full styling.

### Prefer to import the SQL by hand?

Open **phpMyAdmin** (XAMPP → MySQL → *Admin*) → **Import** tab → choose
**`sql/schema.sql`** → **Go**. That creates the database, every table
(including the new `users`, `bookings`, and `booking_items` tables), and the
full catalog seed. Re-importing is safe (`CREATE ... IF NOT EXISTS`,
`INSERT IGNORE`).

## How it works

The browser loads the single HTML file at the site root (served by `index.php`).
Its hash router renders the homepage, the category pages, and the new
account/itinerary/booking pages. The frontend talks to the PHP backend only
through `fetch()` AJAX calls; because Apache serves both the page and the API,
every call is same-origin (no CORS needed). `.htaccess` rewrites every
`api/...` request to `api/index.php`.

### Communication method

The app uses **two-way (request/response) AJAX communication**: the browser
issues asynchronous `GET`/`POST` JSON requests and the PHP backend replies with
JSON, which the page reflects without ever reloading.

### API routes

```
GET  /api/csrf                          -> { csrf_token }
GET  /api/health                        -> row counts + auth flag
GET  /api/categories                    -> the ten categories
GET  /api/categories/<slug>/items       -> { category, items }
GET  /api/categories/<slug>/items/<id>  -> one item

POST /api/auth/register                 -> create account + log in
POST /api/auth/login                    -> log in
POST /api/auth/logout                   -> log out
GET  /api/auth/me                       -> the current user (or null)

POST /api/bookings                      -> create an itinerary booking (auth)
GET  /api/bookings                      -> the user's booking history (auth)
GET  /api/bookings/<id>                 -> one of the user's bookings (auth)
```

## Web security

This build adds layered protections:

- **XSS prevention.** All API responses are JSON with a strict
  `Content-Security-Policy: default-src 'none'`, and every dynamic value the
  frontend injects into the DOM is HTML-escaped (`esc()`) or set with
  `textContent`. User-supplied itinerary items are re-resolved against the
  trusted catalog on the server, so only real attraction names are ever stored
  or echoed back.
- **SQL-injection prevention.** Every database query uses PDO **prepared
  statements** with bound parameters — no string-built SQL.
- **CSRF protection.** A per-session token is issued at `GET /api/csrf` and must
  accompany every state-changing request in the `X-CSRF-Token` header
  (`hash_equals()` comparison), backed by a `SameSite=Lax` session cookie.
- **Secure authentication.** Passwords are stored only as bcrypt hashes
  (`password_hash` / `password_verify`); login failures return one generic
  message to avoid user enumeration; the session id is regenerated on login and
  logout to defeat session fixation.
- **Hardened session cookies.** `HttpOnly`, `SameSite=Lax`, and `Secure`
  (auto-enabled on HTTPS).
- **Security headers.** `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: DENY`, and `Referrer-Policy: no-referrer` on every API
  response.
- **Access control.** A booking is always scoped to its owner's `user_id`, so
  one user can never read another user's bookings; the include-only PHP
  libraries are blocked from direct web access by `api/.htaccess`.

### Project layout

```
WebDev Final Project/
├── index.php                     Serves the single-page frontend
├── .htaccess                     Apache routing: /api/* -> api/index.php (+ SPA fallback)
├── setup.php                     One-click MySQL setup + health check page
├── api/
│   ├── index.php                 JSON API front controller (routes + CSRF gate)
│   ├── config.php                MySQL (PDO) settings + security-headers helper
│   ├── auth.php                  Sessions, CSRF tokens, login/logout helpers
│   ├── db.php                    Database class: schema, seeding, users, bookings
│   ├── validation.php            Server-side validation (signup/login/booking)
│   ├── helpers.php               JSON response helpers
│   └── .htaccess                 Blocks direct access to the include-only files
├── sql/
│   ├── schema.sql                Optional phpMyAdmin import (DDL + full catalog seed)
│   └── reset_catalog.sql         Emergency catalog reload
├── seed_data.json                Catalog source (single source of truth)
├── frontend/
│   └── ilocos-sur-tourism.html   The site (design/markup/CSS unchanged)
├── app-images/                   Media
```

## Configuration

Defaults in `api/config.php` match a stock XAMPP install:

```php
DB_HOST = '127.0.0.1';   // TCP, avoids socket-path surprises
DB_NAME = 'ilocos_sur';
DB_USER = 'root';
DB_PASS = '';            // XAMPP's MySQL root has no password by default
```

## Media

Every attraction, dish, stay, and transport option renders with an image.
All images are stored in the `app-images` folder.
