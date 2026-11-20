<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * System mails (plain text, UTF-8, quoted-printable).
 * Transport: config('mail.transport') = 'smtp' -> the external provider's SMTP server (app/lib/smtp.php, settings
 * mail.smtp.* in the admin panel); anything else -> PHP mail() (needs a local mail server).
 * Test hook: if the environment variable TCD_MAIL_LOG names a file, mails are appended there instead of being sent.
 */

/** Remove control characters (incl. CR/LF) from a header value. */
function mail_header_clean(string $value): string
{
    return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $value));
}

/** RFC 2047 encode a header text when it contains non-ASCII characters. */
function mail_mime_encode(string $text): string
{
    $text = mail_header_clean($text);
    if (preg_match('/^[\x20-\x7E]*$/D', $text) === 1) {
        return $text;
    }
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($text);
    $words = [];
    $current = '';
    foreach ($chars as $c) {
        if ($current !== '' && strlen($current) + strlen($c) > 36) {
            $words[] = $current;
            $current = '';
        }
        $current .= $c;
    }
    $words[] = $current;
    return implode("\r\n ", array_map(static function ($w) {
        return '=?UTF-8?B?' . base64_encode($w) . '?=';
    }, $words));
}

/**
 * Send a plain-text mail.
 *
 * @param string      $to       validated e-mail address
 * @param string|null $replyTo  optional validated address
 * @return bool true if the mail was accepted for delivery
 */
function send_mail(string $to, string $subject, string $text, ?string $replyTo = null): bool
{
    $from = (string) config('mail.from');
    if (!v_email($to) || !v_email($from) || ($replyTo !== null && !v_email($replyTo))) {
        error_log('send_mail: invalid address (to/from/reply-to)');
        return false;
    }
    $siteName = (string) config('site_name', 'TCD-Studio/Agency');
    $headers = [
        'MIME-Version: 1.0',
        'From: "' . addcslashes(mail_header_clean($siteName), "\"\\") . '" <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        'X-Auto-Response-Suppress: All',
    ];
    if ($replyTo !== null) {
        array_splice($headers, 2, 0, ['Reply-To: ' . $replyTo]);
    }
    // Canonical CRLF line breaks BEFORE encoding (quoted_printable_encode() keeps CRLF but would encode a bare LF as =0A).
    $plain = str_replace(["\r\n", "\r"], "\n", $text) . "\n\n--\n" . $siteName . "\n" . (string) config('site_url');
    $body = quoted_printable_encode(str_replace("\n", "\r\n", $plain));

    $log = getenv('TCD_MAIL_LOG');
    if ($log !== false && $log !== '') {
        $entry = "=== MAIL to:" . $to . " subject:" . mail_header_clean($subject) . " ===\n" . $text . "\n=== END ===\n";
        return @file_put_contents($log, $entry, FILE_APPEND | LOCK_EX) !== false;
    }
    if (config('mail.transport') === 'smtp') {
        return mail_send_smtp($from, $to, $subject, $headers, $body);
    }
    $params = preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+$/D', $from) === 1 ? '-f' . $from : '';
    $sent = @mail($to, mail_mime_encode($subject), $body, implode("\r\n", $headers), $params);
    if (!$sent) {
        error_log('send_mail: mail() returned false');
    }
    return $sent;
}

/** Domain part of an address (for Message-ID), 'localhost' when unusable. */
function mail_domain(string $addr): string
{
    $pos = strrpos($addr, '@');
    $domain = $pos === false ? '' : substr($addr, $pos + 1);
    return preg_match('/^[A-Za-z0-9.-]{1,253}$/D', $domain) === 1 ? $domain : 'localhost';
}

/**
 * Deliver via SMTP: the same headers and body as for mail(), plus To, Subject, Date and Message-ID (mail() and the local
 * MTA add those themselves). Failures are logged without secrets.
 *
 * @param array<int,string> $headers
 */
function mail_send_smtp(string $from, string $to, string $subject, array $headers, string $body): bool
{
    $head = array_merge([
        'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . mail_domain($from) . '>',
        'To: ' . $to,
        'Subject: ' . mail_mime_encode($subject),
    ], $headers);
    try {
        smtp_send($from, [$to], implode("\r\n", $head) . "\r\n\r\n" . $body);
        return true;
    } catch (Throwable $e) {
        error_log('send_mail: SMTP delivery failed: ' . ($e instanceof RuntimeException ? $e->getMessage() : get_class($e)));
        return false;
    }
}

/** Notify the team (imprint review queue etc.). */
function notify_admins(string $subject, string $text): bool
{
    return send_mail((string) config('mail.admin_notify'), $subject, $text);
}
