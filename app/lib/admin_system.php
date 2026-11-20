<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * System checks for the admin panel (settings page + dashboard warning).
 * Each check is ['label' => string, 'status' => 'ok'|'warn'|'fail', 'detail' => string].
 */

/** Is $dir (existing or not) inside the web root (TCD_ROOT)? */
function admin_inside_web_root(string $dir): bool
{
    $root = realpath(TCD_ROOT);
    $real = realpath($dir);
    if ($root === false || $real === false) {
        return false;
    }
    return $real === $root || strpos($real, $root . DIRECTORY_SEPARATOR) === 0;
}

/** Writable check that also accepts a not yet existing directory whose parent is writable. */
function admin_dir_writable(string $dir): bool
{
    if (is_dir($dir)) {
        return is_writable($dir);
    }
    $parent = dirname($dir);
    return is_dir($parent) && is_writable($parent);
}

/** @return array<int,array{label:string,status:string,detail:string}> */
function admin_system_checks(): array
{
    $checks = [];
    $add = static function (string $label, string $status, string $detail) use (&$checks): void {
        $checks[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
    };

    $php = PHP_VERSION;
    $add(__('PHP version'), version_compare($php, '7.4.0', '>=') ? 'ok' : 'fail', $php . ' (' . __('required') . ': ≥ 7.4)');

    foreach (['pdo_sqlite' => 'fail', 'mbstring' => 'fail', 'openssl' => 'fail', 'gd' => 'warn'] as $ext => $level) {
        $loaded = extension_loaded($ext);
        $add(sprintf(__('PHP extension %s'), $ext), $loaded ? 'ok' : $level, $loaded ? __('loaded') : __('missing'));
    }

    $data = data_dir();
    $add(__('Data directory writable'), is_dir($data) && is_writable($data) ? 'ok' : 'fail', is_dir($data) ? (is_writable($data) ? __('writable') : __('not writable')) : __('missing'));

    if (admin_inside_web_root($data)) {
        $add(
            __('Data directory location'),
            'warn',
            __('The data directory is inside the web root and only protected by data/.htaccess (Apache). Move it outside the web root: run installer.sh on the server (it moves the data and writes app/data-dir.local), or point to it with the environment variable TCD_DATA_DIR.')
        );
        $add(__('Data directory .htaccess'), is_file($data . '/.htaccess') ? 'ok' : 'fail', is_file($data . '/.htaccess') ? __('present') : __('missing – the database could be downloaded!'));
    } else {
        $add(__('Data directory location'), 'ok', __('outside the web root'));
    }

    $legal = legal_dir();
    $add(__('Imprint directory writable'), admin_dir_writable($legal) ? 'ok' : 'fail', $legal === '' ? '' : (is_dir($legal) ? __('writable') : __('will be created on first publish')));

    $sessions = session_dir();
    $add(__('Session directory writable'), is_dir($sessions) && is_writable($sessions) ? 'ok' : 'fail', is_dir($sessions) && is_writable($sessions) ? __('writable') : __('not writable'));

    $add(__('HTTPS'), is_https() ? 'ok' : 'warn', is_https() ? __('this page was served over HTTPS') : __('this page was NOT served over HTTPS – cookies are not marked Secure'));

    $mc = admin_mail_delivery_check();
    $add(__('Mail delivery'), $mc['status'], $mc['detail']);
    $mailLog = getenv('TCD_MAIL_LOG');
    if ($mailLog !== false && $mailLog !== '') {
        $add(__('Mail test mode'), 'warn', __('Test mode: mails are written to a log file instead of being sent (TCD_MAIL_LOG).'));
    }

    if (is_file(admin_setup_token_file()) && admin_exists()) {
        $add(__('Setup token'), 'warn', __('An old setup token file is still in the data directory (setup-token-….txt). Delete it.'));
    }

    foreach (admin_vault_checks() as $c) {        // identity vault, archive, data key, inbox age, secure_delete
        $checks[] = $c;
    }

    return $checks;
}

/** Number of checks that are not "ok". */
function admin_system_problem_count(array $checks): int
{
    $n = 0;
    foreach ($checks as $c) {
        if ($c['status'] !== 'ok') {
            $n++;
        }
    }
    return $n;
}

/**
 * Ask our own web server whether the data directory is reachable (GET /data/.htaccess).
 * Only meaningful when the data dir lies inside the web root.
 *
 * @return array{status:string,detail:string}
 */
function admin_probe_data_exposure(): array
{
    $url = site_url('/data/.htaccess');
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 4, 'ignore_errors' => true, 'follow_location' => 0, 'header' => "Connection: close\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('~^HTTP/\S+\s+(\d{3})~', (string) $http_response_header[0], $m) === 1) {
        $code = (int) $m[1];
    }
    if ($body === false && $code === 0) {
        return ['status' => 'warn', 'detail' => __('The probe request failed (no answer). Check manually that /data/ is not downloadable.')];
    }
    if ($code === 200) {
        return ['status' => 'fail', 'detail' => __('DANGER: /data/ is reachable over HTTP. Move the data directory out of the web root now.')];
    }
    return ['status' => 'ok', 'detail' => sprintf(__('/data/.htaccess answers with HTTP %d (not downloadable).'), $code)];
}


