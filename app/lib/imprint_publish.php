<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Imprint publishing pipeline: renders the Sitejet imprint page of a live profile to legal/<slug>/index.html
 * (atomically), removes it again, regenerates sitemap.xml and writes the static redirect pages for the old
 * legal-notice-<name>/ URLs. Content model + markup: site_imprint.php, site_frame.php, views/site/frame.php.
 */

/** Directory that receives the generated imprint pages (env TCD_LEGAL_DIR overrides, used by tests). */
function legal_dir(): string
{
    $env = getenv('TCD_LEGAL_DIR');
    return rtrim(($env !== false && $env !== '') ? $env : TCD_ROOT . '/legal', '/');
}

/** Is this a slug that may touch the file system? (checked before ANY path is built) */
function imprint_slug_valid(string $slug): bool
{
    return preg_match('/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/D', $slug) === 1;
}

/** Inner HTML (no site frame) of an imprint, for previews in portal/admin. @param array<string,mixed> $profile @param array<string,mixed> $public */
function imprint_render_fragment(array $profile, array $public): string
{
    return site_imprint_fragment_html(site_imprint_name($profile, $public), site_imprint_blocks($profile, $public));
}

/** Complete HTML page of an imprint. @param array<string,mixed> $profile @param array<string,mixed> $public */
function imprint_render_html(array $profile, array $public): string
{
    $name = site_imprint_name($profile, $public);
    $slug = (string) ($profile['slug'] ?? '');
    return site_frame([
        'title'       => 'Legal Notice - ' . $name,
        'description' => site_imprint_description((string) ($public['kind'] ?? 'creator'), $name),
        'canonical'   => imprint_slug_valid($slug) ? imprint_public_url($slug) : '',
        'content'     => site_imprint_page_body($name, site_imprint_blocks($profile, $public)),
    ]);
}

/**
 * Write a file atomically (temp file in the same directory + rename). Creates missing directories (0755), file mode 0644.
 *
 * @throws RuntimeException
 */
function site_write_atomic(string $file, string $data): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory ' . $dir);
        }
        @chmod($dir, 0755);
    }
    $tmp = $dir . '/.tmp-' . bin2hex(random_bytes(6));
    if (@file_put_contents($tmp, $data, LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException('Cannot write ' . $tmp);
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Cannot replace ' . $file);
    }
}

/** Render + write one live profile (no sitemap update). @throws RuntimeException */
function imprint_publish_page(int $profileId): void
{
    $profile = profile_get($profileId);
    if (!$profile || !imprint_slug_valid((string) $profile['slug'])) {
        throw new RuntimeException('Nothing to publish.');
    }
    if (in_array((string) $profile['status'], ['suspended', 'withdrawn'], true)) {
        throw new RuntimeException('This imprint is ' . $profile['status'] . ' and must not be published.');
    }
    $live = revision_live($profileId);
    if (!$live) {
        throw new RuntimeException('Nothing to publish.');
    }
    $html = imprint_render_html($profile, $live['public']);
    site_write_atomic(legal_dir() . '/' . $profile['slug'] . '/index.html', $html);
}

/** Write legal/<slug>/index.html for the LIVE revision of a profile. @throws RuntimeException */
function imprint_publish(int $profileId): void
{
    imprint_publish_page($profileId);
    imprint_try_sitemap();
}

/** Remove the published page of a profile (file + empty directory). */
function imprint_unpublish(int $profileId): void
{
    $profile = profile_get($profileId);
    if ($profile && imprint_slug_valid((string) $profile['slug'])) {
        $dir = legal_dir() . '/' . $profile['slug'];
        if (is_file($dir . '/index.html') || is_link($dir . '/index.html')) {
            @unlink($dir . '/index.html');
            clearstatcache(true, $dir . '/index.html');
            if (is_file($dir . '/index.html') || is_link($dir . '/index.html')) {
                // A takedown that did not happen must never be reported as done (callers run inside a transaction and roll back).
                throw new RuntimeException('Cannot remove the published page of "' . $profile['slug'] . '" (check file permissions of legal/).');
            }
        }
        if (is_dir($dir)) {
            @rmdir($dir);       // only succeeds when the directory is empty
        }
    }
    imprint_try_sitemap();
}

/**
 * Publish every live profile, write the sitemap once.
 *
 * @return int number of pages written
 * @throws RuntimeException if one or more pages could not be written (the others are still written)
 */
