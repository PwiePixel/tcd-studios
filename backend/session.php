<?php
declare(strict_types=1);

if (!defined('TCD_API')) {
    http_response_code(403);
    exit;
}

/**
 * Session helpers. All state of this backend lives in $_SESSION['tcd'] so it
 * cannot collide with anything else on the same host:
 *   'captcha'   => [token => ['answer' => string, 'expires' => int], ...]
 *   'last_sent' => int (unix time of the last successfully sent message)
 */

/**
 * Start the PHP session with a hardened cookie (HttpOnly, SameSite=Lax,
 * Secure on HTTPS, own cookie name). Safe to call more than once.
 *
 * @param array<string, mixed> $config Backend configuration
 * @return void
 * @throws RuntimeException when the session cannot be started
 */
function tcd_session_start(array $config): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // The session file must outlive the captcha it holds; PHP's default garbage
    // collection (24 min) would otherwise delete it before a 30 min captcha expires.
    $needed = (int) $config['captcha_lifetime'] + 300;
    if ((int) ini_get('session.gc_maxlifetime') < $needed) {
        ini_set('session.gc_maxlifetime', (string) $needed);
    }
    ini_set('session.use_strict_mode', '1');   // never adopt client-chosen ids
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('tcd_sid');
    session_cache_limiter('');                  // we send our own Cache-Control
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => tcd_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('session_start() failed (is session.save_path writable?)');
    }
}

/**
 * Read this backend's session state.
 *
 * @return array<string, mixed>
 */
function tcd_state(): array
{
    return (isset($_SESSION['tcd']) && is_array($_SESSION['tcd'])) ? $_SESSION['tcd'] : [];
}

/**
 * Store one value in this backend's session state.
 *
 * @param string $key   State key
 * @param mixed  $value Value (must be serialisable)
 * @return void
 */
function tcd_state_set(string $key, $value): void
{
    $state = tcd_state();
    $state[$key] = $value;
    $_SESSION['tcd'] = $state;
}