/* ------------------------------------------------- operator settings (data/config.local.php) */

/** Defaults from app/config.php (without local overrides or environment). @return array<string,mixed> */
function admin_config_defaults(): array
{
    static $defaults = null;
    if ($defaults === null) {
        $loaded = require TCD_ROOT . '/app/config.php';
        $defaults = is_array($loaded) ? $loaded : [];
    }
    return $defaults;
}

/**
 * Look up a dotted key in a nested array.
 *
 * @param array<string,mixed> $arr
 * @return array{0:bool,1:mixed} [found, value]
 */
function admin_array_dotted(array $arr, string $dotted): array
{
    $node = $arr;
    foreach (explode('.', $dotted) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return [false, null];
        }
        $node = $node[$part];
    }
    return [true, $node];
}

/** Environment variable that overrides a setting on this server ('' if none). */
function admin_setting_env_name(string $key): string
{
    $map = ['site_url' => 'TCD_SITE_URL', 'vault.dir' => 'TCD_VAULT_DIR'];
    if (!isset($map[$key])) {
        return '';
    }
    $v = getenv($map[$key]);
    return $v !== false && $v !== '' ? $map[$key] : '';
}

/**
 * Where a setting comes from and what it is.
 *   configured: the value in data/config.local.php, or else the default (what the admin panel changes)
 *   effective:  what the application uses right now (environment variables win)
 *   source:     'env' | 'local' | 'default'
 *
 * @param array<string,mixed>|null $local config.local.php contents (loaded once by the caller)
 * @return array{configured:mixed,effective:mixed,source:string,env:string}
 */
function admin_setting_info(string $key, ?array $local = null): array
{
    $local = $local ?? config_local_load();
    [$inLocal, $localValue] = admin_array_dotted($local, $key);
    [, $default] = admin_array_dotted(admin_config_defaults(), $key);
    $configured = $inLocal ? $localValue : $default;
    $env = admin_setting_env_name($key);
    $effective = $configured;
    if ($env !== '') {
        $effective = rtrim((string) getenv($env), '/');
    }
    return ['configured' => $configured, 'effective' => $effective, 'source' => $env !== '' ? 'env' : ($inLocal ? 'local' : 'default'), 'env' => $env];
}

/** Human readable source of a setting. @param array{source:string,env:string} $info */
function admin_setting_source_label(array $info): string
{
    if ($info['source'] === 'env') {
        return sprintf(__('environment variable %s (overrides the saved value)'), $info['env']);
    }
    return $info['source'] === 'local' ? __('saved in data/config.local.php') : __('default (app/config.php)');
}