function imprint_publish_all(): int
{
    $n = 0;
    $failed = [];
    foreach (db_all('SELECT id, slug FROM profiles WHERE status = \'live\' AND live_revision_id IS NOT NULL ORDER BY slug') as $row) {
        try {
            imprint_publish_page((int) $row['id']);
            $n++;
        } catch (Throwable $e) {
            error_log('imprint_publish_all: ' . $row['slug'] . ': ' . $e->getMessage());
            $failed[] = (string) $row['slug'];
        }
    }
    imprint_try_sitemap();
    if ($failed) {
        throw new RuntimeException('Could not publish: ' . implode(', ', $failed));
    }
    return $n;
}

/**
 * Directory that holds the site root files (sitemap.xml, legal-notice-<old>/). It is the repository root, except when
 * the legal directory is redirected with TCD_LEGAL_DIR (tests): then it is that directory's parent, so a test run can
 * never overwrite the real sitemap or the real redirect pages by accident.
 */
function site_root_out(): string
{
    $env = getenv('TCD_LEGAL_DIR');
    return ($env !== false && $env !== '') ? dirname(rtrim($env, '/')) : TCD_ROOT;
}

/** Path of sitemap.xml (env TCD_SITEMAP_FILE overrides, used by tests). */
function imprint_sitemap_path(): string
{
    $env = getenv('TCD_SITEMAP_FILE');
    return ($env !== false && $env !== '') ? $env : site_root_out() . '/sitemap.xml';
}

/**
 * Rewrite sitemap.xml: static pages + one URL per live imprint.
 *
 * @return int number of URLs written
 * @throws RuntimeException
 */
function imprint_write_sitemap(): int
{
    $urls = [];
    foreach (site_static_paths() as $path) {
        $urls[] = site_url($path);
    }
    foreach (db_all('SELECT slug FROM profiles WHERE status = \'live\' AND live_revision_id IS NOT NULL ORDER BY slug') as $row) {
        if (imprint_slug_valid((string) $row['slug'])) {
            $urls[] = imprint_public_url((string) $row['slug']);
        }
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $url) {
        $xml .= '  <url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>' . "\n";
    }
    $xml .= '</urlset>' . "\n";
    site_write_atomic(imprint_sitemap_path(), $xml);
    return count($urls);
}

/** Sitemap update that must never abort the caller. */
function imprint_try_sitemap(): void
{
    try {
        imprint_write_sitemap();
    } catch (Throwable $e) {
        error_log('imprint_write_sitemap: ' . $e->getMessage());
    }
}

/** Directory that receives the legacy redirect pages (repository root; env TCD_ROOT_OUT overrides, used by tests). */
function imprint_root_out(): string
{
    $env = getenv('TCD_ROOT_OUT');
    return rtrim(($env !== false && $env !== '') ? $env : site_root_out(), '/');
}

/** Minimal static page that forwards an old URL to its new place. */
function imprint_redirect_html(string $target): string
{
    $abs = h($target);
    $path = h((string) parse_url($target, PHP_URL_PATH));
    return '<!doctype html>' . "\n"
        . '<html lang="en">' . "\n"
        . '  <head>' . "\n"
        . '    <meta charset="utf-8" />' . "\n"
        . '    <title>Legal Notice has moved</title>' . "\n"
        . '    <meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n"
        . '    <meta name="robots" content="noindex" />' . "\n"
        . '    <link rel="canonical" href="' . $abs . '" />' . "\n"
        . '    <meta http-equiv="refresh" content="0; url=' . $path . '" />' . "\n"
        . '    <style>body{font-family:Poppins,Arial,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem;line-height:1.5;color:#111}a{color:#ff4747}</style>' . "\n"
        . '  </head>' . "\n"
        . '  <body>' . "\n"
        . '    <h1>This legal notice has moved</h1>' . "\n"
        . '    <p>You are being forwarded. If nothing happens, please follow this link: <a href="' . $path . '">' . $abs . '</a></p>' . "\n"
        . '  </body>' . "\n"
        . '</html>' . "\n";
}

/** Static redirect pages for the old URLs legal-notice-<name>/ → /legal/<name>/ (names from app/data/legacy-profiles.php). */
function imprint_write_legacy_redirects(): void
{
    $file = TCD_ROOT . '/app/data/legacy-profiles.php';
    $legacy = is_file($file) ? (static function () use ($file) {
        return require $file;
    })() : [];
    if (!is_array($legacy)) {
        return;
    }
    foreach ($legacy as $p) {
        $slug = is_array($p) ? (string) ($p['slug'] ?? '') : '';
        if (!imprint_slug_valid($slug)) {
            continue;
        }
        site_write_atomic(imprint_root_out() . '/legal-notice-' . $slug . '/index.html', imprint_redirect_html(imprint_public_url($slug)));
    }
}
