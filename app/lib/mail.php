<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * System mails (plain text, UTF-8, quoted-printable) via PHP mail().
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
    $body = quoted_printable_encode(str_replace(["\r\n", "\r"], "\n", $text) . "\n\n--\n" . $siteName . "\n" . (string) config('site_url'));
    $body = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body));

    $log = getenv('TCD_MAIL_LOG');
    if ($log !== false && $log !== '') {
        $entry = "=== MAIL to:" . $to . " subject:" . mail_header_clean($subject) . " ===\n" . $text . "\n=== END ===\n";
        return @file_put_contents($log, $entry, FILE_APPEND | LOCK_EX) !== false;
    }
    $params = preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+$/D', $from) === 1 ? '-f' . $from : '';
    $sent = @mail($to, mail_mime_encode($subject), $body, implode("\r\n", $headers), $params);
    if (!$sent) {
        error_log('send_mail: mail() returned false');
    }
    return $sent;
}

/** Notify the team (imprint review queue etc.). */
function notify_admins(string $subject, string $text): bool
{
    return send_mail((string) config('mail.admin_notify'), $subject, $text);
}
