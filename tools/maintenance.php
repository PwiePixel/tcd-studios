<?php
declare(strict_types=1);

/**
 * Maintenance job (run daily by cron, CLI only):  php tools/maintenance.php
 *  - deletes ID uploads that were never reviewed (older than 14 days) from the web server's inbox
 *  - removes stale rate-limit counters
 * Environment (optional): TCD_DATA_DIR.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
$n = identity_inbox_gc(14);
rate_limit_gc();
echo "Removed $n stale ID upload file(s).\n";
