<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Content model of an imprint page. One model feeds two renderers:
 *  - the Sitejet page body (app/views/site/imprint-body.php), identical in look to the original static pages,
 *  - the plain semantic fragment for previews in the portal/admin (app/views/site/imprint-fragment.php).
 *
 * A block is a list of rows; a row is one of
 *   ['h', 'Heading text']                      bold heading line
 *   ['t', <inline>]                            text line
 *   ['ul', [['label' => .., 'url' => ..], …]]  list of customer links (always the last row of a block)
 * <inline> is a plain string or a list of strings and ['a', href, label] link tuples (everything is escaped on output).
 * Nothing in here is trusted: every value is escaped by site_imprint_inline() / the views.
 */

/** Role text after "Responsible for content /". */
function site_imprint_role(string $kind): string
{
    $roles = ['streamer' => 'Streamer', 'creator' => 'Content creator', 'shop' => 'Shop operator', 'other' => 'Content provider'];
    return $roles[$kind] ?? 'Content creator';
}

/** Short phrase for the meta description ("the Streamer PixelCST"). */
function site_imprint_description(string $kind, string $name): string
{
    $what = ['streamer' => 'the Streamer ', 'creator' => 'the Content creator ', 'shop' => 'the Online shop ', 'other' => ''];
    return 'This is the Imprint for ' . ($what[$kind] ?? '') . $name;
}

/** Display name of an imprint (falls back to the slug). */
function site_imprint_name(array $profile, array $public): string
{
    $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($public['display_name'] ?? '')));
    return $name !== '' ? $name : (string) ($profile['slug'] ?? 'Imprint');
}

/** Valid, publishable links of the customer (https only; anything else is silently dropped). @return array<int,array{label:string,url:string}> */
function site_imprint_links(array $public): array
{
    $out = [];
    foreach ((array) ($public['links'] ?? []) as $link) {
        if (!is_array($link)) {
            continue;
        }
        $label = trim((string) ($link['label'] ?? ''));
        $url = trim((string) ($link['url'] ?? ''));
        if ($label === '' || !v_https_url($url)) {
            continue;
        }
        $out[] = ['label' => $label, 'url' => $url];
    }
    return $out;
}

/** Link tuple that degrades to plain text if the URL is not a valid https URL. */
function site_imprint_link(string $url, string $label = ''): array
{
    return v_https_url($url) ? ['a', $url, $label !== '' ? $label : $url] : [$url];
}

/**
 * Build the blocks of an imprint.
 *
 * @param array<string,mixed> $profile
 * @param array<string,mixed> $public
 * @return array<int,array<int,array>>
 */
function site_imprint_blocks(array $profile, array $public): array
{
    $m = site_management();
    $name = site_imprint_name($profile, $public);
    $kind = (string) ($public['kind'] ?? 'creator');

    $blocks = [];
    $blocks[] = [
        ['h', 'Legal Disclosure (according to §5 ECG – Austria)'],
        ['h', 'Responsible for content / ' . site_imprint_role($kind) . ':'],
        ['t', $name],
        ['t', 'Managed by TCD-Agency'],
    ];

    $mgmt = [['h', 'Management / Contact Information:']];
    foreach (['name', 'street', 'city'] as $k) {
        if ($m[$k] !== '') {
            $mgmt[] = ['t', $m[$k]];
        }
    }
    if ($m['phone'] !== '') {
        $mgmt[] = ['t', 'Phone: ' . $m['phone']];
    }
    if ($m['email'] !== '') {
        $mgmt[] = ['t', ['Email: ', v_email($m['email']) ? ['a', 'mailto:' . $m['email'], $m['email']] : $m['email']]];
    }
    if ($m['website'] !== '') {
        $mgmt[] = ['t', ['Website: ', site_imprint_link($m['website'])]];
    }
    $blocks[] = $mgmt;
    if ($m['notice'] !== '') {
        $blocks[] = [['h', 'Important Notice:'], ['t', $m['notice']]];
    }

    $contact = mb_strtolower(trim((string) ($public['contact_email'] ?? '')), 'UTF-8');
    if (!empty($public['show_contact_email']) && v_email($contact)) {
        $blocks[] = [
            ['h', 'Direct contact:'],
            ['t', 'For direct inquiries to ' . $name . ':'],
            ['t', ['Email: ', ['a', 'mailto:' . $contact, $contact]]],
        ];
    }

    $links = site_imprint_links($public);
    if ($links) {
        $blocks[] = [
            ['h', 'Links:'],
            ['t', 'This legal notice may only be used for ' . $name . ' projects. This list contains all authorized links for this legal notice.'],
            ['ul', $links],
        ];
    }

    $purpose = [['h', 'Business Purpose:']];
    $lines = preg_split('/\R/u', trim((string) ($public['purpose'] ?? ''))) ?: [];
    foreach ($lines as $line) {
        if (trim($line) !== '') {
            $purpose[] = ['t', trim($line)];
        }
    }
    $blocks[] = $purpose;

    $blocks[] = [['h', 'Legal Form of Management:']];
    $form = [];
    foreach (['legal_form', 'chamber'] as $k) {
        if ($m[$k] !== '') {
            $form[] = ['t', $m[$k]];
        }
    }
    if ($form) {
        $blocks[] = $form;
    }

    $auth = [['h', 'Regulatory Authority:']];
    if ($m['authority'] !== '') {
        $auth[] = ['t', $m['authority']];
    }
    if ($m['regulations_url'] !== '') {
        $auth[] = ['t', ['Applicable legal regulations: ', site_imprint_link($m['regulations_url'])]];
    }
    $blocks[] = $auth;

    $blocks[] = [
        ['h', 'Disclaimer'],
        ['t', 'All content on this website and associated platforms has been created with care. Nevertheless, we assume no liability for the accuracy, completeness, or timeliness of the information provided. Use of the content is at your own risk.'],
    ];
    $blocks[] = [
        ['t', 'Despite careful control of content, we do not accept liability for external links. The content of linked pages is the sole responsibility of their operators.'],
    ];
    $blocks[] = [
        ['h', 'Copyright Notice'],
        ['t', 'All images, graphics, and media used on this website or associated platforms are subject to copyright and are the intellectual property of their respective creators, ' . $name . ', or TCD-Studio, unless otherwise stated. Unauthorized use, reproduction, or distribution is prohibited and may result in legal action.'],
    ];
    $blocks[] = [
        ['h', 'EU Online Dispute Resolution'],
        ['t', 'The European Commission provides a platform for online dispute resolution (ODR):'],
        ['t', [['a', 'https://ec.europa.eu/consumers/odr', 'https://ec.europa.eu/consumers/odr']]],
        ['t', 'We are not obligated to participate in such dispute resolution procedures.'],
    ];
    return $blocks;
}

