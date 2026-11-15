<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * STUB of the imprint publishing pipeline (written by the foundation so that portal/admin can be built
 * in parallel). The "site" work package replaces the bodies with the real implementation. SIGNATURES ARE FINAL.
 */

/** Directory that receives the generated imprint pages (env TCD_LEGAL_DIR overrides, used by tests). */
function legal_dir(): string
{
    $env = getenv('TCD_LEGAL_DIR');
    return rtrim(($env !== false && $env !== '') ? $env : TCD_ROOT . '/legal', '/');
}

/** Inner HTML (no site frame) of an imprint, for previews in portal/admin. @param array<string,mixed> $profile @param array<string,mixed> $public */
function imprint_render_fragment(array $profile, array $public): string
{
    return '<div class="pf-preview"><h2>' . h($public['display_name'] ?? '') . '</h2><p>' . h($public['purpose'] ?? '') . '</p></div>';
}

/** Complete HTML page of an imprint. @param array<string,mixed> $profile @param array<string,mixed> $public */
function imprint_render_html(array $profile, array $public): string
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . h($public['display_name'] ?? '') . '</title></head><body>'
        . imprint_render_fragment($profile, $public) . '</body></html>';
}

/** Write legal/<slug>/index.html for the LIVE revision of a profile. @throws RuntimeException */
function imprint_publish(int $profileId): void
{
    $profile = profile_get($profileId);
    $live = $profile ? revision_live($profileId) : null;
    if (!$profile || !$live || preg_match('/^[a-z0-9-]{3,40}$/D', (string) $profile['slug']) !== 1) {
        throw new RuntimeException('Nothing to publish.');
    }
    $dir = legal_dir() . '/' . $profile['slug'];
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('Cannot create ' . $dir);
    }
    $tmp = $dir . '/.index.tmp';
    if (@file_put_contents($tmp, imprint_render_html($profile, $live['public']), LOCK_EX) === false || !@rename($tmp, $dir . '/index.html')) {
        throw new RuntimeException('Cannot write the imprint page.');
    }
}

/** Remove the published page of a profile. */
function imprint_unpublish(int $profileId): void
{
    $profile = profile_get($profileId);
    if ($profile && preg_match('/^[a-z0-9-]{3,40}$/D', (string) $profile['slug']) === 1) {
        @unlink(legal_dir() . '/' . $profile['slug'] . '/index.html');
        @rmdir(legal_dir() . '/' . $profile['slug']);
    }
}

/** Publish every live profile. @return int number of pages written */
function imprint_publish_all(): int
{
    $n = 0;
    foreach (db_all('SELECT id FROM profiles WHERE status = \'live\' AND live_revision_id IS NOT NULL') as $row) {
        imprint_publish((int) $row['id']);
        $n++;
    }
    return $n;
}

/** Static redirect pages for the old URLs legal-notice-<name>/ . */
function imprint_write_legacy_redirects(): void
{
}
