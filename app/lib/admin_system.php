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
            __('The data directory is inside the web root and only protected by data/.htaccess (Apache). Move it outside the web root and point to it with the environment variable TCD_DATA_DIR (e.g. SetEnv in .htaccess or the PHP-FPM pool).')
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

    $mailLog = getenv('TCD_MAIL_LOG');
    if ($mailLog !== false && $mailLog !== '') {
        $add(__('Mail delivery'), 'warn', __('Test mode: mails are written to a log file instead of being sent (TCD_MAIL_LOG).'));
    } else {
        $add(__('Mail delivery'), function_exists('mail') ? 'ok' : 'fail', function_exists('mail') ? __('PHP mail() is available – use the test button to verify delivery') : __('PHP mail() is not available'));
    }

    if (is_file($data . '/setup-token.txt') && admin_exists()) {
        $add(__('Setup token'), 'warn', __('An old setup-token.txt is still in the data directory. Delete it.'));
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
