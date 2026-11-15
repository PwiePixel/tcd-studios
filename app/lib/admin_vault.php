<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/* ======================================================================
 * Admin side of the identity vault: status page data, archive probe, identity request helpers,
 * deletion request lists, legal hold and the dashboard/system-check hooks.
 * The cryptographic core lives in vault.php / erase.php (never re-implemented here).
 * ==================================================================== */

/** After this many days an ID file that is still in the web server inbox (not archived) is reported. */
const ADMIN_VAULT_STALE_DAYS = 7;

/* ------------------------------------------------------------ vault status */

/** Is $path (existing or not) inside the web root? Resolves the nearest existing parent, so it also works for directories that do not exist yet. */
function admin_path_inside_web_root(string $path): bool
{
    $root = realpath(TCD_ROOT);
    if ($root === false || $path === '') {
        return false;
    }
    $rest = '';
    $probe = rtrim($path, '/');
    while ($probe !== '' && $probe !== '/' && realpath($probe) === false) {
        $rest = '/' . basename($probe) . $rest;
        $probe = dirname($probe);
    }
    $real = realpath($probe === '' ? '/' : $probe);
    if ($real === false) {
        return false;
    }
    $full = rtrim($real, '/') . $rest;
    return $full === $root || strpos($full . '/', $root . '/') === 0;
}

/** Directory of the 'directory' archive driver, computed like vault_archive_dir() but WITHOUT creating it (a status page must not create directories on a missing mount). */
function admin_vault_archive_path(): string
{
    $env = getenv('TCD_VAULT_DIR');
    $configured = ($env !== false && $env !== '') ? $env : trim((string) config('vault.dir', ''));
    return $configured !== '' ? rtrim($configured, '/') : data_dir() . '/vault-archive';
}

/** Missing S3 settings (names only, never values). @return string[] */
function admin_vault_s3_missing(): array
{
    $c = (array) config('vault.s3', []);
    $missing = [];
    foreach (['endpoint', 'bucket', 'access_key', 'secret_key', 'region'] as $k) {
        if (empty($c[$k])) {
            $missing[] = $k;
        }
    }
    return $missing;
}

/** Does the data key exist (data/config.local.php or the TCD_DATA_KEY environment variable)? */
function admin_data_key_present(): bool
{
    $env = getenv('TCD_DATA_KEY');
    if ($env !== false && $env !== '') {
        return true;
    }
    $local = data_dir() . '/config.local.php';
    if (!is_file($local)) {
        return false;
    }
    $cfg = (static function () use ($local) {
        return include $local;
    })();
    return is_array($cfg) && !empty($cfg['data_key']);
}

/** Last stored archive test result, or null. @return array{at:int,ok:bool,detail:string}|null */
function admin_vault_last_probe(): ?array
{
    $raw = setting_get('vault.probe');
    if ($raw === null || $raw === '') {
        return null;
    }
    $d = json_arr((string) $raw);
    return isset($d['at']) ? ['at' => (int) $d['at'], 'ok' => !empty($d['ok']), 'detail' => (string) ($d['detail'] ?? '')] : null;
}

/**
 * Everything the vault page and the system checks need to know.
 *
 * @return array<string,mixed>
 */
function admin_vault_status(): array
{
    $pem = vault_public_key_pem();
    $s = ['key_set' => $pem !== '', 'key_error' => '', 'key_bits' => 0, 'fingerprint' => '', 'pem' => $pem];
    if ($pem !== '') {
        $err = vault_validate_public_key($pem);
        $s['key_error'] = $err ?? '';
        $res = @openssl_pkey_get_public($pem);
        $details = $res ? openssl_pkey_get_details($res) : false;
        $s['key_bits'] = is_array($details) ? (int) ($details['bits'] ?? 0) : 0;
        $s['fingerprint'] = vault_fingerprint($pem);
    }
    $s['key_ok'] = $s['key_set'] && $s['key_error'] === '';

    $driver = (string) config('vault.driver', 'directory') === 's3' ? 's3' : 'directory';
    $s['driver'] = $driver;
    if ($driver === 's3') {
        $c = (array) config('vault.s3', []);
        $s['location'] = rtrim((string) ($c['endpoint'] ?? ''), '/') . ' / ' . (string) ($c['bucket'] ?? '') . ' / ' . (string) ($c['prefix'] ?? '');
        $s['s3_missing'] = admin_vault_s3_missing();
        $s['dir_exists'] = null;
        $s['dir_writable'] = null;
        $s['in_web_root'] = null;
    } else {
        $dir = admin_vault_archive_path();
        $s['location'] = $dir;
        $s['s3_missing'] = [];
        $s['dir_exists'] = is_dir($dir);
        $s['dir_writable'] = admin_dir_writable($dir);
        $s['in_web_root'] = admin_path_inside_web_root($dir);
    }

    $now = now();
    $inbox = db_one('SELECT COUNT(*) AS n, MIN(created_at) AS oldest FROM identity_files WHERE deleted_at IS NULL AND inbox_name IS NOT NULL');
    $s['inbox_files'] = (int) ($inbox['n'] ?? 0);
    $s['inbox_oldest'] = $inbox !== null && $inbox['oldest'] !== null ? (int) $inbox['oldest'] : null;
    $s['inbox_stale'] = (int) (db_one(
        'SELECT COUNT(*) AS n FROM identity_files WHERE deleted_at IS NULL AND inbox_name IS NOT NULL AND created_at < ?',
        [$now - ADMIN_VAULT_STALE_DAYS * 86400]
    )['n'] ?? 0);
    $s['archive_files'] = (int) (db_one('SELECT COUNT(*) AS n FROM identity_files WHERE deleted_at IS NULL AND archive_ref IS NOT NULL AND archive_ref != \'\'')['n'] ?? 0);

    $s['data_key'] = admin_data_key_present();
    $s['probe'] = admin_vault_last_probe();
    return $s;
}

