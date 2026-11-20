<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Operator settings that live in data/config.local.php and can be changed in the admin panel (and by installer.sh through
 * tools/install-helper.php). One whitelist with validation for both, so neither can write anything else (data_key, db_file …).
 *
 *   config_setting_keys()                 all editable dotted keys
 *   config_setting_error($key, $raw)      English error message or null
 *   config_setting_cast($key, $raw)       typed value to store
 *   config_local_set(['a.b' => value])    merge into data/config.local.php (locked, atomic)
 *   config_setting_is_secret($key)        never shown again after saving (SMTP password, S3 keys)
 */

/** @return array<string,string> dotted key => type (url|email|text|bool|int|choice|path|token|bucket|prefix|secret|s3url|host) */
function config_setting_keys(): array
{
    return [
        'site_url'              => 'url',
        'trust_forwarded_proto' => 'bool',
        'mail.from'             => 'email',
        'mail.contact'          => 'email',
        'mail.info'             => 'email',
        'mail.imprint'          => 'email',
        'mail.privacy'          => 'email',
        'mail.admin_notify'     => 'email',
        'mail.requests'         => 'email',
        'mail.transport'        => 'choice:mail,smtp',
        'mail.smtp.host'        => 'host',
        'mail.smtp.port'        => 'int:1-65535',
        'mail.smtp.encryption'  => 'choice:tls,ssl,none',
        'mail.smtp.username'    => 'text',
        'mail.smtp.password'    => 'secret',
        'vault.driver'          => 'choice:directory,s3',
        'vault.dir'             => 'path',
        'vault.s3.endpoint'     => 's3url',
        'vault.s3.region'       => 'token',
        'vault.s3.bucket'       => 'bucket',
        'vault.s3.prefix'       => 'prefix',
        'vault.s3.path_style'   => 'bool',
        'vault.s3.access_key'   => 'secret',
        'vault.s3.secret_key'   => 'secret',
    ];
}

function config_setting_is_secret(string $key): bool
{
    return (config_setting_keys()[$key] ?? '') === 'secret';
}

/** Is $path the web root or one of its ancestors (both resolved)? */
function config_path_is_web_root_ancestor(string $path): bool
{
    $root = realpath(TCD_ROOT);
    $p = realpath($path);
    if ($root === false || $p === false) {
        return false;
    }
    return $p === '/' || strpos($root . '/', rtrim($p, '/') . '/') === 0;
}

