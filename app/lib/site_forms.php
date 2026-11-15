<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Spam/abuse protection for PUBLIC forms (no sessions, no cookies): signed time token, honeypot,
 * same-origin check. Rate limiting uses rate_limit_hit() with config 'rate.request_ip'.
 */

/** Secret for the form tokens, stored in data_dir()/site-secret (mode 0600, created on first use). */
function site_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $file = data_dir() . '/site-secret';
    for ($try = 0; $try < 6; $try++) {
        if (is_file($file)) {
            $value = trim((string) @file_get_contents($file));
            if (strlen($value) >= 32) {
                return $secret = $value;
            }
        } else {
            $fh = @fopen($file, 'x');           // exclusive create: only one process wins the race
            if ($fh !== false) {
                $value = random_token(32);
                fwrite($fh, $value);
                fclose($fh);
                @chmod($file, 0600);
                return $secret = $value;
            }
        }
        usleep(50000);
    }
    throw new RuntimeException('Cannot create the form secret.');
}

/** base64url without padding. */
function site_b64(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/** Time token for a form: "<unix time>.<hmac>". The purpose binds it to one form. */
function site_form_token(string $purpose): string
{
    $ts = (string) now();
    return $ts . '.' . site_b64(hash_hmac('sha256', $purpose . '|' . $ts, site_secret(), true));
}

/**
 * Check a time token.
 *
 * @return string '' if fine, otherwise 'invalid' | 'fast' (submitted in under $minSeconds) | 'expired'
 */
function site_form_token_check(string $purpose, string $token, int $minSeconds = 3, int $maxSeconds = 7200): string
{
    if (preg_match('/^(\d{9,12})\.([A-Za-z0-9_-]{43})$/D', $token, $m) !== 1) {
        return 'invalid';
    }
    $expected = site_b64(hash_hmac('sha256', $purpose . '|' . $m[1], site_secret(), true));
    if (!hash_equals($expected, $m[2])) {
        return 'invalid';
    }
    $age = now() - (int) $m[1];
    if ($age < $minSeconds) {
        return 'fast';
    }
    return $age > $maxSeconds ? 'expired' : '';
}

/** host[:port] of a URL or Host header, lower-case, default ports removed ('' if unusable). */
function site_host_key(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }
    if (strpos($value, '://') !== false) {
        $parts = parse_url($value);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : 0;
    } else {
        $parts = parse_url('//' . $value);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : 0;
    }
    return ($port === 0 || $port === 80 || $port === 443) ? $host : $host . ':' . $port;
}

/**
 * Is this POST request coming from one of our own pages? Origin (or, if missing, Referer) must name this host.
 * Requests without Origin and Referer are refused (browsers always send one of them on form posts).
 */
function site_same_origin(): bool
{
    $host = site_host_key(isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '');
    $own = site_host_key((string) config('site_url'));
    $source = '';
    if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== '' && $_SERVER['HTTP_ORIGIN'] !== 'null') {
        $source = (string) $_SERVER['HTTP_ORIGIN'];
    } elseif (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !== '') {
        $source = (string) $_SERVER['HTTP_REFERER'];
    }
    $key = site_host_key($source);
    return $key !== '' && ($key === $host || $key === $own);
}
