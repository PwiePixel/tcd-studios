<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * SQLite database access (PDO). The file name is random and stored in data/config.local.php
 * on first run, so a blind download attempt cannot guess it. Keep data/ out of the web root
 * where possible (config 'data_dir' via TCD_DATA_DIR) and never serve it (data/.htaccess).
 */

/** Path of the SQLite file (created with a random name on first use). */
function db_file(): string
{
    static $file = null;
    if ($file !== null) {
        return $file;
    }
    $configured = (string) config('db_file', '');
    if ($configured === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $configured)) {
        $configured = (string) config_local_get_or_create('db_file', static function () {
            return 'db-' . random_token(8) . '.sqlite';
        });
    }
    $file = data_dir() . '/' . $configured;
    return $file;
}

/** Shared PDO connection; migrates the schema on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $file = db_file();
    $isNew = !is_file($file);
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($isNew) {
        @chmod($file, 0640);          // only when created: an administrator may have granted a web server group write access
    }
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA secure_delete = ON');      // deleted rows are overwritten with zeros (irrevocable erasure)
    db_migrate($pdo);
    return $pdo;
}

/** Run a query and return the first row or null. @param array<int|string,mixed> $params */
function db_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Run a query and return all rows. @param array<int|string,mixed> $params */
function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Run a statement; returns the number of affected rows. @param array<int|string,mixed> $params */
function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** Insert and return the new row id. @param array<int|string,mixed> $params */
function db_insert(string $sql, array $params = []): int
{
    db_exec($sql, $params);
    return (int) db()->lastInsertId();
}

/** Run $fn inside a transaction (rolls back on exception; joins an already open transaction). @return mixed */
function db_transaction(callable $fn)
{
    static $depth = 0;
    $pdo = db();
    if ($depth > 0) {
        return $fn();          // join the surrounding transaction (the outer caller commits/rolls back)
    }
    // IMMEDIATE takes the write lock up front: concurrent writers wait (busy_timeout) instead of failing with "database is locked".
    $pdo->exec('BEGIN IMMEDIATE');
    $depth++;
    try {
        $result = $fn();
        $pdo->exec('COMMIT');
        $depth--;
        return $result;
    } catch (Throwable $e) {
        $depth--;
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $ignored) {
            // no transaction active any more
        }
        throw $e;
    }
}

/**
 * Ordered schema migrations. Each step is SQL (string) or a callable receiving the PDO.
 * Never edit an existing step; append a new one.
 *
 * @return array<int,string|callable>
 */
