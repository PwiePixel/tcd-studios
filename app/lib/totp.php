<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * TOTP (RFC 6238, HMAC-SHA1, 6 digits, 30 s step) with base32 secrets (RFC 4648). No external libraries.
 * Pure functions; storing secrets / recovery codes lives in app/lib/admin_totp.php.
 */

const TOTP_PERIOD = 30;
const TOTP_DIGITS = 6;
const TOTP_B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** Base32 encode (no padding). */
function totp_base32_encode(string $bin): string
{
    $bits = '';
    $len = strlen($bin);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= TOTP_B32[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $out;
}

/** Base32 decode; tolerant to case, spaces, hyphens and padding. Returns '' for invalid input. */
function totp_base32_decode(string $text): string
{
    $text = strtoupper((string) preg_replace('/[\s=-]+/', '', $text));
    if ($text === '' || preg_match('/^[A-Z2-7]+$/D', $text) !== 1) {
        return '';
    }
    $bits = '';
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin((int) strpos(TOTP_B32, $text[$i])), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

/** New random secret (20 bytes = 160 bit, 32 base32 characters). */
function totp_generate_secret(): string
{
    return totp_base32_encode(random_bytes(20));
}

/** Time step counter for a unix time. */
function totp_step(?int $time = null): int
{
    return intdiv($time ?? now(), TOTP_PERIOD);
}

/** The 6-digit code of a base32 secret for a given step counter. */
function totp_code(string $secret, int $step): string
{
    $key = totp_base32_decode($secret);
    if ($key === '') {
        return '';
    }
    // 8-byte big-endian counter (a step counter stays far below 2^32 for the next ~4000 years)
    $hash = hash_hmac('sha1', "\0\0\0\0" . pack('N', $step), $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
    return str_pad((string) ($value % (10 ** TOTP_DIGITS)), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/**
 * Verify a user supplied code within ±$window steps. All candidate steps are compared in constant time.
 *
 * @return int|null the matching step counter, or null if the code is wrong
 */
function totp_verify(string $secret, string $code, int $window = 1, ?int $time = null): ?int
{
    $code = (string) preg_replace('/[\s-]+/', '', $code);
    if (preg_match('/^\d{' . TOTP_DIGITS . '}$/D', $code) !== 1) {
        return null;
    }
    $center = totp_step($time);
    $found = null;
    for ($i = -$window; $i <= $window; $i++) {
        $expected = totp_code($secret, $center + $i);
        if ($expected !== '' && hash_equals($expected, $code) && $found === null) {
            $found = $center + $i;
        }
    }
    return $found;
}

/** otpauth:// provisioning URI for authenticator apps. */
function totp_uri(string $secret, string $account, string $issuer): string
{
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
        . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=' . TOTP_DIGITS . '&period=' . TOTP_PERIOD;
}

/** Group a secret in blocks of four for easier reading/typing. */
function totp_secret_pretty(string $secret): string
{
    return trim(implode(' ', str_split($secret, 4)));
}
