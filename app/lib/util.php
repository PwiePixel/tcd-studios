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

/**
 * Directory for runtime data (database, local config).
 * Order: environment variable TCD_DATA_DIR (created on demand), then the one-line file app/data-dir.local (written by installer.sh
 * when the data directory lives outside the web root - it works for the web server AND for cron/CLI without any server
 * configuration), then <web root>/data.
 *
 * Fail closed: a pointer that is unreadable/invalid or points to a missing directory (volume not mounted), or a missing pointer
 * while data/.tcd-moved says the data was moved away, throws instead of silently starting a fresh, empty installation with a new
 * data key next to the real one.
 */
function data_dir(): string
{
    static $resolved = null;
    $env = getenv('TCD_DATA_DIR');
    if ($env !== false && $env !== '') {
        if (!is_dir($env)) {
            @mkdir($env, 0750, true);
        }
        return rtrim($env, '/');
    }
    if ($resolved !== null) {
        return $resolved;
    }
    $pointer = TCD_ROOT . '/app/data-dir.local';
    if (is_file($pointer)) {
        $raw = @file_get_contents($pointer, false, null, 0, 4096);
        $line = $raw === false ? '' : rtrim($raw, "\r\n");
        if ($line === '' || $line[0] !== '/' || preg_match('/[\x00-\x1f]/', $line) === 1) {
            throw new RuntimeException('app/data-dir.local is unreadable or does not contain an absolute path - refusing to continue');
        }
        if (!is_dir($line)) {
            throw new RuntimeException('The data directory named in app/data-dir.local does not exist (storage volume not mounted?) - refusing to continue');
        }
        return $resolved = rtrim($line, '/');
    }
    $dir = TCD_ROOT . '/data';
    if (is_file($dir . '/.tcd-moved')) {
        throw new RuntimeException('data/.tcd-moved says the data directory was moved, but app/data-dir.local is missing - refusing to continue');
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $resolved = $dir;
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
        $over = config_local_load();
        if ($over !== []) {
            $cfg = array_replace_recursive($cfg, $over);
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

/**
 * Load data/config.local.php fresh. PHP's opcache may serve an older copy of a file that was rewritten within the last
 * seconds (opcache.revalidate_freq), which would make a freshly created value look "missing" - so the cache entry is
 * invalidated first.
 *
 * @return array<string,mixed>
 */
function config_local_load(): array
{
    $local = data_dir() . '/config.local.php';
    if (!is_file($local)) {
        return [];
    }
    clearstatcache(true, $local);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($local, true);
    }
    $loaded = (static function () use ($local) {
        return include $local;
    })();
    if (!is_array($loaded)) {
        // Fail closed: treating an unreadable file as empty would let the caller overwrite it (losing the data key, S3 keys ...).
        throw new RuntimeException('data/config.local.php exists but could not be read (syntax error?) - refusing to continue');
    }
    return $loaded;
}

/**
 * Read a value from data/config.local.php, creating it ONCE if it does not exist yet (race-free: two parallel first
 * requests must never generate two different values, e.g. two data keys).
 *
 * @param callable $factory returns the value to store when missing
 * @return mixed
 */
function config_local_get_or_create(string $key, callable $factory)
{
    $local = data_dir() . '/config.local.php';
    $lock = @fopen(data_dir() . '/config.local.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Cannot lock data/config.local.lock (is data/ writable?)');
    }
    try {
        $existing = config_local_load();
        if (array_key_exists($key, $existing) && $existing[$key] !== '' && $existing[$key] !== null) {
            return $existing[$key];
        }
        $value = $factory();
        $existing[$key] = $value;
        $code = "<?php\ndefined('TCD_APP') || exit;\nreturn " . var_export($existing, true) . ";\n";
        $tmp = $local . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $code, LOCK_EX) === false || !@rename($tmp, $local)) {
            throw new RuntimeException('Cannot write ' . $local);
        }
        @chmod($local, 0640);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($local, true);
        }
        return $value;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
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