/**
 * Write, read back and delete a small probe object through the configured archive driver.
 * The result is remembered (setting vault.probe) so the system checks can show it without touching the archive again.
 *
 * @return array{ok:bool,detail:string,ms:int}
 */
function admin_vault_probe(): array
{
    $name = 'idf-' . random_token(16) . '.tcdv';
    $payload = 'TCDV1-PROBE-' . random_token(24);
    $t0 = microtime(true);
    $step = 'write';
    $ref = '';
    $ok = false;
    $detail = '';
    try {
        $ref = vault_archive_put($name, $payload);
        $step = 'read back';
        if (!hash_equals($payload, vault_archive_get($ref))) {
            throw new RuntimeException('the data read back differs from what was written');
        }
        $step = 'delete';
        vault_archive_delete($ref);
        $step = 'check deletion';
        $stillThere = false;
        try {
            vault_archive_get($ref);
            $stillThere = true;
        } catch (Throwable $gone) {
            $stillThere = false;                 // expected: the object is gone
        }
        if ($stillThere) {
            throw new RuntimeException('the object can still be read after it was deleted');
        }
        $ref = '';
        $ok = true;
    } catch (Throwable $e) {
        $detail = sprintf(__('Failed at step "%s": %s'), $step, $e->getMessage());
        error_log('vault probe failed at ' . $step . ': ' . $e->getMessage());
        if ($ref !== '') {
            try {
                vault_archive_delete($ref);      // do not leave the probe object behind
            } catch (Throwable $ignored) {
                $detail .= ' ' . __('(the probe object could not be removed either)');
            }
        }
    }
    $ms = (int) round((microtime(true) - $t0) * 1000);
    if ($ok) {
        $detail = sprintf(__('Write, read-back and delete of a probe object succeeded (%d ms).'), $ms);
    }
    setting_set('vault.probe', json_str(['at' => now(), 'ok' => $ok, 'detail' => $detail]));
    return ['ok' => $ok, 'detail' => $detail, 'ms' => $ms];
}

/** Do two public keys (PEM) describe the same key? */
function admin_vault_same_key(string $a, string $b): bool
{
    $fa = vault_fingerprint($a);
    return $fa !== '' && $fa === vault_fingerprint($b);
}

/* ------------------------------------------------- system checks and dashboard */

