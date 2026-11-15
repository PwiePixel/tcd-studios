<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Sitejet frame for public pages (banner + menu, boxed content block, footer). The same template
 * (app/views/site/frame.php) drives the generated imprint pages and the dynamic public pages
 * (/imprint-protection/, /services/, /shops/, /coming-soon/).
 *
 * CSP does NOT apply to these pages (the webcard runtime needs an inline bootstrap script), so they must not
 * use the strict dynamic-page headers from send_security_headers().
 */

/** Paths listed in sitemap.xml besides the live imprint pages. @return string[] */
function site_static_paths(): array
{
    return ['/', '/newcomer/', '/tcd-programmer/', '/privacy/', '/legal-notice/', '/imprint-protection/', '/services/', '/shops/', '/agb/', '/terms/'];
}

/**
 * Complete HTML document in the Sitejet frame.
 *
 * Keys: title (required), content (HTML of the boxed block), description, canonical (path or absolute URL; '' = none),
 * robots ('' = default/indexable), css (extra stylesheet hrefs), body_class, module_id.
 *
 * @param array<string,mixed> $page
 */
function site_frame(array $page): string
{
    $siteUrl = rtrim((string) config('site_url', 'https://tcd-studio.de'), '/');
    $canonical = (string) ($page['canonical'] ?? '');
    if ($canonical !== '' && strpos($canonical, '://') === false) {
        $canonical = $siteUrl . '/' . ltrim($canonical, '/');
    }
    $css = [];
    foreach ((array) ($page['css'] ?? []) as $href) {
        if (is_string($href) && preg_match('~^/[A-Za-z0-9/._-]+\.css$~', $href) === 1) {
            $css[] = $href;
        }
    }
    return render_partial('site/frame', [
        'title'       => (string) ($page['title'] ?? 'TCD-Studio/Agency'),
        'description' => (string) ($page['description'] ?? ''),
        'canonical'   => $canonical,
        'robots'      => (string) ($page['robots'] ?? ''),
        'content'     => (string) ($page['content'] ?? ''),
        'css'         => $css,
        'body_class'  => preg_replace('/[^A-Za-z0-9 _-]/', '', (string) ($page['body_class'] ?? '')),
        'module_id'   => (int) ($page['module_id'] ?? 13865385),
        'site_url'    => $siteUrl,
    ]);
}

/** Headline in the site style: the first letter carries the accent colour. */
function site_headline(string $text, string $tag = 'h2', string $id = ''): string
{
    $tag = in_array($tag, ['h1', 'h2', 'h3'], true) ? $tag : 'h2';
    $first = mb_substr($text, 0, 1, 'UTF-8');
    $rest = mb_substr($text, 1, null, 'UTF-8');
    $idAttr = $id !== '' ? ' id="' . h($id) . '"' : '';
    return '<div class="ed-element ed-headline custom-theme"' . $idAttr . '><' . $tag . ' class="center"><span class="color-primary">'
        . h($first) . '</span>' . h($rest) . '</' . $tag . '></div>';
}

/** Vertical spacer block (pixels). */
function site_spacer(int $px, string $id = ''): string
{
    $idAttr = $id !== '' ? ' id="' . h($id) . '"' : '';
    return '<div class="ed-element ed-spacer"' . $idAttr . '><div style="height: ' . max(0, $px) . 'px" class="space"></div></div>';
}

/** Text block in the site style; $html must already be escaped/trusted markup. */
function site_text(string $html, string $extraClass = '', string $id = ''): string
{
    $idAttr = $id !== '' ? ' id="' . h($id) . '"' : '';
    $cls = trim('ed-element ed-text custom-theme ' . $extraClass);
    return '<div class="' . h($cls) . '"' . $idAttr . '>' . $html . '</div>';
}

/** Send the headers of a public dynamic page (no CSP: the Sitejet runtime needs inline scripts). */
function site_send_headers(bool $noStore = false, int $status = 200): void
{
    if (headers_sent()) {
        return;
    }
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if ($noStore) {
        header('Cache-Control: no-store');
    }
}

/**
 * Echo a public page and stop.
 *
 * @param array<string,mixed> $page see site_frame()
 * @return void Never returns.
 */
function site_render(array $page, bool $noStore = false, int $status = 200): void
{
    $html = site_frame($page);
    site_send_headers($noStore, $status);
    echo $html;
    exit;
}

/** Management block with a safe fallback when the database is unavailable. @return array<string,string> */
function site_management(): array
{
    try {
        $m = management_config();
    } catch (Throwable $e) {
        error_log('site_management: ' . $e->getMessage());
        $m = (array) config('management', []);
    }
    $defaults = [
        'name' => 'TCD-Studio/Agency', 'street' => '', 'city' => '', 'phone' => '', 'email' => '', 'website' => '',
        'legal_form' => '', 'chamber' => '', 'authority' => '', 'regulations_url' => '', 'notice' => '',
    ];
    $out = [];
    foreach ($defaults as $k => $d) {
        $out[$k] = isset($m[$k]) ? (string) $m[$k] : $d;
    }
    return $out;
}