/** Validate one raw (string) value. @return string|null error message */
function config_setting_error(string $key, string $raw): ?string
{
    $types = config_setting_keys();
    if (!isset($types[$key])) {
        return 'Unknown setting: ' . $key;
    }
    $type = $types[$key];
    $url = static function (string $v): bool {
        $p = parse_url($v);
        return is_array($p) && in_array($p['scheme'] ?? '', ['http', 'https'], true) && !empty($p['host']) && !isset($p['user'])
            && !isset($p['query']) && !isset($p['fragment']) && trim((string) ($p['path'] ?? ''), '/') === '' && preg_match('/^[\x21-\x7e]{1,300}$/', $v) === 1;
    };
    if (strncmp($type, 'choice:', 7) === 0) {
        return in_array($raw, explode(',', substr($type, 7)), true) ? null : 'Please choose one of the offered options.';
    }
    if (strncmp($type, 'int:', 4) === 0) {
        [$min, $max] = array_map('intval', explode('-', substr($type, 4)));
        return ctype_digit($raw) && (int) $raw >= $min && (int) $raw <= $max ? null : sprintf('Please enter a number from %d to %d.', $min, $max);
    }
    switch ($type) {
        case 'url':
            return $url($raw) ? null : 'Please enter the full address, e.g. https://tcd-studio.de (no path).';
        case 'email':
            return v_email($raw) ? null : 'Please enter a valid e-mail address.';
        case 'bool':
            return $raw === 'true' || $raw === 'false' ? null : 'Invalid value.';
        case 'text':
            return $raw === '' || (mb_strlen($raw, 'UTF-8') <= 200 && preg_match('/[\x00-\x1f\x7f]/', $raw) !== 1) ? null : 'Use at most 200 characters on one line.';
        case 'secret':
            return preg_match('/^[^\x00-\x1f\x7f]{1,256}$/u', $raw) === 1 ? null : 'Use 1–256 characters without line breaks.';
        case 'host':
            return $raw === '' || (preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/', $raw) === 1) ? null : 'Please enter a host name such as smtp.example.com.';
        case 'token':
            return preg_match('/^[A-Za-z0-9._-]{1,128}$/', $raw) === 1 ? null : 'Use letters, digits, dot, dash or underscore.';
        case 'bucket':
            return preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $raw) === 1 && strpos($raw, '..') === false ? null : 'Bucket names have 3–63 characters: lower-case letters, digits, dot and dash.';
        case 'prefix':
            return $raw === '' || (preg_match('~^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*/?$~', $raw) === 1 && preg_match('~(^|/)\.\.?(/|$)~', $raw) !== 1) ? null : 'Use a prefix such as tcd-id/ (no leading slash, no "..").';
        case 's3url':
            if (!$url($raw)) {
                return 'Please enter the full endpoint, e.g. https://s3.eu-central-1.amazonaws.com.';
            }
            $p = parse_url($raw);
            return ($p['scheme'] ?? '') === 'https' || in_array((string) ($p['host'] ?? ''), ['127.0.0.1', 'localhost'], true) ? null : 'The S3 endpoint must use https://.';
        case 'path':
            if ($raw === '' || $raw[0] !== '/' || rtrim($raw, '/') === '' || strlen($raw) > 1024 || preg_match('/[\x00-\x1f]/', $raw) === 1) {
                return 'Please enter an absolute directory path, e.g. /srv/tcd-vault-archive.';
            }
            if (admin_path_inside_web_root($raw) || config_path_is_web_root_ancestor($raw)) {
                return 'The archive directory must be OUTSIDE the web root (and must not contain it).';
            }
            return null;
    }
    return 'Invalid value.';
}

/** Typed value of a validated raw value. @return mixed */
function config_setting_cast(string $key, string $raw)
{
    $type = config_setting_keys()[$key] ?? 'text';
    if ($type === 'bool') {
        return $raw === 'true';
    }
    if (strncmp($type, 'int:', 4) === 0) {
        return (int) $raw;
    }
    if (in_array($type, ['url', 's3url', 'path'], true)) {
        return rtrim($raw, '/');
    }
    return $raw;
}

/**
 * Merge dotted keys into data/config.local.php under the same lock the application uses (atomic replace, fail-closed when the
 * existing file cannot be read). Only whitelisted keys are accepted.
 *
 * @param array<string,mixed> $values dotted key => typed value
 * @throws RuntimeException|InvalidArgumentException
 */
function config_local_set(array $values): void
{
    $allowed = config_setting_keys();
    foreach (array_keys($values) as $k) {
        if (!isset($allowed[$k])) {
            throw new InvalidArgumentException('Setting not allowed: ' . $k);
        }
    }
    $dir = data_dir();
    $local = $dir . '/config.local.php';
    $lock = @fopen($dir . '/config.local.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Cannot lock data/config.local.lock (is the data directory writable?)');
    }
    try {
        $cfg = config_local_load();
        foreach ($values as $dotted => $value) {
            $node = &$cfg;
            $parts = explode('.', (string) $dotted);
            $last = array_pop($parts);
            foreach ($parts as $part) {
                if (!isset($node[$part]) || !is_array($node[$part])) {
                    $node[$part] = [];
                }
                $node = &$node[$part];
            }
            $node[$last] = $value;
            unset($node);
        }
        $code = "<?php\ndefined('TCD_APP') || exit;\nreturn " . var_export($cfg, true) . ";\n";
        $tmp = $local . '.tmp' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $code, LOCK_EX) === false) {
            @unlink($tmp);
            throw new RuntimeException('Cannot write ' . $local);
        }
        @chmod($tmp, 0640);
        if (!@rename($tmp, $local)) {
            @unlink($tmp);
            throw new RuntimeException('Cannot replace ' . $local);
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($local, true);
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
