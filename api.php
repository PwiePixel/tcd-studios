<?php
declare(strict_types=1);

/**
 * Backend entry point for the contact form of tcd-studio.de.
 *
 * The browser runtime of the site calls (always POST, same origin):
 *   /api.php/form_container/captcha  -> JSON {"image": <base64 PNG>, "hash": <token>}
 *   /api.php/form_container/submit   -> HTML .wv-success on success, plain-text error otherwise
 *
 * This file only routes. The logic lives in backend/ (settings: backend/config.php).
 * Requires PHP 7.4+, the GD and session extensions and a working mail().
 */

define('TCD_API', true);
define('TCD_BACKEND_DIR', __DIR__ . '/backend');

error_reporting(E_ALL);
ini_set('display_errors', '0');          // never leak PHP errors to visitors
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');              // they go to the server's error log instead

require_once TCD_BACKEND_DIR . '/http.php';
require_once TCD_BACKEND_DIR . '/session.php';
require_once TCD_BACKEND_DIR . '/captcha.php';
require_once TCD_BACKEND_DIR . '/mailer.php';
require_once TCD_BACKEND_DIR . '/contact.php';

/**
 * Work out which API route was requested. Works with PATH_INFO (Apache, PHP-FPM,
 * php -S) and falls back to REQUEST_URI, so ".../api.php/form_container/captcha"
 * resolves in either case, also when the site lives in a sub-directory.
 *
 * @return string Route without slashes at both ends, e.g. "form_container/captcha"
 */
function tcd_api_route(): string
{
    $info = isset($_SERVER['PATH_INFO']) ? (string) $_SERVER['PATH_INFO'] : '';
    if ($info === '') {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $info = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
    }
    // Some setups put the script name into PATH_INFO as well: keep only what follows it.
    if (preg_match('~(?:^|/)' . preg_quote(basename(__FILE__), '~') . '(?:/(.*))?$~', $info, $match) === 1) {
        $info = isset($match[1]) ? $match[1] : '';
    }
    return trim((string) preg_replace('~/+~', '/', $info), '/');
}

/**
 * Handle the current request.
 *
 * @return void
 */
function tcd_api_main(): void
{
    $routes = [
        'form_container/captcha' => 'tcd_captcha_handle',
        'form_container/submit'  => 'tcd_contact_handle',
    ];

    try {
        $route = tcd_api_route();
        if (!isset($routes[$route])) {
            tcd_respond_text(404, 'Not found.');
            return;
        }
        $method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '';
        if ($method !== 'POST') {
            header('Allow: POST');
            tcd_respond_text(405, 'Method not allowed.');
            return;
        }
        $config = require TCD_BACKEND_DIR . '/config.php';
        if (!is_array($config)) {
            throw new RuntimeException('backend/config.php must return an array');
        }
        $routes[$route]($config);
    } catch (TcdHttpException $e) {
        tcd_respond_text($e->getCode(), $e->getMessage());
    } catch (Throwable $e) {
        error_log('tcd-api: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        tcd_respond_text(500, 'Internal server error.');
    }
}

tcd_api_main();
