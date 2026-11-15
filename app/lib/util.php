<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/** HTML-escape for output. */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Translation hook for user-facing strings (English only for now). */
function __(string $text): string
{
    return $text;
}

/** Current unix time (single place, easy to fake in tests via TCD_FAKE_NOW). */
function now(): int
{
    $fake = getenv('TCD_FAKE_NOW');
    return ($fake !== false && ctype_digit($fake)) ? (int) $fake : time();
}

/** Format a unix time as "Y-m-d H:i" (UTC). */
function fmt_time(?int $ts): string
{
    return $ts ? gmdate('Y-m-d H:i', $ts) . ' UTC' : '–';
}

/** Cryptographically secure random token, hex encoded (2 chars per byte). */
function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/** Directory for runtime data (database, local config). Created on demand. */
function data_dir(): string
{
    $env = getenv('TCD_DATA_DIR');
    $dir = ($env !== false && $env !== '') ? $env : TCD_ROOT . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return rtrim($dir, '/');
}

/**
 * Read a setting by dotted key, e.g. config('mail.contact'). Defaults come from app/config.php,
 * overrides from data/config.local.php.
 *
 * @param mixed $default
 * @return mixed
 */
function config(string $key, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config.php';
        $local = data_dir() . '/config.local.php';
        if (is_file($local)) {
            $over = (static function () use ($local) {
                defined('TCD_APP') || exit;
                return require $local;
            })();
            if (is_array($over)) {
                $cfg = array_replace_recursive($cfg, $over);
            }
        }
        $site = getenv('TCD_SITE_URL');
        if ($site !== false && $site !== '') {
            $cfg['site_url'] = rtrim($site, '/');
        }
    }
    $node = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return $default;
        }
        $node = $node[$part];
    }
    return $node;
}

/** Absolute URL for a path on this site, e.g. site_url('/portal/login.php'). */
function site_url(string $path = ''): string
{
    return rtrim((string) config('site_url'), '/') . '/' . ltrim($path, '/');
}

/** Lower-case URL slug: letters, digits and single hyphens. */
function slugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        if (is_string($t)) {
            $text = $t;
        }
    }
    $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/** Decode JSON to an array; invalid input yields an empty array. */
function json_arr(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/** Encode an array as JSON for storage. */
function json_str(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('json_encode failed');
    }
    return $json;
}

/** Number of characters (not bytes). */
function str_len(string $text): int
{
    return mb_strlen($text, 'UTF-8');
}

/** Constant-time string comparison. */
function safe_equals(string $a, string $b): bool
{
    return hash_equals($a, $b);
}
