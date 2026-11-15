<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/* ======================================================================
 * Data access: users, profiles, revisions, services, requests, settings.
 * Plain functions over PDO. Revision rows come back with decoded
 * 'public' and 'private' arrays.
 * ==================================================================== */

/* ---------------------------------------------------------------- users */

function user_get(int $id): ?array
{
    return db_one('SELECT * FROM users WHERE id = ?', [$id]);
}

function user_get_by_email(string $email): ?array
{
    return db_one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email), 'UTF-8')]);
}

/**
 * @param string $role   'customer' | 'admin' | '' (all)
 * @return array<int,array<string,mixed>>
 */
function users_list(string $role = '', string $search = ''): array
{
    $sql = 'SELECT u.*, (SELECT COUNT(*) FROM profiles p WHERE p.owner_id = u.id) AS profile_count FROM users u WHERE 1=1';
    $params = [];
    if ($role !== '') {
        $sql .= ' AND u.role = ?';
        $params[] = $role;
    }
    if ($search !== '') {
        $sql .= ' AND (u.email LIKE ? ESCAPE \'\\\' OR u.name LIKE ? ESCAPE \'\\\')';
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        $params[] = $like;
        $params[] = $like;
    }
    return db_all($sql . ' ORDER BY u.created_at DESC LIMIT 500', $params);
}

function user_set_status(int $id, string $status): void
{
    db_exec('UPDATE users SET status = ? WHERE id = ?', [$status, $id]);
}

/* ------------------------------------------------------------- settings */

/** @param mixed $default @return mixed */
function setting_get(string $key, $default = null)
{
    $row = db_one('SELECT v FROM settings WHERE k = ?', [$key]);
    return $row === null ? $default : $row['v'];
}

function setting_set(string $key, string $value): void
{
    db_exec('INSERT OR REPLACE INTO settings (k, v) VALUES (?, ?)', [$key, $value]);
}

/** Management block of the imprints: config defaults overridden by DB settings "management.<key>". */
function management_config(): array
{
    $m = (array) config('management', []);
    foreach (array_keys($m) as $k) {
        $v = setting_get('management.' . $k);
        if ($v !== null && $v !== '') {
            $m[$k] = (string) $v;
        }
    }
    return $m;
}

/* ------------------------------------------------------------- profiles */

function profile_get(int $id): ?array
{
    return db_one('SELECT * FROM profiles WHERE id = ?', [$id]);
}

function profile_get_by_slug(string $slug): ?array
{
    return db_one('SELECT * FROM profiles WHERE slug = ?', [$slug]);
}

/** @return array<int,array<string,mixed>> profiles of one owner, newest first */
function profiles_by_owner(int $userId): array
{
    return db_all('SELECT * FROM profiles WHERE owner_id = ? ORDER BY created_at DESC', [$userId]);
}

/**
 * Create a profile with its first draft revision.
 *
 * @param array<string,mixed> $public
 * @param array<string,mixed> $private
 * @return int profile id
 */
function profile_create(?int $ownerId, string $slug, array $public, array $private): int
{
    return (int) db_transaction(static function () use ($ownerId, $slug, $public, $private) {
        $t = now();
        $pid = db_insert(
            'INSERT INTO profiles (owner_id, slug, status, identity_verified, created_at, updated_at) VALUES (?,?,?,?,?,?)',
            [$ownerId, $slug, 'draft', 0, $t, $t]
        );
        db_insert(
            'INSERT INTO revisions (profile_id, number, state, public_json, private_json, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
            [$pid, 1, 'draft', json_str($public), json_str($private), $t, $t]
        );
        return $pid;
    });
}

function profile_set_status(int $id, string $status): void
{
    db_exec('UPDATE profiles SET status = ?, updated_at = ? WHERE id = ?', [$status, now(), $id]);
}

/**
 * Admin list: profiles with owner e-mail and display name of the newest revision.
 *
 * @return array<int,array<string,mixed>>
 */