/** @return array<int,array{label:string,status:string,detail:string}> */
function admin_vault_checks(): array
{
    $s = admin_vault_status();
    $out = [];
    $add = static function (string $label, string $status, string $detail) use (&$out): void {
        $out[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
    };

    if ($s['key_ok']) {
        $add(__('Vault public key'), 'ok', sprintf(__('set (RSA %d bit), fingerprint %s'), (int) $s['key_bits'], substr((string) $s['fingerprint'], 0, 19) . '…'));
    } elseif ($s['key_set']) {
        $add(__('Vault public key'), 'fail', (string) $s['key_error']);
    } else {
        $add(__('Vault public key'), 'warn', __('not set – customers cannot upload ID photos. Generate a key pair on the Vault page.'));
    }

    if ($s['driver'] === 's3') {
        $miss = $s['s3_missing'];
        $add(__('Vault archive (S3)'), $miss ? 'fail' : 'ok', $miss ? sprintf(__('incomplete S3 configuration (missing: %s)'), implode(', ', $miss)) : __('S3 storage configured'));
    } elseif ($s['dir_writable'] !== true) {
        $add(__('Vault archive directory'), 'fail', $s['dir_exists'] ? __('not writable') : __('does not exist and cannot be created'));
    } elseif ($s['in_web_root']) {
        $add(__('Vault archive directory'), 'warn', __('The archive directory is inside the web root. Set config vault.dir (or TCD_VAULT_DIR) to a directory OUTSIDE the web root, ideally on a separate storage system.'));
    } else {
        $add(__('Vault archive directory'), 'ok', __('writable and outside the web root'));
    }

    if ($s['key_ok']) {
        $p = $s['probe'];
        if ($p === null) {
            $add(__('Vault archive test'), 'warn', __('never tested – run "Test archive" on the Vault page'));
        } else {
            $add(__('Vault archive test'), $p['ok'] ? 'ok' : 'fail', ($p['ok'] ? __('last test succeeded ') : __('last test FAILED ')) . admin_age($p['at']) . ' ' . __('ago') . ($p['ok'] ? '' : ': ' . $p['detail']));
        }
    }

    $add(
        __('Data encryption key (data_key)'),
        $s['data_key'] ? 'ok' : 'warn',
        $s['data_key'] ? __('present – back up data/config.local.php together with the database') : __('not created yet (it is generated on first use) – back up data/config.local.php together with the database')
    );

    if ($s['inbox_stale'] > 0) {
        $add(__('ID files in the web server inbox'), 'warn', sprintf(__('%d file(s) have been waiting for more than %d days without being archived. Approve the request or use "Archive now" on the identity page.'), $s['inbox_stale'], ADMIN_VAULT_STALE_DAYS));
    } else {
        $add(__('ID files in the web server inbox'), 'ok', sprintf(__('%d file(s) waiting, none older than %d days'), $s['inbox_files'], ADMIN_VAULT_STALE_DAYS));
    }

    $sd = (int) db()->query('PRAGMA secure_delete')->fetchColumn();
    $add(__('SQLite secure_delete'), $sd >= 1 ? 'ok' : 'warn', $sd >= 1 ? __('on – deleted rows are overwritten') : __('off – deleted data may remain in the database file'));
    return $out;
}

/**
 * Notices for the dashboard.
 *
 * @return array<int,array{text:string,href:string,link:string}>
 */
function admin_vault_notices(): array
{
    $s = admin_vault_status();
    $n = [];
    if (!$s['key_ok']) {
        $n[] = ['text' => __('The identity vault has no (valid) public key: customers cannot upload ID photos.'), 'href' => '/admin/vault.php', 'link' => __('Open the vault page')];
    }
    if ($s['inbox_stale'] > 0) {
        $n[] = ['text' => sprintf(__('%d ID file(s) have been in the web server inbox for more than %d days (not archived).'), $s['inbox_stale'], ADMIN_VAULT_STALE_DAYS), 'href' => '/admin/vault.php', 'link' => __('Open the vault page')];
    }
    return $n;
}

/* ------------------------------------------------------ identity requests */

/** @return array<string,string> status => label */
function admin_identity_status_labels(): array
{
    return ['uploaded' => __('Uploaded – not checked yet'), 'verified' => __('Verified'), 'archived' => __('Verified and archived'), 'rejected' => __('Rejected'), 'erased' => __('Erased')];
}

/** <span class="pf-badge …"> for an identity request status. */
function admin_identity_badge(string $status): string
{
    $class = ['uploaded' => 'pending', 'verified' => 'info', 'archived' => 'ok', 'rejected' => 'rejected', 'erased' => 'draft'];
    $labels = admin_identity_status_labels();
    return '<span class="pf-badge pf-badge--' . h($class[$status] ?? 'draft') . '">' . h($labels[$status] ?? $status) . '</span>';
}

/** Where does a stored file live right now? @param array<string,mixed> $f identity_files row */
function admin_identity_file_location(array $f): string
{
    $inArchive = $f['archive_ref'] !== null && $f['archive_ref'] !== '';
    $inInbox = $f['inbox_name'] !== null && $f['inbox_name'] !== '';
    if ($inArchive && $inInbox) {
        return __('secure archive + web server inbox (inbox copy not deleted yet)');
    }
    return $inArchive ? __('secure archive') : ($inInbox ? __('web server inbox') : __('nowhere'));
}

/** Human readable size, e.g. "142 KB". */
function admin_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, '.', '') . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
}

/**
 * The newest identity request of a profile that can still be checked/archived (uploaded or verified), or null.
 *
 * @return array<string,mixed>|null
 */
function admin_identity_open_request(int $profileId): ?array
{
    return db_one('SELECT * FROM identity_requests WHERE profile_id = ? AND status IN (\'uploaded\',\'verified\') ORDER BY id DESC LIMIT 1', [$profileId]);
}

