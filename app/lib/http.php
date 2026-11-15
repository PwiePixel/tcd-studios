<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/** Client IP from REMOTE_ADDR only (X-Forwarded-For is attacker controlled and never trusted). */
function client_ip(): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
}

/** Is the request served over HTTPS (also behind a TLS terminating proxy)? */
function is_https(): bool
{
    $https = isset($_SERVER['HTTPS']) ? strtolower((string) $_SERVER['HTTPS']) : '';
    if ($https !== '' && $https !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        return true;
    }
    return isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

function is_post(): bool
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Require POST, otherwise answer 405 and stop. */
function require_post(): void
{
    if (!is_post()) {
        header('Allow: POST');
        abort(405, __('Method not allowed.'));
    }
}

/**
 * Headers every dynamic page carries (strict CSP: no inline scripts/styles).
 *
 * @param array<string,string> $csp optional per-page override of single CSP directives, e.g. ['img-src' => "'self' data: blob:"]
 *                                  (calling it again after session_boot() replaces the header)
 */
function send_security_headers(array $csp = []): void
{
    if (headers_sent()) {
        return;
    }
    $directives = ['default-src' => "'none'", 'style-src' => "'self'", 'script-src' => "'self'", 'img-src' => "'self' data:",
        'font-src' => "'self'", 'form-action' => "'self'", 'base-uri' => "'none'", 'frame-ancestors' => "'none'"];
    foreach ($csp as $name => $value) {
        if (preg_match('/^[a-z-]+$/D', (string) $name) === 1 && preg_match('/^[\x20-\x7E]+$/D', (string) $value) === 1 && strpos((string) $value, ';') === false) {
            $directives[$name] = (string) $value;
        }
    }
    $policy = [];
    foreach ($directives as $name => $value) {
        $policy[] = $name . ' ' . $value;
    }
    header('Content-Security-Policy: ' . implode('; ', $policy));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

/**
 * Stop the request with a plain error page.
 *
 * @return void Never returns.
 */
function abort(int $status, string $message = ''): void
{
    http_response_code($status);
    if (!headers_sent()) {
        send_security_headers();
        header('Content-Type: text/html; charset=utf-8');
    }
    $title = $status === 404 ? __('Page not found') : ($status === 403 ? __('Forbidden') : __('Error'));
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . h($title) . '</title>'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<link rel="stylesheet" href="/fonts/poppins.css"><link rel="stylesheet" href="/css/portal.css"></head>'
        . '<body class="pf-body"><main class="pf-container pf-error-page"><div class="pf-card"><h1>' . h((string) $status) . ' – ' . h($title) . '</h1>'
        . '<p>' . h($message !== '' ? $message : $title) . '</p><p><a class="pf-btn" href="/">' . h(__('Back to the website')) . '</a></p></div></main></body></html>';
    exit;
}

/**
 * Redirect (303) to a local path and stop. Optional flash messages: ['ok' => '…'] or ['error' => '…'].
 *
 * @param array<string,string> $flash
 * @return void Never returns.
 */
function redirect(string $path, array $flash = []): void
{
    if (!preg_match('~^/(?![/\\\\])~', $path) && !preg_match('~^https?://~', $path)) {
        $path = '/' . ltrim($path, '/');
    }
    if (preg_match('~^https?://~', $path)) {
        $host = (string) parse_url($path, PHP_URL_HOST);
        $own = (string) parse_url((string) config('site_url'), PHP_URL_HOST);
        if (strcasecmp($host, $own) !== 0) {
            $path = '/';      // never redirect off-site
        }
    }
    foreach ($flash as $type => $message) {
        flash_add((string) $type, (string) $message);
    }
    http_response_code(303);
    header('Location: ' . str_replace(["\r", "\n"], '', $path));
    exit;
}

/** Trimmed POST string value ('' if missing or not a string). */
function post_str(string $key, int $maxLen = 5000): string
{
    $v = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
    $v = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return mb_substr($v, 0, $maxLen, 'UTF-8');
}

/** POST value as int (0 if missing/invalid). */
function post_int(string $key): int
{
    return isset($_POST[$key]) && is_string($_POST[$key]) && preg_match('/^\d{1,12}$/', $_POST[$key]) === 1 ? (int) $_POST[$key] : 0;
}

/** POST checkbox (present and not "0"/""). */
function post_bool(string $key): bool
{
    return isset($_POST[$key]) && is_string($_POST[$key]) && $_POST[$key] !== '' && $_POST[$key] !== '0';
}

/** GET value as trimmed string. */
function get_str(string $key, int $maxLen = 200): string
{
    $v = isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
    return mb_substr((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $v), 0, $maxLen, 'UTF-8');
}

/** GET value as int (0 if missing/invalid). */
function get_int(string $key): int
{
    return isset($_GET[$key]) && is_string($_GET[$key]) && preg_match('/^\d{1,12}$/', $_GET[$key]) === 1 ? (int) $_GET[$key] : 0;
}
