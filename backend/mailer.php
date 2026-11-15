<?php
declare(strict_types=1);

if (!defined('TCD_API')) {
    http_response_code(403);
    exit;
}

/**
 * Building and sending the notification e-mail (plain text, UTF-8, mail()).
 *
 * Header injection is prevented twice: every value that reaches a header goes
 * through tcd_header_clean() (no control characters, so no CR/LF), and the
 * only visitor-controlled header value, Reply-To, must also pass
 * tcd_is_valid_email().
 */

/**
 * Remove every control character (including CR and LF) from a header value.
 *
 * @param string $value Raw value
 * @return string Single-line value
 */
function tcd_header_clean(string $value): string
{
    $clean = preg_replace('/[\x00-\x1F\x7F]+/', '', $value);
    return trim($clean === null ? '' : $clean);
}

/**
 * Strict e-mail check used for validation and for the Reply-To header:
 * FILTER_VALIDATE_EMAIL plus a length cap and a ban on characters that are
 * legal in exotic quoted local parts but have no business in a contact form
 * (quotes, backslash, angle brackets, comma, semicolon, colon, brackets).
 *
 * @param string $email Address to check
 * @return bool
 */
function tcd_is_valid_email(string $email): bool
{
    return strlen($email) <= 254
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && preg_match('/^[^\s"\'<>(),;:\\\\\[\]\x00-\x1F\x7F]+$/D', $email) === 1;
}

/**
 * RFC 2047 encode a header text if it contains non-ASCII characters; pure
 * ASCII is returned unchanged. Long text is split into several encoded words
 * (max 60 characters each, below the 75 allowed) joined by header folding.
 *
 * @param string $text Header text (UTF-8)
 * @return string
 */
function tcd_mime_encode(string $text): string
{
    $text = tcd_header_clean($text);
    if (preg_match('/^[\x20-\x7E]*$/D', $text) === 1) {
        return $text;
    }

    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false) {
        $chars = str_split($text);                       // not valid UTF-8; still stay safe
    }
    $words = [];
    $current = '';
    foreach ($chars as $char) {
        if ($current !== '' && strlen($current) + strlen($char) > 36) {   // 36 bytes -> 48 base64 chars
            $words[] = $current;
            $current = '';
        }
        $current .= $char;
    }
    $words[] = $current;

    $encoded = [];
    foreach ($words as $word) {
        $encoded[] = '=?UTF-8?B?' . base64_encode($word) . '?=';
    }
    return implode("\r\n ", $encoded);
}

/**
 * Format "Display Name <address>" for a From header.
 *
 * @param string $name  Display name
 * @param string $email Address (already validated)
 * @return string
 */
function tcd_format_address(string $name, string $email): string
{
    $name = tcd_header_clean($name);
    if ($name === '') {
        return $email;
    }
    $display = tcd_mime_encode($name);
    if ($display === $name) {                            // ASCII: use a quoted string
        $display = '"' . addcslashes($name, "\"\\") . '"';
    }
    // Encoded words may be folded onto several lines; keep the address on its own line then.
    return $display . (strpos($display, "\r\n") === false ? ' ' : "\r\n ") . '<' . $email . '>';
}

/**
 * Build the plain-text body (CRLF line endings).
 *
 * @param array<string, mixed>  $config Backend configuration
 * @param array<string, string> $values Cleaned field values keyed by field id
 * @return string
 */
function tcd_mail_body(array $config, array $values): string
{
    $messageId = $config['roles']['message'];
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    $ua = substr(tcd_header_clean($ua), 0, 255);

    $lines = [
        (string) $config['mail_subject'],
        '',
        'Submitted: ' . gmdate('Y-m-d H:i:s') . ' UTC',
        '',
    ];
    foreach ($config['fields'] as $id => $label) {
        $value = isset($values[$id]) ? $values[$id] : '';
        if ($value === '') {
            $value = '-';
        }
        if ($id === $messageId) {
            $lines[] = '';
            $lines[] = $label . ':';
            $lines[] = $value;
        } else {
            $lines[] = $label . ': ' . $value;
        }
    }
    $lines[] = '';
    $lines[] = '--';
    $lines[] = 'IP address: ' . tcd_client_ip();
    $lines[] = 'User agent: ' . ($ua === '' ? '-' : $ua);

    return str_replace("\n", "\r\n", implode("\n", $lines)) . "\r\n";
}

/**
 * Send the notification mail.
 *
 * @param array<string, mixed>  $config Backend configuration
 * @param array<string, string> $values Cleaned field values keyed by field id
 * @return bool true if mail() accepted the message for delivery
 */
function tcd_mail_send(array $config, array $values): bool
{
    $recipient = (string) $config['recipient'];
    $sender = (string) $config['sender'];
    if (!function_exists('mail') || !tcd_is_valid_email($recipient) || !tcd_is_valid_email($sender)) {
        error_log('tcd-api: mail() unavailable or recipient/sender in backend/config.php is invalid');
        return false;
    }

    $subject = tcd_mime_encode((string) $config['mail_subject']);
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . tcd_format_address((string) $config['site_name'], $sender),
    ];
    $email = isset($values[$config['roles']['email']]) ? $values[$config['roles']['email']] : '';
    if ($email !== '' && tcd_is_valid_email($email)) {
        $headers[] = 'Reply-To: ' . tcd_header_clean($email);
    }
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: quoted-printable';

    $body = quoted_printable_encode(tcd_mail_body($config, $values));

    // "-f" sets the envelope sender (return path); only with a strict address.
    $params = '';
    $strict = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+$/D';
    if (!empty($config['use_envelope_sender']) && preg_match($strict, $sender) === 1) {
        $params = '-f' . $sender;
    }

    $sent = mail($recipient, $subject, $body, implode("\r\n", $headers), $params);
    if (!$sent) {
        error_log('tcd-api: mail() returned false (check sendmail/SMTP settings of the host)');
    }
    return $sent;
}
