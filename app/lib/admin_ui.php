<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/* ======================================================================
 * Small helpers shared by the admin panel (admin/*.php, app/views/admin/*).
 * ==================================================================== */

/** Exception code used for "someone else already changed this" (double submission, stale page). */
const ADMIN_STATE_CONFLICT = 409;

/** Positive integer id from the query string; answers 404 if missing/invalid. */
function admin_id(string $key = 'id'): int
{
    $id = get_int($key);
    if ($id <= 0) {
        abort(404);
    }
    return $id;
}

/** Human readable age, e.g. "12 min", "5 h", "3 days". */
function admin_age(?int $ts): string
{
    if (!$ts) {
        return '–';
    }
    $s = max(0, now() - $ts);
    if ($s < 90) {
        return __('just now');
    }
    if ($s < 5400) {
        return (int) round($s / 60) . ' min';
    }
    if ($s < 172800) {
        return (int) round($s / 3600) . ' h';
    }
    return (int) floor($s / 86400) . ' ' . __('days');
}

/** CSS modifier for the age of a pending item (stale reviews get highlighted). */
function admin_age_class(?int $ts): string
{
    $s = $ts ? now() - $ts : 0;
    return $s > 3 * 86400 ? 'pf-age--old' : ($s > 86400 ? 'pf-age--warn' : '');
}

/**
 * Build a query string from non-empty parameters ("?a=1&b=2" or "").
 *
 * @param array<string,mixed> $params
 */
function admin_query(array $params): string
{
    $params = array_filter($params, static function ($v) {
        return $v !== '' && $v !== null && $v !== 0;
    });
    return $params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
}

/**
 * Pagination math.
 *
 * @return array{page:int,pages:int,offset:int,per:int,total:int}
 */
function admin_pager(int $total, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, get_int('page')), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage, 'total' => $total];
}

/** Escape a user string for use inside a LIKE ... ESCAPE '\' pattern (contains match). */
function admin_like(string $text): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text) . '%';
}

/** Number of active admin accounts. */
function admin_active_admin_count(): int
{
    return (int) (db_one('SELECT COUNT(*) AS n FROM users WHERE role = \'admin\' AND status = \'active\'')['n'] ?? 0);
}

/** Is there at least one admin account (any status)? */
function admin_exists(): bool
{
    return db_one('SELECT id FROM users WHERE role = \'admin\' LIMIT 1') !== null;
}

/** Mail a user by row (no-op for null). Failures are logged by send_mail(); returns false then. */
function admin_mail_user(?array $user, string $subject, string $text): bool
{
    if ($user === null || empty($user['email'])) {
        return false;
    }
    $name = trim((string) ($user['name'] ?? ''));
    return send_mail((string) $user['email'], $subject, 'Hello' . ($name !== '' ? ' ' . $name : '') . ",\n\n" . $text);
}

/** Display name of the newest revision of a profile (falls back to the slug). */
function admin_profile_title(array $profile): string
{
    $rev = revision_latest((int) $profile['id']);
    $name = $rev ? (string) ($rev['public']['display_name'] ?? '') : '';
    return $name !== '' ? $name : (string) $profile['slug'];
}

/**
 * Flatten a request payload for display as key/value rows (values are plain strings, escaped by the view).
 *
 * @param array<string,mixed> $payload
 * @return array<int,array{0:string,1:string}>
 */
function admin_payload_rows(array $payload): array
{
    $rows = [];
    foreach ($payload as $key => $value) {
        if (is_bool($value)) {
            $text = $value ? 'yes' : 'no';
        } elseif (is_scalar($value) || $value === null) {
            $text = (string) $value;
        } else {
            $text = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        $rows[] = [mb_substr((string) $key, 0, 80, 'UTF-8'), mb_substr($text, 0, 5000, 'UTF-8')];
    }
    return $rows;
}

/** Counters for the dashboard. @return array<string,int> */
function admin_dashboard_stats(): array
{
    $count = static function (string $sql): int {
        return (int) (db_one($sql)['n'] ?? 0);
    };
    return [
        'pending_reviews' => $count('SELECT COUNT(*) AS n FROM revisions WHERE state = \'submitted\''),
        'live_imprints'   => $count('SELECT COUNT(*) AS n FROM profiles WHERE status = \'live\''),
        'customers'       => $count('SELECT COUNT(*) AS n FROM users WHERE role = \'customer\''),
        'new_requests'    => $count('SELECT COUNT(*) AS n FROM service_requests WHERE status = \'new\''),
    ];
}

/** Valid values for request status filters etc. @return string[] */
function admin_request_statuses(): array
{
    return ['new', 'in_progress', 'accepted', 'declined', 'done'];
}

/** @return string[] */
function admin_profile_statuses(): array
{
    return ['draft', 'pending', 'live', 'suspended', 'withdrawn'];
}

/** @return string[] */
function admin_service_availabilities(): array
{
    return ['active', 'on_request', 'coming_soon', 'hidden'];
}

/** Raw POST string (no trimming: passwords may contain spaces). Max 200 bytes. */
function admin_post_raw(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? substr($_POST[$key], 0, 200) : '';
}

/** Path of the one-time setup token file. */
function admin_setup_token_file(): string
{
    // Not a fixed, guessable name: derived from the (random) database file name.
    return data_dir() . '/setup-token-' . substr(hash('sha256', basename(db_file())), 0, 12) . '.txt';
}

/**
 * Return the setup token, creating the file (mode 0600, random hex) if it is missing.
 *
 * @return string '' if the file cannot be created or read
 */
function admin_setup_token(): string
{
    $file = admin_setup_token_file();
    if (!is_file($file)) {
        $old = umask(0077);
        $fh = @fopen($file, 'x');              // exclusive create: no race between two requests
        umask($old);
        if ($fh !== false) {
            fwrite($fh, random_token(16) . "\n");
            fclose($fh);
            @chmod($file, 0600);
        }
    }
    $token = is_file($file) ? trim((string) @file_get_contents($file)) : '';
    return strlen($token) >= 24 ? $token : '';
}

/** Name to show for a user (falls back to the e-mail address). */
function admin_display_name(array $user): string
{
    $name = trim((string) ($user['name'] ?? ''));
    return $name !== '' ? $name : (string) $user['email'];
}

/**
 * E-mail of the reviewer of each revision, for the history tables.
 *
 * @param array<int,array<string,mixed>> $revisions
 * @return array<int,string> user id => e-mail
 */
function admin_reviewer_names(array $revisions): array
{
    $ids = [];
    foreach ($revisions as $r) {
        if (!empty($r['reviewed_by'])) {
            $ids[(int) $r['reviewed_by']] = true;
        }
    }
    $names = [];
    foreach (array_keys($ids) as $uid) {
        $u = user_get($uid);
        $names[$uid] = $u ? (string) $u['email'] : 'user#' . $uid;
    }
    return $names;
}