/**
 * Escaped inline HTML of a text row.
 *
 * @param string|array $inline
 */
function site_imprint_inline($inline): string
{
    if (!is_array($inline)) {
        return h($inline);
    }
    if (($inline[0] ?? '') === 'a' && count($inline) >= 3 && is_string($inline[1])) {
        $href = $inline[1];
        $safe = preg_match('~^(https://|mailto:)~i', $href) === 1;
        return $safe ? '<a href="' . h($href) . '">' . h((string) $inline[2]) . '</a>' : h((string) $inline[2]);
    }
    $out = '';
    foreach ($inline as $part) {
        $out .= site_imprint_inline($part);
    }
    return $out;
}

/** <ul> with the customer's links (external, never followed, no referrer). */
function site_imprint_links_html(array $links): string
{
    $html = '<ul>';
    foreach ($links as $l) {
        $html .= '<li><a href="' . h($l['url']) . '" target="_blank" rel="noopener noreferrer nofollow ugc">' . h($l['label']) . '</a></li>';
    }
    return $html . '</ul>';
}

/** Sitejet body of an imprint page: headline + text block, same markup as the original static pages. @param array<int,array<int,array>> $blocks */
function site_imprint_page_body(string $name, array $blocks): string
{
    $html = '        <div class="ed-element ed-headline custom-theme" id="ed-713620365">' . "\n"
        . '          <h2 class="center"><span class="color-primary">L</span>egal Notice ' . h($name) . '</h2>' . "\n"
        . '        </div>' . "\n"
        . '        <div class="ed-element ed-spacer" id="ed-713620368"><div style="height: 16px" class="space"></div></div>' . "\n"
        . '        <div class="ed-element ed-text custom-theme" id="ed-713620371">' . "\n";
    foreach ($blocks as $block) {
        $lines = [];
        $list = '';
        foreach ($block as $row) {
            if ($row[0] === 'h') {
                $lines[] = '<strong>' . h($row[1]) . '</strong>';
            } elseif ($row[0] === 't') {
                $lines[] = site_imprint_inline($row[1]);
            } elseif ($row[0] === 'ul') {
                $list = site_imprint_links_html($row[1]);
            }
        }
        if ($lines) {
            $html .= '          <p>' . implode('<br />', $lines) . '</p>' . "\n";
        }
        if ($list !== '') {
            $html .= '          ' . $list . "\n";
        }
    }
    return $html . '        </div>' . "\n"
        . '        <div class="ed-element ed-spacer" id="ed-713620374"><div style="height: 32px" class="space"></div></div>';
}

/**
 * Plain semantic HTML (h2/h3/p/ul) for previews inside the portal/admin; needs no site CSS.
 *
 * @param array<int,array<int,array>> $blocks
 */
function site_imprint_fragment_html(string $name, array $blocks): string
{
    $html = '<div class="pf-preview imprint-preview">' . "\n" . '<h2>Legal Notice ' . h($name) . '</h2>' . "\n";
    foreach ($blocks as $block) {
        $text = [];
        $flush = static function () use (&$text, &$html): void {
            if ($text) {
                $html .= '<p>' . implode('<br>', $text) . '</p>' . "\n";
                $text = [];
            }
        };
        foreach ($block as $row) {
            if ($row[0] === 'h') {
                $flush();
                $html .= '<h3>' . h($row[1]) . '</h3>' . "\n";
            } elseif ($row[0] === 't') {
                $text[] = site_imprint_inline($row[1]);
            } elseif ($row[0] === 'ul') {
                $flush();
                $html .= site_imprint_links_html($row[1]) . "\n";
            }
        }
        $flush();
    }
    return $html . '</div>';
}