/** Display text of a setting value (secrets: only "set"/"not set"). @param mixed $value */
function admin_setting_display(string $key, $value): string
{
    if (config_setting_is_secret($key)) {
        return (string) $value !== '' ? __('set') : __('not set');
    }
    if (is_bool($value)) {
        return $value ? __('yes') : __('no');
    }
    if ($value === null || $value === '') {
        return '—';
    }
    return is_scalar($value) ? (string) $value : '';
}

/** Comparable form of a stored value (config files may hold "587" or 587, "1" or true). @param mixed $value */
function admin_setting_norm(string $key, $value): string
{
    $type = config_setting_keys()[$key] ?? 'text';
    if ($type === 'bool') {
        return !empty($value) && $value !== 'false' ? '1' : '0';
    }
    if (in_array($type, ['url', 's3url', 'path'], true)) {
        return rtrim((string) $value, '/');
    }
    return is_scalar($value) ? (string) $value : '';
}

/**
 * Validate submitted raw values and work out what changes (only whitelisted, non-secret keys).
 *
 * @param array<string,string> $raw dotted key => raw string (booleans as "true"/"false")
 * @param array<string,mixed>  $local config.local.php contents
 * @return array{errors:array<string,string>,changes:array<string,mixed>}
 */
function admin_settings_diff(array $raw, array $local): array
{
    $errors = [];
    $changes = [];
    foreach ($raw as $key => $value) {
        $err = config_setting_error($key, $value);
        if ($err !== null) {
            $errors[$key] = __($err);
            continue;
        }
        $new = config_setting_cast($key, $value);
        $info = admin_setting_info($key, $local);
        if (admin_setting_norm($key, $new) !== admin_setting_norm($key, $info['configured'])) {
            $changes[$key] = $new;
        }
    }
    return ['errors' => $errors, 'changes' => $changes];
}

/** Raw POST value of a write-only secret ('' if missing). Not trimmed unless asked (passwords may contain spaces). */
function admin_post_secret(string $key, bool $trim = false): string
{
    $v = isset($_POST[$key]) && is_string($_POST[$key]) ? substr($_POST[$key], 0, 1100) : '';
    return $trim ? trim($v) : $v;
}

/**
 * Step-up check: the admin's current password (rate limited per admin).
 *
 * @param array<string,mixed> $admin
 * @return string|null error message or null if the password is right
 */
function admin_stepup_error(array $admin, string $password, string $why): ?string
{
    $rk = 'admin_stepup:' . (int) $admin['id'];
    $limit = [8, 900];
    if (!rate_limit_peek($rk, $limit)) {
        return __('Too many wrong passwords. Please try again in 15 minutes.');
    }
    if ($password === '' || !password_verify($password, (string) $admin['password_hash'])) {
        rate_limit_hit($rk, $limit);
        return $password === '' ? $why : __('The current password is wrong.') . ' ' . $why;
    }
    return null;
}

/* ------------------------------------------------- SMTP connection test (never sends a mail) */

/** SMTP settings as used by the mail system. @return array<string,mixed> */
function admin_smtp_config(): array
{
    $c = (array) config('mail.smtp', []);
    return [
        'host' => (string) ($c['host'] ?? ''), 'port' => (int) ($c['port'] ?? 587), 'encryption' => (string) ($c['encryption'] ?? 'tls'),
        'username' => (string) ($c['username'] ?? ''), 'password' => (string) ($c['password'] ?? ''), 'timeout' => (int) ($c['timeout'] ?? 15),
    ];
}

/** Is the connection test available (app/lib/smtp.php loaded)? */
function admin_smtp_test_available(): bool
{
    return function_exists('smtp_check');
}

/** Last stored result of the connection test, or null. @return array{at:int,ok:bool,detail:string}|null */
function admin_smtp_last_test(): ?array
{
    $raw = setting_get('mail.smtp_test');
    if ($raw === null || $raw === '') {
        return null;
    }
    $d = json_arr((string) $raw);
    return isset($d['at']) ? ['at' => (int) $d['at'], 'ok' => !empty($d['ok']), 'detail' => (string) ($d['detail'] ?? '')] : null;
}