function profiles_list(string $status = '', string $search = ''): array
{
    $sql = 'SELECT p.*, u.email AS owner_email, u.name AS owner_name,
              (SELECT public_json FROM revisions r WHERE r.profile_id = p.id ORDER BY r.number DESC LIMIT 1) AS latest_public,
              (SELECT COUNT(*) FROM revisions r WHERE r.profile_id = p.id AND r.state = \'submitted\') AS pending_count
            FROM profiles p LEFT JOIN users u ON u.id = p.owner_id WHERE 1=1';
    $params = [];
    if ($status !== '') {
        $sql .= ' AND p.status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        $sql .= ' AND (p.slug LIKE ? ESCAPE \'\\\' OR u.email LIKE ? ESCAPE \'\\\' OR latest_public LIKE ? ESCAPE \'\\\')';
        array_push($params, $like, $like, $like);
    }
    $rows = db_all($sql . ' ORDER BY p.updated_at DESC LIMIT 500', $params);
    foreach ($rows as &$row) {
        $pub = json_arr($row['latest_public'] ?? null);
        $row['display_name'] = (string) ($pub['display_name'] ?? $row['slug']);
    }
    return $rows;
}

/* ------------------------------------------------------------ revisions */

/** Decode public/private JSON of a revision row. */
function revision_decode(?array $row): ?array
{
    if ($row === null) {
        return null;
    }
    $row['public'] = json_arr($row['public_json'] ?? null);
    $row['private'] = json_arr($row['private_json'] ?? null);
    return $row;
}

function revision_get(int $id): ?array
{
    return revision_decode(db_one('SELECT * FROM revisions WHERE id = ?', [$id]));
}

/** The open revision (draft or submitted) of a profile, if any. */
function revision_open(int $profileId): ?array
{
    return revision_decode(db_one(
        'SELECT * FROM revisions WHERE profile_id = ? AND state IN (\'draft\',\'submitted\') ORDER BY number DESC LIMIT 1',
        [$profileId]
    ));
}

/** The live (approved) revision of a profile, if any. */
function revision_live(int $profileId): ?array
{
    return revision_decode(db_one(
        'SELECT r.* FROM revisions r JOIN profiles p ON p.live_revision_id = r.id WHERE p.id = ?',
        [$profileId]
    ));
}

/** Newest revision regardless of state. */
function revision_latest(int $profileId): ?array
{
    return revision_decode(db_one('SELECT * FROM revisions WHERE profile_id = ? ORDER BY number DESC LIMIT 1', [$profileId]));
}

/** @return array<int,array<string,mixed>> all revisions, newest first */
function revisions_for_profile(int $profileId): array
{
    return array_map('revision_decode', db_all('SELECT * FROM revisions WHERE profile_id = ? ORDER BY number DESC', [$profileId]));
}

/**
 * Revisions waiting for review.
 *
 * @return array<int,array<string,mixed>> each with profile_slug, owner_email
 */
function revisions_submitted(): array
{
    $rows = db_all(
        'SELECT r.*, p.slug AS profile_slug, p.status AS profile_status, u.email AS owner_email
           FROM revisions r JOIN profiles p ON p.id = r.profile_id LEFT JOIN users u ON u.id = p.owner_id
          WHERE r.state = \'submitted\' ORDER BY r.submitted_at ASC'
    );
    return array_map('revision_decode', $rows);
}

/** Overwrite the data of a DRAFT revision. */
function revision_save_draft(int $revisionId, array $public, array $private): void
{
    $n = db_exec(
        'UPDATE revisions SET public_json = ?, private_json = ?, updated_at = ? WHERE id = ? AND state = \'draft\'',
        [json_str($public), json_str($private), now(), $revisionId]
    );
    if ($n !== 1) {
        throw new RuntimeException('Revision is not an editable draft.');
    }
}

/**
 * Start a new draft from the newest revision (used after approval/rejection). Fails if one is already open.
 *
 * @return int new revision id
 */
function revision_create_draft(int $profileId): int
{
    return (int) db_transaction(static function () use ($profileId) {
        if (revision_open($profileId) !== null) {
            throw new RuntimeException('There is already an open revision.');
        }
        $latest = revision_latest($profileId);
        $t = now();
        return db_insert(
            'INSERT INTO revisions (profile_id, number, state, public_json, private_json, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
            [$profileId, $latest ? (int) $latest['number'] + 1 : 1, 'draft',
             $latest ? $latest['public_json'] : '{}', $latest ? $latest['private_json'] : '{}', $t, $t]
        );
    });
}

/** draft → submitted (and profile → pending if it has never been live). */
function revision_submit(int $revisionId): void
{
    db_transaction(static function () use ($revisionId) {
        $r = db_one('SELECT * FROM revisions WHERE id = ?', [$revisionId]);
        if (!$r || $r['state'] !== 'draft') {
            throw new RuntimeException('Only a draft can be submitted.');
        }
        $t = now();
        db_exec('UPDATE revisions SET state = \'submitted\', submitted_at = ?, updated_at = ? WHERE id = ?', [$t, $t, $revisionId]);
        $p = profile_get((int) $r['profile_id']);
        if ($p && $p['live_revision_id'] === null) {
            profile_set_status((int) $p['id'], 'pending');
        } else {
            db_exec('UPDATE profiles SET updated_at = ? WHERE id = ?', [$t, (int) $r['profile_id']]);
        }
    });
}

/**
 * Approve a submitted revision: it becomes the live one, the previous live revision is superseded.
 * (Writing the static page is done by the caller via imprint_publish()).
 */
function revision_approve(int $revisionId, int $adminId, string $note = ''): void
{
    db_transaction(static function () use ($revisionId, $adminId, $note) {
        $r = db_one('SELECT * FROM revisions WHERE id = ?', [$revisionId]);
        if (!$r || $r['state'] !== 'submitted') {
            throw new RuntimeException('Only a submitted revision can be approved.');
        }
        $t = now();
        $p = profile_get((int) $r['profile_id']);
        if ($p && $p['live_revision_id'] !== null) {
            db_exec('UPDATE revisions SET state = \'superseded\', updated_at = ? WHERE id = ? AND state = \'approved\'', [$t, (int) $p['live_revision_id']]);
        }
        db_exec(
            'UPDATE revisions SET state = \'approved\', reviewed_at = ?, reviewed_by = ?, review_note = ?, updated_at = ? WHERE id = ?',
            [$t, $adminId, $note, $t, $revisionId]
        );
        db_exec(
            'UPDATE profiles SET live_revision_id = ?, status = \'live\', published_at = ?, updated_at = ? WHERE id = ?',
            [$revisionId, $t, $t, (int) $r['profile_id']]
        );
    });
}

/** Reject a submitted revision (the live version, if any, stays online). */
function revision_reject(int $revisionId, int $adminId, string $note): void
{
    db_transaction(static function () use ($revisionId, $adminId, $note) {
        $r = db_one('SELECT * FROM revisions WHERE id = ?', [$revisionId]);
        if (!$r || $r['state'] !== 'submitted') {
            throw new RuntimeException('Only a submitted revision can be rejected.');
        }
        $t = now();
        db_exec(
            'UPDATE revisions SET state = \'rejected\', reviewed_at = ?, reviewed_by = ?, review_note = ?, updated_at = ? WHERE id = ?',
            [$t, $adminId, $note, $t, $revisionId]
        );
        $p = profile_get((int) $r['profile_id']);
        if ($p && $p['live_revision_id'] === null) {
            profile_set_status((int) $p['id'], 'draft');
        } else {
            db_exec('UPDATE profiles SET updated_at = ? WHERE id = ?', [$t, (int) $r['profile_id']]);
        }
    });
}

/* ------------------------------------------------------------- services */

/** @return array<int,array<string,mixed>> */
function services_all(bool $includeHidden = false): array
{
    return db_all('SELECT * FROM services' . ($includeHidden ? '' : ' WHERE availability != \'hidden\'') . ' ORDER BY sort_order, id');
}

function service_get(string $slug): ?array
{
    return db_one('SELECT * FROM services WHERE slug = ?', [$slug]);
}

/** Insert or update a service by slug. @param array<string,mixed> $s */
function service_save(array $s): void
{
    db_exec(
        'INSERT INTO services (slug,name,summary,details,availability,requestable,sort_order) VALUES (?,?,?,?,?,?,?)
         ON CONFLICT(slug) DO UPDATE SET name=excluded.name, summary=excluded.summary, details=excluded.details,
           availability=excluded.availability, requestable=excluded.requestable, sort_order=excluded.sort_order',
        [$s['slug'], $s['name'], $s['summary'], $s['details'], $s['availability'], !empty($s['requestable']) ? 1 : 0, (int) ($s['sort_order'] ?? 100)]
    );
}

/* ------------------------------------------------------------- requests */

/** @param array<string,mixed> $payload @return int request id */
function request_create(string $serviceSlug, ?int $userId, string $name, string $email, array $payload): int
{
    $t = now();
    return db_insert(
        'INSERT INTO service_requests (service_slug,user_id,name,email,payload_json,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',
        [$serviceSlug, $userId, $name, $email, json_str($payload), 'new', $t, $t]
    );
}

function request_get(int $id): ?array
{
    $r = db_one('SELECT * FROM service_requests WHERE id = ?', [$id]);
    if ($r) {
        $r['payload'] = json_arr($r['payload_json']);
    }
    return $r;
}

/** @return array<int,array<string,mixed>> */
function requests_list(string $status = '', string $serviceSlug = ''): array
{
    $sql = 'SELECT * FROM service_requests WHERE 1=1';
    $params = [];
    if ($status !== '') {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    if ($serviceSlug !== '') {
        $sql .= ' AND service_slug = ?';
        $params[] = $serviceSlug;
    }
    return db_all($sql . ' ORDER BY created_at DESC LIMIT 500', $params);
}

function request_update(int $id, string $status, string $note): void
{
    db_exec('UPDATE service_requests SET status = ?, admin_note = ?, updated_at = ? WHERE id = ?', [$status, $note, now(), $id]);
}

/** @return array<int,array<string,mixed>> requests of one customer */
function requests_by_user(int $userId): array
{
    return db_all('SELECT * FROM service_requests WHERE user_id = ? ORDER BY created_at DESC', [$userId]);
}
