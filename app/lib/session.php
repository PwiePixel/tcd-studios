<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Start the session of an area. Customer portal and admin panel use different cookies
 * (name + path), so being logged in to one never grants anything in the other.
 *
 * @param string $area 'portal' or 'admin'
 */
function session_boot(string $area): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if ($area !== 'portal' && $area !== 'admin') {
        throw new InvalidArgumentException('unknown area');
    }
    $GLOBALS['TCD_AREA'] = $area;
    set_exception_handler(static function (Throwable $e): void {
        error_log('app: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        abort(500, __('Something went wrong on our side. Please try again later.'));
    });
    $idle = (int) config($area === 'admin' ? 'admin_idle_seconds' : 'portal_idle_seconds', 1800);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) max($idle, 1800));
    ini_set('session.save_path', session_dir());
    session_name($area === 'admin' ? 'tcd_admin' : 'tcd_portal');
    session_cache_limiter('');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/' . $area . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => $area === 'admin' ? 'Strict' : 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('session_start() failed');
    }
    send_security_headers();

    if (random_int(1, 150) === 1) {
        try {
            identity_inbox_gc();           // stale, never-reviewed ID uploads must not stay on the web server
        } catch (Throwable $e) {
            error_log('identity_inbox_gc failed: ' . $e->getMessage());
        }
    }

    $t = now();
    if (isset($_SESSION['last_seen']) && $t - (int) $_SESSION['last_seen'] > $idle) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = $t;
}

/** Writable directory for session files (own directory so other apps' garbage collection cannot delete ours). */
function session_dir(): string
{
    $dir = data_dir() . '/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return is_dir($dir) && is_writable($dir) ? $dir : (string) session_save_path();
}

/** Area of the current request ('portal' or 'admin'). */
function current_area(): string
{
    return isset($GLOBALS['TCD_AREA']) ? (string) $GLOBALS['TCD_AREA'] : 'portal';
}

/** Queue a flash message ('ok' or 'error') for the next page. */
function flash_add(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['flash'][] = [$type === 'ok' ? 'ok' : 'error', $message];
}

/** Fetch and clear queued flash messages. @return array<int,array{0:string,1:string}> */
function flash_pop(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['flash'])) {
        return [];
    }
    $messages = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $messages;
}
