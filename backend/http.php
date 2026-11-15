<?php
declare(strict_types=1);

if (!defined('TCD_API')) {
    http_response_code(403);
    exit;
}

/**
 * HTTP helpers: response writers, the error exception and request inspection.
 */

/**
 * An error that should be reported to the visitor as a plain-text response.
 *
 * The code is the HTTP status (400, 403, 429, 500 ...). The message must be
 * short, English and free of < > & because the browser runtime shows it as HTML.
 */
final class TcdHttpException extends RuntimeException
{
}

/**
 * Throw a TcdHttpException.
 *
 * @param int    $status  HTTP status code
 * @param string $message Plain-text message for the visitor
 * @return void Never returns.
 * @throws TcdHttpException
 */
function tcd_fail(int $status, string $message): void
{
    throw new TcdHttpException($message, $status);
}

/**
 * Send the headers every API response carries.
 *
 * @return void
 */
function tcd_send_common_headers(): void
{
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
}

/**
 * Write a complete response.
 *
 * @param int    $status      HTTP status code
 * @param string $contentType Full Content-Type value
 * @param string $body        Response body
 * @return void
 */
function tcd_respond(int $status, string $contentType, string $body): void
{
    if (!headers_sent()) {
        http_response_code($status);
        tcd_send_common_headers();
        header('Content-Type: ' . $contentType);
    }
    echo $body;
}

/**
 * Write a plain-text response (used for all errors).
 *
 * @param int    $status HTTP status code
 * @param string $text   Message
 * @return void
 */
function tcd_respond_text(int $status, string $text): void
{
    tcd_respond($status, 'text/plain; charset=utf-8', $text);
}

/**
 * Write a JSON response.
 *
 * @param array<string, mixed> $data Data to encode
 * @return void
 */
function tcd_respond_json(array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('json_encode failed');
    }
    tcd_respond(200, 'application/json; charset=utf-8', $json);
}

/**
 * Write the HTML snippet the browser runtime treats as "form sent".
 * It recognises success by the .wv-success class on the root element.
 *
 * @param string $message Success text (escaped here)
 * @return void
 */
function tcd_respond_success_html(string $message): void
{
    $text = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    tcd_respond(200, 'text/html; charset=utf-8', '<div class="wv-message wv-success">' . $text . '</div>');
}

/**
 * Is the current request served over HTTPS (also behind a TLS-terminating proxy)?
 * Only used to decide on the Secure flag of the session cookie.
 *
 * @return bool
 */
function tcd_is_https(): bool
{
    $https = isset($_SERVER['HTTPS']) ? strtolower((string) $_SERVER['HTTPS']) : '';
    if ($https !== '' && $https !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        return true;
    }
    $proto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) : '';
    return $proto === 'https';
}

/**
 * Visitor IP address from REMOTE_ADDR only. X-Forwarded-For is never trusted
 * because any client can set it.
 *
 * @return string IP address or "unknown"
 */
function tcd_client_ip(): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
}

/**
 * Do the Origin and Referer headers (each one that is present) name the same
 * host as the Host header? Requests without both headers (curl, some privacy
 * tools) are let through; browsers always send at least one on a form POST.
 * Only host names are compared, so a TLS-terminating proxy that changes the
 * port or scheme does not cause false alarms.
 *
 * @return bool
 */
function tcd_origin_matches_host(): bool
{
    $hostHeader = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    $host = parse_url('//' . $hostHeader, PHP_URL_HOST);

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
        if (!isset($_SERVER[$key]) || (string) $_SERVER[$key] === '') {
            continue;
        }
        $given = parse_url((string) $_SERVER[$key], PHP_URL_HOST);
        if (!is_string($host) || $host === '' || !is_string($given) || strcasecmp($host, $given) !== 0) {
            return false;
        }
    }
    return true;
}