function db_schema_steps(): array
{
    return [
        1 => "
CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL DEFAULT '',
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('customer','admin')),
  status TEXT NOT NULL CHECK (status IN ('pending','active','disabled')),
  email_verified_at INTEGER,
  totp_secret TEXT,
  totp_enabled INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL,
  last_login_at INTEGER
);
CREATE TABLE tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type TEXT NOT NULL CHECK (type IN ('verify','reset')),
  token_hash TEXT NOT NULL UNIQUE,
  expires_at INTEGER NOT NULL,
  used_at INTEGER,
  created_at INTEGER NOT NULL
);
CREATE TABLE profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  owner_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  slug TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL CHECK (status IN ('draft','pending','live','suspended','withdrawn')),
  live_revision_id INTEGER,
  identity_verified INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL,
  published_at INTEGER
);
CREATE TABLE revisions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  profile_id INTEGER NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
  number INTEGER NOT NULL,
  state TEXT NOT NULL CHECK (state IN ('draft','submitted','approved','rejected','superseded')),
  public_json TEXT NOT NULL DEFAULT '{}',
  private_json TEXT NOT NULL DEFAULT '{}',
  submitted_at INTEGER,
  reviewed_at INTEGER,
  reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  review_note TEXT NOT NULL DEFAULT '',
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL,
  UNIQUE (profile_id, number)
);
CREATE TABLE services (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  summary TEXT NOT NULL DEFAULT '',
  details TEXT NOT NULL DEFAULT '',
  availability TEXT NOT NULL CHECK (availability IN ('active','on_request','coming_soon','hidden')),
  requestable INTEGER NOT NULL DEFAULT 0,
  sort_order INTEGER NOT NULL DEFAULT 100
);
CREATE TABLE service_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  service_slug TEXT NOT NULL,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  name TEXT NOT NULL DEFAULT '',
  email TEXT NOT NULL DEFAULT '',
  payload_json TEXT NOT NULL DEFAULT '{}',
  status TEXT NOT NULL DEFAULT 'new' CHECK (status IN ('new','in_progress','accepted','declined','done')),
  admin_note TEXT NOT NULL DEFAULT '',
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);
CREATE TABLE audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  at INTEGER NOT NULL,
  actor_id INTEGER,
  actor_label TEXT NOT NULL DEFAULT '',
  action TEXT NOT NULL,
  target_type TEXT,
  target_id TEXT,
  meta_json TEXT NOT NULL DEFAULT '{}',
  ip TEXT NOT NULL DEFAULT ''
);
CREATE TABLE rate_limits (k TEXT PRIMARY KEY, hits INTEGER NOT NULL, window_start INTEGER NOT NULL);
CREATE TABLE settings (k TEXT PRIMARY KEY, v TEXT NOT NULL);
CREATE INDEX idx_profiles_owner ON profiles(owner_id);
CREATE INDEX idx_revisions_profile ON revisions(profile_id, state);
CREATE INDEX idx_requests_status ON service_requests(status, created_at);
CREATE INDEX idx_audit_at ON audit_log(at);
CREATE INDEX idx_tokens_user ON tokens(user_id, type);
",
        2 => function (PDO $pdo): void {
            // Service catalogue (editable in the admin panel).
            $ins = $pdo->prepare('INSERT INTO services (slug,name,summary,details,availability,requestable,sort_order) VALUES (?,?,?,?,?,?,?)');
            $ins->execute(['imprint-protection', 'Imprint Protection',
                'A legally sound imprint for creators under tcd-studio.de/legal/<name>/ with the management address of TCD-Studio/Agency instead of your private address.',
                'You decide which links and contact details appear in your imprint. Every change is reviewed and approved by our team before it goes live.',
                'active', 1, 10]);
            $ins->execute(['creator-protection', 'Creator Protection',
                'More protection services for creators and streamers.',
                'We are building further services around the protection of creators. Tell us what you need.',
                'coming_soon', 1, 20]);
            $ins->execute(['shops', 'Shop Partnership',
                'Payments, tax settlement and payouts for online shops.',
                'Connect your shop to our payment system. We retain an agreed share of the revenue, take care of the tax settlement and pay out according to tax law. In the launch phase this is available on request and in limited numbers only.',
                'on_request', 1, 30]);
        },
        3 => function (PDO $pdo): void {
            // Legacy imprints that existed as static pages (app/data/legacy-profiles.php).
            $file = TCD_ROOT . '/app/data/legacy-profiles.php';
            if (!is_file($file)) {
                return;
            }
            $profiles = (static function () use ($file) {
                return require $file;
            })();
            if (!is_array($profiles)) {
                return;
            }
            $t = time();
            foreach ($profiles as $p) {
                $pdo->prepare('INSERT INTO profiles (owner_id,slug,status,live_revision_id,identity_verified,created_at,updated_at,published_at) VALUES (NULL,?,?,NULL,0,?,?,?)')
                    ->execute([$p['slug'], 'live', $t, $t, $t]);
                $pid = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO revisions (profile_id,number,state,public_json,private_json,submitted_at,reviewed_at,review_note,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$pid, 1, 'approved', json_str($p['public']), json_str($p['private'] ?? []), $t, $t, 'Imported from the previous static imprint page.', $t, $t]);
                $rid = (int) $pdo->lastInsertId();
                $pdo->prepare('UPDATE profiles SET live_revision_id = ? WHERE id = ?')->execute([$rid, $pid]);
            }
        },
        4 => 'ALTER TABLE users ADD COLUMN session_epoch INTEGER NOT NULL DEFAULT 0',
        5 => function (PDO $pdo): void {
            // Terms and Conditions (AGB) acceptance is recorded per customer (time + version).
            $pdo->exec('ALTER TABLE users ADD COLUMN terms_accepted_at INTEGER');
            $pdo->exec('ALTER TABLE users ADD COLUMN terms_version TEXT');
            // Product rules: Imprint Protection is for creators (everyone); shops only on request; shop payments (future) via Stripe.
            $upd = $pdo->prepare('UPDATE services SET name = ?, summary = ?, details = ?, availability = ?, requestable = ? WHERE slug = ?');
            $upd->execute(['Imprint Protection for Creators',
                'A legally sound imprint for creators and streamers on tcd-studio.de/legal/<name>/ with the management address of TCD-Studio/Agency instead of your private address. Available to all creators.',
                'You decide which links and contact details appear in your imprint. Every change is reviewed and approved by our team before it goes live. Our Terms and Conditions (AGB) apply.',
                'active', 1, 'imprint-protection']);
            $upd->execute(['Shops (on request)',
                'Imprint protection for online shops and, in future, payment processing via Stripe. Available on request and in limited numbers.',
                'Imprint protection for shops is not part of the standard offer for creators; it is agreed individually. Planned: your shop payments are processed through the Stripe API; we retain an agreed share of the revenue, take care of the tax settlement and pay out according to tax law. In the launch phase this is available on request and in limited numbers only.',
                'on_request', 1, 'shops']);
        },
        6 => function (PDO $pdo): void {
            // Identity verification (ID photos live in the vault, see vault.php), legal hold and deletion requests.
            $pdo->exec("
CREATE TABLE identity_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
  profile_id INTEGER REFERENCES profiles(id) ON DELETE CASCADE,
  status TEXT NOT NULL CHECK (status IN ('uploaded','verified','rejected','archived','erased')),
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL,
  verified_at INTEGER,
  verified_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  note TEXT NOT NULL DEFAULT ''
);
CREATE TABLE identity_files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  request_id INTEGER NOT NULL REFERENCES identity_requests(id) ON DELETE CASCADE,
  side TEXT NOT NULL CHECK (side IN ('front','back')),
  mime TEXT NOT NULL,
  plain_size INTEGER NOT NULL,
  enc_size INTEGER NOT NULL,
  enc_sha256 TEXT NOT NULL,
  inbox_name TEXT,
  archive_ref TEXT,
  created_at INTEGER NOT NULL,
  archived_at INTEGER,
  deleted_at INTEGER
);
CREATE TABLE deletion_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  user_ref INTEGER NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('requested','confirmed','on_hold','completed','cancelled')),
  token_hash TEXT,
  token_expires INTEGER,
  hold_note TEXT NOT NULL DEFAULT '',
  created_at INTEGER NOT NULL,
  confirmed_at INTEGER,
  completed_at INTEGER,
  counts_json TEXT NOT NULL DEFAULT '{}'
);
CREATE INDEX idx_identity_profile ON identity_requests(profile_id, status);
CREATE INDEX idx_identity_files_request ON identity_files(request_id);
CREATE INDEX idx_deletion_status ON deletion_requests(status, created_at);
");
            $pdo->exec('ALTER TABLE users ADD COLUMN legal_hold INTEGER NOT NULL DEFAULT 0');
            $pdo->exec("ALTER TABLE users ADD COLUMN legal_hold_note TEXT NOT NULL DEFAULT ''");
            // Encrypt existing private (identity/address) data at rest.
            $sel = $pdo->query('SELECT id, private_json FROM revisions');
            $upd = $pdo->prepare('UPDATE revisions SET private_json = ? WHERE id = ?');
            foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!crypt_is_encrypted((string) $row['private_json'])) {
                    $upd->execute([crypt_encrypt((string) $row['private_json'] === '' ? '{}' : (string) $row['private_json']), (int) $row['id']]);
                }
            }
        },
    ];
}

/** Apply pending migrations (tracked via PRAGMA user_version). */
function db_migrate(PDO $pdo): void
{
    $current = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    $steps = db_schema_steps();
    ksort($steps);
    foreach ($steps as $version => $step) {
        if ($version <= $current) {
            continue;
        }
        $pdo->beginTransaction();
        try {
            if (is_string($step)) {
                $pdo->exec($step);
            } else {
                $step($pdo);
            }
            $pdo->exec('PRAGMA user_version = ' . (int) $version);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
