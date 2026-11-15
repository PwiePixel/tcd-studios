<?php
declare(strict_types=1);

/**
 * Regenerate every static imprint page (legal/<slug>/index.html), the redirect pages for the old
 * legal-notice-<name>/ URLs and sitemap.xml from the database.
 *
 *   php tools/publish-all.php
 *
 * Environment (all optional): TCD_DATA_DIR (database), TCD_LEGAL_DIR (output dir for the imprints),
 * TCD_ROOT_OUT (output dir for the redirect pages), TCD_SITEMAP_FILE, TCD_SITE_URL.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$status = 0;
try {
    $n = imprint_publish_all();
    echo 'Imprint pages written: ' . $n . ' (' . legal_dir() . ")\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Imprint pages: ' . $e->getMessage() . "\n");
    $status = 1;
}

try {
    imprint_write_legacy_redirects();
    echo 'Redirect pages written to ' . imprint_root_out() . "/legal-notice-<name>/\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Redirect pages: ' . $e->getMessage() . "\n");
    $status = 1;
}

try {
    $urls = imprint_write_sitemap();
    echo 'Sitemap written: ' . $urls . ' URLs (' . imprint_sitemap_path() . ")\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Sitemap: ' . $e->getMessage() . "\n");
    $status = 1;
}

exit($status);
