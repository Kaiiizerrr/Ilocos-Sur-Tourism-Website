# Ilocos Sur Tourism Portal

A functioning tourism portal for the province of Ilocos Sur with real database
interactions: visitors browse ten tourism categories and complete a
registration / booking transaction (traveler profile, hotel reservation, or
trip plan) that is validated, persisted, and reflected back on a confirmation
page.

## Quick start (XAMPP)

1. Install/open **XAMPP** and start **Apache** and **MySQL** from the control panel.
   You can download XAMPP at it's official website: 
   ```
   https://www.apachefriends.org/
   ```

2. After cloning the repository into your local device, place
   the whole **`WebDev Final Project`** folder into XAMPP's **`htdocs`**
   directory.
3. In your browser, open the setup check once:

   ```
   http://localhost/WebDev_Final_Project/setup.php
   ```

   It connects to MySQL, creates the `ilocos_sur` database + tables, seeds the
   catalog (10 categories, 50 items) from `seed_data.json`, and shows a green
   "ready" panel. (Optional step)
4. Launch the site:

   ```
   http://localhost/WebDev_Final_Project/
   ```

> Bootstrap and the web fonts load from their CDNs, so an internet connection is
> needed for full styling.

### Don't want the auto-setup? Import the SQL instead

Open **phpMyAdmin** (from the XAMPP control panel → MySQL → *Admin*), go to the
**Import** tab, choose **`sql/schema.sql`**, and click **Go**. That creates the
database, the tables, and the full catalog seed in one shot. Re-importing is
safe (it uses `CREATE ... IF NOT EXISTS` and `INSERT IGNORE`).

## How it works

The browser loads the single HTML file at the site root (served by `index.php`,
mirroring Flask's old `/` route). Its hash router renders the homepage, each
category page, the registration form, and the confirmation page. Five `api.*`
methods issue real `fetch()` requests to the PHP backend. Because Apache serves
both the page and the API, every call is same-origin (no CORS needed).

The registration transaction follows the same flow: the form submits via an
AJAX `POST` (no page reload); the PHP backend validates the payload and inserts
a row into MySQL; on success the UI routes to `#/confirmation/<id>`, which
issues a follow-up AJAX `GET` to fetch the newly created record and render it.

`.htaccess` rewrites every `api/...` request to `api/index.php`, which is how
the clean URLs keep working on Apache.

### Project layout

```
WebDev Final Project/
├── index.php                     Serves the single-page frontend (Flask "/" equivalent)
├── .htaccess                     Apache routing: /api/* -> api/index.php (+ SPA fallback)
├── setup.php                     One-click MySQL setup + health check page
├── api/
│   ├── index.php                 JSON API front controller (was app.py routes)
│   ├── config.php                MySQL connection settings (PDO) + auto-create DB
│   ├── db.php                    Database class: schema, seeding, reads/writes (was db.py)
│   ├── validation.php            Registration rules, 1:1 port (was validation.py)
│   ├── helpers.php               JSON response helpers
│   └── .htaccess                 Blocks direct access to the include-only files
├── sql/
│   └── schema.sql                Optional phpMyAdmin import (DDL + full catalog seed)
├── seed_data.json                Catalog source (single source of truth)
├── frontend/
│   └── ilocos-sur-tourism.html   The site (markup/CSS/router unchanged)
├── app-images/                   Media; drop real photos here
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

