<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Terms and Conditions (AGB) of the Imprint Protection service.
 * German page /agb/ (binding version) and English translation /terms/ (information only).
 * The views are app/views/site/agb.php and app/views/site/terms.php. When their content changes in a way
 * customers must accept again, raise config('terms_version') in app/config.php.
 */

/** @return array<string,string> provider data and version used by both language versions */
function site_terms_vars(): array
{
    $m = site_management();
    $version = (string) config('terms_version', '');
    $date = $version;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $version, $x) === 1) {
        $date = $x[3] . '.' . $x[2] . '.' . $x[1];
    }
    $host = (string) parse_url((string) config('site_url', 'https://tcd-studio.de'), PHP_URL_HOST);
    return [
        'name'         => $m['name'] !== '' ? $m['name'] : 'TCD-Studio/Agency',
        'street'       => $m['street'],
        'city'         => $m['city'],
        'phone'        => $m['phone'],
        'host'         => $host !== '' ? $host : 'tcd-studio.de',
        'version'      => $version,
        'date'         => $date,
        'mail_imprint' => (string) config('mail.imprint'),
        'mail_contact' => (string) config('mail.contact'),
        'mail_privacy' => (string) config('mail.privacy'),
    ];
}

/** /agb/ – German, binding version. */
function site_page_agb(): void
{
    site_render([
        'title'       => 'AGB – Impressumsschutz für Creator - TCD-Studio/Agency',
        'description' => 'Allgemeine Geschäftsbedingungen für den Impressumsschutz für Creator von TCD-Studio/Agency.',
        'canonical'   => '/agb/',
        'css'         => ['/css/site.css', '/css/legal-text.css'],
        'content'     => render_partial('site/agb', ['p' => site_terms_vars()]),
    ]);
}

/** /terms/ – English translation (information only; the German version governs). */
function site_page_terms(): void
{
    site_render([
        'title'       => 'Terms and Conditions – Imprint Protection for Creators - TCD-Studio/Agency',
        'description' => 'English translation of the Terms and Conditions (AGB) for Imprint Protection for creators. The German version governs.',
        'canonical'   => '/terms/',
        'css'         => ['/css/site.css', '/css/legal-text.css'],
        'content'     => render_partial('site/terms', ['p' => site_terms_vars()]),
    ]);
}
