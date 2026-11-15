<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Account and service-request helpers of the customer portal.
 */

/** @return array<string,string> value => label (expected monthly shop revenue) */
function portal_revenue_ranges(): array
{
    return [
        'lt-1k'    => __('Less than EUR 1,000'),
        '1k-5k'    => __('EUR 1,000 – 5,000'),
        '5k-20k'   => __('EUR 5,000 – 20,000'),
        '20k-100k' => __('EUR 20,000 – 100,000'),
        'gt-100k'  => __('More than EUR 100,000'),
        'unknown'  => __('I do not know yet'),
    ];
}

/** Display name of a service slug (also for internal request types such as the account deletion). */
function portal_service_label(string $slug): string
{
    if ($slug === 'account-deletion') {
        return __('Account deletion');
    }
    $service = service_get($slug);
    return $service !== null ? (string) $service['name'] : $slug;
}

/** Change the display name of a user. */
function portal_user_set_name(int $userId, string $name): void
{
    db_exec('UPDATE users SET name = ? WHERE id = ?', [$name, $userId]);
    audit('user.name_changed', 'user', $userId, [], $userId);
}

/** Unix time → ISO 8601 (UTC) for the data export. */
function portal_iso(?int $ts): ?string
{
    return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : null;
}

/**
 * Everything we store about a customer (GDPR data copy): account without secrets, profiles with ALL
 * revisions including the private data, and service requests.
 *
 * @param array<string,mixed> $user
 * @return array<string,mixed>
 */
function portal_export_data(array $user): array
{
    $uid = (int) $user['id'];
    $profiles = [];
    foreach (profiles_by_owner($uid) as $p) {
        $revisions = [];
        foreach (revisions_for_profile((int) $p['id']) as $r) {
            $revisions[] = [
                'number'       => (int) $r['number'],
                'state'        => (string) $r['state'],
                'public_data'  => $r['public'],
                'private_data' => $r['private'],
                'submitted_at' => portal_iso($r['submitted_at'] !== null ? (int) $r['submitted_at'] : null),
                'reviewed_at'  => portal_iso($r['reviewed_at'] !== null ? (int) $r['reviewed_at'] : null),
                'review_note'  => (string) $r['review_note'],
                'created_at'   => portal_iso((int) $r['created_at']),
                'updated_at'   => portal_iso((int) $r['updated_at']),
            ];
        }
        $profiles[] = [
            'page_name'         => (string) $p['slug'],
            'status'            => (string) $p['status'],
            'identity_verified' => (bool) $p['identity_verified'],
            'identity_checks'   => portal_export_identity((int) $p['id']),
            'created_at'        => portal_iso((int) $p['created_at']),
            'published_at'      => portal_iso($p['published_at'] !== null ? (int) $p['published_at'] : null),
            'revisions'         => $revisions,
        ];
    }
    $requests = [];
    foreach (requests_by_user($uid) as $r) {
        $requests[] = [
            'service'    => (string) $r['service_slug'],
            'name'       => (string) $r['name'],
            'email'      => (string) $r['email'],
            'details'    => json_arr((string) $r['payload_json']),
            'status'     => (string) $r['status'],
            'created_at' => portal_iso((int) $r['created_at']),
        ];
    }
    return [
        'exported_at' => portal_iso(now()),
        'source'      => (string) config('site_url'),
        'account'     => [
            'email'             => (string) $user['email'],
            'name'              => (string) $user['name'],
            'status'            => (string) $user['status'],
            'email_verified_at' => portal_iso($user['email_verified_at'] !== null ? (int) $user['email_verified_at'] : null),
            'created_at'        => portal_iso((int) $user['created_at']),
            'last_login_at'     => portal_iso($user['last_login_at'] !== null ? (int) $user['last_login_at'] : null),
            'terms_accepted_at' => portal_iso(isset($user['terms_accepted_at']) && $user['terms_accepted_at'] !== null ? (int) $user['terms_accepted_at'] : null),
            'terms_version'     => isset($user['terms_version']) && $user['terms_version'] !== null ? (string) $user['terms_version'] : null,
        ],
        'imprint_profiles' => $profiles,
        'service_requests' => $requests,
        'deletion_request' => portal_export_deletion($uid),
    ];
}

/**
 * Identity checks of a profile for the data export: status, timestamps and number of files per check. The images
 * themselves (and every storage name/reference) are never part of it.
 *
 * @return array<int,array<string,mixed>> newest first
 */
function portal_export_identity(int $profileId): array
{
    $out = [];
    foreach (db_all('SELECT * FROM identity_requests WHERE profile_id = ? AND status != \'erased\' ORDER BY id DESC', [$profileId]) as $req) {
        $files = [];
        $archivedAt = null;
        foreach (identity_files((int) $req['id']) as $f) {
            $files[] = [
                'side'        => (string) $f['side'],
                'uploaded_at' => portal_iso((int) $f['created_at']),
                'archived_at' => portal_iso($f['archived_at'] !== null ? (int) $f['archived_at'] : null),
                'stored'      => ($f['archive_ref'] !== null && $f['archive_ref'] !== '') ? 'encrypted, secure archive' : 'encrypted, web server (temporary)',
            ];
            if ($f['archived_at'] !== null) {
                $archivedAt = max((int) $archivedAt, (int) $f['archived_at']);
            }
        }
        $out[] = [
            'status'      => (string) $req['status'],
            'uploaded_at' => portal_iso((int) $req['created_at']),
            'verified_at' => portal_iso($req['verified_at'] !== null ? (int) $req['verified_at'] : null),
            'archived_at' => portal_iso($archivedAt),
            'file_count'  => count($files),
            'files'       => $files,
            'note'        => (string) $req['note'],
        ];
    }
    return $out;
}

/** The open deletion request of a customer for the data export (status and times only), or null. @return array<string,mixed>|null */
function portal_export_deletion(int $userId): ?array
{
    $r = termination_pending($userId);
    if ($r === null) {
        return null;
    }
    return [
        'status'       => (string) $r['status'],
        'requested_at' => portal_iso((int) $r['created_at']),
        'confirmed_at' => portal_iso($r['confirmed_at'] !== null ? (int) $r['confirmed_at'] : null),
    ];
}

/** Is a reset token currently usable? (Read-only check; the token is only consumed by auth_reset_password.) */
function portal_reset_token_valid(string $token): bool
{
    if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
        return false;
    }
    $row = db_one('SELECT expires_at, used_at FROM tokens WHERE token_hash = ? AND type = \'reset\'', [hash('sha256', $token)]);
    return $row !== null && $row['used_at'] === null && (int) $row['expires_at'] >= now();
}
