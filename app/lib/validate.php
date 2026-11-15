<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/** Strict e-mail check (FILTER_VALIDATE_EMAIL + no exotic characters). */
function v_email(string $email): bool
{
    return strlen($email) <= 254
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && preg_match('/^[^\s"\'<>(),;:\\\\\[\]\x00-\x1F\x7F]+$/D', $email) === 1;
}

/** https URL only (no credentials, no control characters), max $maxLen chars. */
function v_https_url(string $url, int $maxLen = 300): bool
{
    if ($url === '' || strlen($url) > $maxLen || preg_match('/[\s\x00-\x1F\x7F<>"\'\\\\]/', $url) === 1) {
        return false;
    }
    $parts = parse_url($url);
    if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }
    return preg_match('/^[A-Za-z0-9.-]+$/', (string) $parts['host']) === 1 && strpos((string) $parts['host'], '.') !== false;
}

/** Text length check in characters. */
function v_len(string $text, int $min, int $max): bool
{
    $n = str_len($text);
    return $n >= $min && $n <= $max;
}

/** Single-line text: no control characters, no newlines. */
function v_oneline(string $text): bool
{
    return preg_match('/[\x00-\x1F\x7F\x{2028}\x{2029}]/u', $text) !== 1;
}