/** Forget the last connection test (after the SMTP settings changed). */
function admin_smtp_forget_test(): void
{
    db_exec('DELETE FROM settings WHERE k = ?', ['mail.smtp_test']);
}

/**
 * Connect to the configured SMTP server, start TLS and log in – via smtp_check() from app/lib/smtp.php – WITHOUT sending
 * any e-mail. The result is remembered for the system checks. Secrets never appear in the returned detail.
 *
 * @return array{ok:bool,detail:string}
 */
function admin_smtp_test(): array
{
    $cfg = admin_smtp_config();
    if (!admin_smtp_test_available()) {
        return ['ok' => false, 'detail' => __('The connection test is not available on this installation yet (app/lib/smtp.php is missing).')];
    }
    if ($cfg['host'] === '') {
        return ['ok' => false, 'detail' => __('No SMTP server is configured yet. Enter and save the server first.')];
    }
    $ok = false;
    $detail = '';
    try {
        $res = smtp_check();                     // connect, EHLO, STARTTLS/TLS, AUTH, QUIT – no MAIL FROM, no DATA
        $ok = is_array($res) && !empty($res['ok']);
        $detail = is_array($res) && isset($res['detail']) && is_scalar($res['detail']) ? (string) $res['detail'] : '';
    } catch (Throwable $e) {
        error_log('smtp_check failed: ' . get_class($e));
        $detail = $e instanceof RuntimeException ? $e->getMessage() : '';
    }
    // Never show credentials, whatever the server or the library echoes back.
    if ($cfg['password'] !== '') {
        $detail = str_replace([$cfg['password'], base64_encode($cfg['password']), base64_encode("\0" . $cfg['username'] . "\0" . $cfg['password'])], '***', $detail);
    }
    // Server replies may carry non-UTF-8 bytes (or a cut multi-byte character): scrub them instead of losing the whole text.
    $detail = mb_convert_encoding((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $detail), 'UTF-8', 'UTF-8');
    $detail = mb_substr(trim((string) $detail), 0, 400, 'UTF-8');
    if ($ok) {
        $detail = $detail !== '' ? $detail : sprintf(__('Connected to %s:%d and logged in. No e-mail was sent.'), $cfg['host'], $cfg['port']);
    } else {
        $detail = __('Connection test failed:') . ' ' . ($detail !== '' ? $detail : __('no details available.')) . ' ' . __('No e-mail was sent.');
    }
    setting_set('mail.smtp_test', json_str(['at' => now(), 'ok' => $ok, 'detail' => $detail]));
    return ['ok' => $ok, 'detail' => $detail];
}

/** System check for the way mails leave the server. @return array{status:string,detail:string} */
function admin_mail_delivery_check(): array
{
    if ((string) config('mail.transport', 'mail') === 'smtp') {
        $cfg = admin_smtp_config();
        if ($cfg['host'] === '') {
            return ['status' => 'fail', 'detail' => __('External SMTP server selected, but no server is configured (Settings → Mail delivery).')];
        }
        $t = admin_smtp_last_test();
        $where = $cfg['host'] . ':' . $cfg['port'];
        if ($t === null) {
            return ['status' => 'warn', 'detail' => sprintf(__('External SMTP server %s – connection not tested yet ("Test connection" under Settings → Mail delivery; no e-mail is sent).'), $where)];
        }
        return $t['ok']
            ? ['status' => 'ok', 'detail' => sprintf(__('External SMTP server %s – last connection test succeeded %s ago.'), $where, admin_age($t['at']))]
            : ['status' => 'fail', 'detail' => sprintf(__('External SMTP server %s – last connection test FAILED %s ago: %s'), $where, admin_age($t['at']), $t['detail'])];
    }
    if (!function_exists('mail')) {
        return ['status' => 'fail', 'detail' => __('PHP mail() is selected but not available. Choose "External SMTP server" under Settings → Mail delivery.')];
    }
    return ['status' => 'warn', 'detail' => __('PHP mail() is used: it only works with a mail server on this machine. If your mailboxes are hosted by an external provider, choose "External SMTP server" under Settings → Mail delivery.')];
}