/** Identity requests of a customer, newest first, with profile slug and number of stored files. @return array<int,array<string,mixed>> */
function admin_identity_requests_of_user(int $userId): array
{
    return db_all(
        'SELECT r.*, p.slug AS profile_slug,
                (SELECT COUNT(*) FROM identity_files f WHERE f.request_id = r.id AND f.deleted_at IS NULL) AS file_count
           FROM identity_requests r LEFT JOIN profiles p ON p.id = r.profile_id
          WHERE r.user_id = ? ORDER BY r.id DESC',
        [$userId]
    );
}

/**
 * Mark an identity request verified by an admin and move its files to the archive.
 * Never throws: an archive failure must not undo the approval (the caller shows a warning instead).
 *
 * @return array{request_id:int,archived:int,failed:int,errors:string[]}|null null when the profile has no open identity request
 */
function admin_identity_verify_and_archive(int $profileId, int $adminId): ?array
{
    $req = admin_identity_open_request($profileId);
    if ($req === null) {
        return null;
    }
    $rid = (int) $req['id'];
    if ($req['status'] === 'uploaded') {
        db_exec('UPDATE identity_requests SET status = \'verified\', verified_at = ?, verified_by = ?, updated_at = ? WHERE id = ? AND status = \'uploaded\'', [now(), $adminId, now(), $rid]);
    }
    try {
        $res = identity_archive_request($rid);
    } catch (Throwable $e) {
        error_log('identity archive failed (request ' . $rid . '): ' . $e->getMessage());
        $res = ['archived' => 0, 'failed' => 1, 'errors' => [$e->getMessage()]];
    }
    return ['request_id' => $rid] + $res;
}

/**
 * Record that the identity was verified by other proof (no upload). Creates a 'verified' request without files that carries the
 * admin's note (the note is erased together with the account; the audit log only gets the request id).
 *
 * @return int identity request id
 */
function admin_identity_record_override(int $userId, int $profileId, int $adminId, string $note): int
{
    $open = admin_identity_open_request($profileId);
    $t = now();
    if ($open !== null) {
        db_exec('UPDATE identity_requests SET status = \'verified\', verified_at = ?, verified_by = ?, note = ?, updated_at = ? WHERE id = ?', [$t, $adminId, $note, $t, (int) $open['id']]);
        return (int) $open['id'];
    }
    return db_insert(
        'INSERT INTO identity_requests (user_id, profile_id, status, created_at, updated_at, verified_at, verified_by, note) VALUES (?,?,?,?,?,?,?,?)',
        [$userId, $profileId, 'verified', $t, $t, $t, $adminId, $note]
    );
}

/* ------------------------------------------------------ deletion requests */

/** @return string[] */
function admin_deletion_statuses(): array
{
    return ['requested', 'confirmed', 'on_hold', 'completed', 'cancelled'];
}

/** @return array<string,string> */
function admin_deletion_labels(): array
{
    return ['requested' => __('Requested'), 'confirmed' => __('Confirmed – waiting'), 'on_hold' => __('On hold (legal hold)'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled')];
}

function admin_deletion_badge(string $status): string
{
    $class = ['requested' => 'new', 'confirmed' => 'pending', 'on_hold' => 'suspended', 'completed' => 'ok', 'cancelled' => 'draft'];
    $labels = admin_deletion_labels();
    return '<span class="pf-badge pf-badge--' . h($class[$status] ?? 'draft') . '">' . h($labels[$status] ?? $status) . '</span>';
}

/**
 * Deletion requests for the admin list. E-mail/name come from the live account only: erased accounts are gone (user_id NULL),
 * so completed rows carry no personal data at all.
 *
 * @return array<int,array<string,mixed>>
 */
function admin_deletion_requests(string $status = ''): array
{
    $sql = 'SELECT d.id, d.user_ref, d.status, d.created_at, d.confirmed_at, d.completed_at, d.counts_json, d.user_id,
                   u.email AS user_email, u.legal_hold AS user_hold, u.legal_hold_note AS user_hold_note
              FROM deletion_requests d LEFT JOIN users u ON u.id = d.user_id';
    $params = [];
    if ($status !== '') {
        $sql .= ' WHERE d.status = ?';
        $params[] = $status;
    }
    $rows = db_all($sql . ' ORDER BY d.created_at DESC, d.id DESC LIMIT 500', $params);
    foreach ($rows as &$r) {
        $r['counts'] = json_arr((string) $r['counts_json']);
        if ($r['status'] === 'completed') {
            $r['user_email'] = null;               // belt and braces: never show personal data for completed rows
            $r['user_hold_note'] = '';
        }
    }
    unset($r);
    return $rows;
}

/** Number of deletion requests per status. @return array<string,int> */
function admin_deletion_counts(): array
{
    $out = array_fill_keys(admin_deletion_statuses(), 0);
    foreach (db_all('SELECT status, COUNT(*) AS n FROM deletion_requests GROUP BY status') as $r) {
        $out[(string) $r['status']] = (int) $r['n'];
    }
    return $out;
}
