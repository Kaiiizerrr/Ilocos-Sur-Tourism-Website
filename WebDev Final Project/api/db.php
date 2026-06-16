<?php
/* db.php — Database interfacing for the Ilocos Sur Tourism Portal (MySQL) */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class Database
{
    private PDO $pdo;
    private string $seedPath;

    public function __construct()
    {
        $this->pdo      = pdo_db();
        $this->seedPath = __DIR__ . '/../seed_data.json';
        $this->initSchema();
        $this->seedCatalog();
    }

    // ----- schema + seed ---------------------------------------------------
    private function initSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS categories (
                slug        VARCHAR(64)  NOT NULL,
                title       VARCHAR(255) NOT NULL,
                tagline     TEXT,
                sort_order  INT          NOT NULL DEFAULT 0,
                PRIMARY KEY (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS items (
                id            VARCHAR(64)  NOT NULL,
                category_slug VARCHAR(64)  NOT NULL,
                name          VARCHAR(255) NOT NULL,
                location      VARCHAR(255),
                summary       TEXT,
                about         TEXT,
                tags_json     TEXT         NOT NULL,
                image         VARCHAR(512),
                sort_order    INT          NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_items_category (category_slug),
                CONSTRAINT fk_items_category FOREIGN KEY (category_slug)
                    REFERENCES categories(slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS registrations (
                id          CHAR(36)     NOT NULL,
                full_name   VARCHAR(255) NOT NULL,
                email       VARCHAR(255) NOT NULL,
                phone       VARCHAR(64),
                category    VARCHAR(64),
                attraction  VARCHAR(255),
                visit_date  VARCHAR(32),
                travelers   INT,
                notes       TEXT,
                created_at  VARCHAR(32)  NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Small key/value table used to remember which version of the seed file
        // the catalog was last loaded from (so we can auto-refresh on changes).
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS catalog_meta (
                meta_key    VARCHAR(64)  NOT NULL,
                meta_value  TEXT,
                PRIMARY KEY (meta_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Populate categories/items from seed_data.json.
     *
     * This now keeps the catalog in sync with the seed file: it stores a hash
     * of the file's contents in `catalog_meta` and, whenever that hash changes
     * (i.e. seed_data.json was edited), it wipes and reloads the catalog. On
     * loads where nothing changed it does a single cheap lookup and returns.
     * The `registrations` table is never touched, so form submissions survive.
     */
    private function seedCatalog(): void
    {
        if (!is_file($this->seedPath)) {
            return; // no seed file: leave the catalog empty rather than crash
        }
        $seedJson = (string)file_get_contents($this->seedPath);
        $seed     = json_decode($seedJson, true);
        if (!is_array($seed)) {
            return;
        }

        // If the catalog already matches this exact seed file, there's nothing
        // to do. (Hashing the raw file makes any edit trigger a refresh.)
        $seedHash   = hash('sha256', $seedJson);
        $storedHash = $this->getMeta('catalog_hash');
        if ($storedHash === $seedHash) {
            return;
        }

        $categories = $seed['categories'] ?? [];
        $items      = $seed['items'] ?? [];

        $this->pdo->beginTransaction();
        try {
            // Clear the old catalog first. Delete items (children) before
            // categories (parents) so the foreign key is always satisfied.
            $this->pdo->exec('DELETE FROM items');
            $this->pdo->exec('DELETE FROM categories');

            $catStmt = $this->pdo->prepare(
                'INSERT INTO categories (slug, title, tagline, sort_order)
                 VALUES (:slug, :title, :tagline, :sort_order)'
            );
            foreach ($categories as $order => $cat) {
                $catStmt->execute([
                    ':slug'       => $cat['slug'],
                    ':title'      => $cat['title'],
                    ':tagline'    => $cat['tagline'] ?? '',
                    ':sort_order' => $order,
                ]);
            }

            $itemStmt = $this->pdo->prepare(
                'INSERT INTO items
                    (id, category_slug, name, location, summary, about, tags_json, image, sort_order)
                 VALUES
                    (:id, :category_slug, :name, :location, :summary, :about, :tags_json, :image, :sort_order)'
            );
            foreach ($categories as $cat) {
                $slug = $cat['slug'];
                $list = $items[$slug] ?? [];
                foreach ($list as $order => $item) {
                    $itemStmt->execute([
                        ':id'            => $item['id'],
                        ':category_slug' => $slug,
                        ':name'          => $item['name'] ?? '',
                        ':location'      => $item['location'] ?? '',
                        ':summary'       => $item['summary'] ?? '',
                        ':about'         => $item['about'] ?? '',
                        ':tags_json'     => json_encode($item['tags'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ':image'         => $item['image'] ?? null, // null -> frontend placeholder
                        ':sort_order'    => $order,
                    ]);
                }
            }

            // Remember which seed file this catalog came from.
            $this->setMeta('catalog_hash', $seedHash);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /* Read a value from catalog_meta, or null if the key is absent. */
    private function getMeta(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT meta_value FROM catalog_meta WHERE meta_key = ?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : (string)$val;
    }

    /** Insert or update a value in catalog_meta. */
    private function setMeta(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO catalog_meta (meta_key, meta_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE meta_value = :v2'
        );
        $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
    }

    // Catalog reads
    /** @return array<int,array<string,mixed>> list of {slug,title,tagline}. */
    public function listCategories(): array
    {
        $stmt = $this->pdo->query(
            'SELECT slug, title, tagline FROM categories ORDER BY sort_order'
        );
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null one {slug,title,tagline} or null. */
    public function getCategory(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT slug, title, tagline FROM categories WHERE slug = ?'
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> items for a category. */
    public function getItems(string $slug): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, location, summary, about, tags_json, image
             FROM items WHERE category_slug = ? ORDER BY sort_order'
        );
        $stmt->execute([$slug]);
        return array_map([$this, 'itemRowToArray'], $stmt->fetchAll());
    }

    /** @return array<string,mixed>|null single item or null. */
    public function getItem(string $slug, string $itemId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, location, summary, about, tags_json, image
             FROM items WHERE category_slug = ? AND id = ?'
        );
        $stmt->execute([$slug, $itemId]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->itemRowToArray($row);
    }

    /**
     * Normalise a DB item row into the JSON shape the frontend expects:
     * decode tags_json -> tags[], and omit `image` entirely when empty so the
     * UI falls back to its generated placeholder (matches the old behaviour).
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function itemRowToArray(array $row): array
    {
        $tags = json_decode((string)($row['tags_json'] ?? '[]'), true);
        unset($row['tags_json']);
        $row['tags'] = is_array($tags) ? $tags : [];

        if (empty($row['image'])) {
            unset($row['image']);
        }
        return $row;
    }

    // Registration transaction
    /**
     * Insert a validated registration and return the full record (with its new
     * server id + created_at), exactly what the confirmation page reads back.
     *
     * @param array<string,mixed> $cleaned
     * @return array<string,mixed>
     */
    public function createRegistration(array $cleaned): array
    {
        $rec = [
            'id'         => self::newUuid(),
            'full_name'  => $cleaned['full_name']  ?? '',
            'email'      => $cleaned['email']      ?? '',
            'phone'      => $cleaned['phone']      ?? '',
            'category'   => $cleaned['category']   ?? '',
            'attraction' => $cleaned['attraction'] ?? '',
            'visit_date' => $cleaned['visit_date'] ?? '',
            'travelers'  => $cleaned['travelers']  ?? null,
            'notes'      => $cleaned['notes']      ?? '',
            'created_at' => self::nowIso(),
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO registrations
                (id, full_name, email, phone, category, attraction, visit_date, travelers, notes, created_at)
             VALUES
                (:id, :full_name, :email, :phone, :category, :attraction, :visit_date, :travelers, :notes, :created_at)'
        );
        $stmt->execute($rec);

        // travelers is an int in the API contract (frontend prints it as a number).
        $rec['travelers'] = $rec['travelers'] === null ? null : (int)$rec['travelers'];
        return $rec;
    }

    /** Return the stored registration or null. */
    public function getRegistration(string $regId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, email, phone, category, attraction,
                    visit_date, travelers, notes, created_at
             FROM registrations WHERE id = ?'
        );
        $stmt->execute([$regId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['travelers'] = $row['travelers'] === null ? null : (int)$row['travelers'];
        return $row;
    }

    public function countRegistrations(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
    }

    // Minor utilities
    private static function nowIso(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    private static function newUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant 10xx
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
