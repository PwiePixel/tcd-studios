<?php
declare(strict_types=1);

/**
 * Application bootstrap for the Imprint-Protection system (customer portal + admin panel).
 * Every entry script starts with:  require dirname(__DIR__) . '/app/bootstrap.php';
 */

if (defined('TCD_APP')) {
    return;
}
define('TCD_APP', true);
define('TCD_ROOT', dirname(__DIR__));

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', '0');     // errors go to the server log, never to visitors
ini_set('log_errors', '1');

// Core libraries first (fixed order), then every other app/lib/*.php alphabetically.
foreach (['util', 'http', 'db', 'repo', 'session', 'csrf', 'ratelimit', 'audit', 'validate', 'mail', 'auth', 'view', 'imprint_model'] as $lib) {
    require_once __DIR__ . '/lib/' . $lib . '.php';
}
$tcdExtraLibs = glob(__DIR__ . '/lib/*.php');
sort($tcdExtraLibs);
foreach ($tcdExtraLibs as $tcdFile) {
    require_once $tcdFile;
}
unset($tcdExtraLibs, $tcdFile);
