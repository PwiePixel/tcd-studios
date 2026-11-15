<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Imprint data model (pure functions, no I/O): kinds, defaults, validation/normalisation, diff, slugs.
 * Public data is published in the imprint; private data (identity/address) is never published.
 */

/** @return array<string,string> kind => label */
function imprint_kinds(): array
{
    return ['creator' => 'Content creator', 'streamer' => 'Streamer', 'shop' => 'Online shop', 'other' => 'Other'];
}

/** Default business-purpose text for a kind (the customer may edit it). */
function imprint_default_purpose(string $kind, string $displayName): string
{
    $name = $displayName !== '' ? $displayName : 'The account holder';
    switch ($kind) {
        case 'streamer':
            $what = 'is a content creator and streamer.';
            break;
        case 'shop':
            $what = 'operates an online shop.';
            break;
        case 'other':
            $what = 'publishes content online.';
            break;
        default:
            $what = 'is a content creator.';
    }
    return $name . ' ' . $what . ' TCD-Studio acts as management, providing support in digital media, marketing, and creative services.';
}

/** @return array<string,mixed> empty public data for a new profile */
function imprint_public_defaults(string $kind = 'creator', string $displayName = ''): array
{
    return [
        'display_name' => $displayName,
        'kind' => isset(imprint_kinds()[$kind]) ? $kind : 'creator',
        'purpose' => imprint_default_purpose($kind, $displayName),
        'links' => [],
        'show_contact_email' => false,
        'contact_email' => '',
    ];
}

/** @return array<string,string> empty private data */
function imprint_private_defaults(): array
{
    return ['legal_name' => '', 'address_line1' => '', 'postal_code' => '', 'city' => '', 'country' => '', 'phone' => '', 'vat_id' => '', 'notes' => ''];
}

/**
 * Validate + normalise public data.
 *
 * @param array<string,mixed> $raw  untrusted input (e.g. merged POST + existing links)
 * @return array{0:array<string,mixed>,1:array<string,string>} [clean data, errors by field]
 */
function imprint_clean_public(array $raw): array
{
    $errors = [];
    $name = trim((string) ($raw['display_name'] ?? ''));
    if (!v_len($name, 2, 60) || !v_oneline($name)) {
        $errors['display_name'] = __('Please enter the public name (2–60 characters, one line).');
    }
    $kind = (string) ($raw['kind'] ?? 'creator');
    if (!isset(imprint_kinds()[$kind])) {
        $errors['kind'] = __('Please choose a valid type.');
        $kind = 'creator';
    }
    $purpose = trim((string) ($raw['purpose'] ?? ''));
    $purpose = (string) preg_replace('/\s+/u', ' ', $purpose);
    if (!v_len($purpose, 10, 300)) {
        $errors['purpose'] = __('The business purpose needs 10–300 characters.');
    }

    $links = [];
    $max = (int) config('max_links', 12);
    $rawLinks = isset($raw['links']) && is_array($raw['links']) ? array_values($raw['links']) : [];
    if (count($rawLinks) > $max) {
        $errors['links'] = sprintf(__('At most %d links are allowed.'), $max);
        $rawLinks = array_slice($rawLinks, 0, $max);
    }
    $seen = [];
    foreach ($rawLinks as $link) {
        $label = trim((string) (is_array($link) ? ($link['label'] ?? '') : ''));
        $url = trim((string) (is_array($link) ? ($link['url'] ?? '') : ''));
        if (!v_len($label, 1, 40) || !v_oneline($label)) {
            $errors['links'] = __('Every link needs a label of 1–40 characters.');
            continue;
        }
        if (!v_https_url($url)) {
            $errors['links'] = __('Links must be complete https:// addresses.');
            continue;
        }
        $key = mb_strtolower($url, 'UTF-8');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $links[] = ['label' => $label, 'url' => $url];
    }

    $show = !empty($raw['show_contact_email']);
    $email = mb_strtolower(trim((string) ($raw['contact_email'] ?? '')), 'UTF-8');
    if ($show && !v_email($email)) {
        $errors['contact_email'] = __('Please enter a valid contact e-mail address or switch the option off.');
    }
    if (!$show) {
        $email = '';
    }

    return [[
        'display_name' => $name, 'kind' => $kind, 'purpose' => $purpose, 'links' => $links,
        'show_contact_email' => $show, 'contact_email' => $email,
    ], $errors];
}

/**
 * Validate + normalise private data (identity/address; required for submission).
 *
 * @param array<string,mixed> $raw
 * @param bool $requireComplete require all mandatory fields (on submit)
 * @return array{0:array<string,string>,1:array<string,string>}
 */
function imprint_clean_private(array $raw, bool $requireComplete): array
{
    $fields = array_keys(imprint_private_defaults());
    $limits = ['legal_name' => 120, 'address_line1' => 120, 'postal_code' => 20, 'city' => 80, 'country' => 80, 'phone' => 40, 'vat_id' => 30, 'notes' => 1000];
    $clean = [];
    $errors = [];
    foreach ($fields as $f) {
        $v = trim((string) ($raw[$f] ?? ''));
        $v = $f === 'notes' ? (string) preg_replace('/[^\P{C}\n]/u', '', $v) : (string) preg_replace('/[^\P{C}]/u', '', $v);
        if (str_len($v) > $limits[$f]) {
            $errors[$f] = sprintf(__('Too long (maximum %d characters).'), $limits[$f]);
            $v = mb_substr($v, 0, $limits[$f], 'UTF-8');
        }
        $clean[$f] = $v;
    }
    if ($requireComplete) {
        foreach (['legal_name', 'address_line1', 'postal_code', 'city', 'country'] as $f) {
            if ($clean[$f] === '') {
                $errors[$f] = __('This field is required to submit your imprint for review.');
            }
        }
    }
    return [$clean, $errors];
}

/** Slugs that must never be used (routes, system words). @return string[] */
function imprint_reserved_slugs(): array
{
    return ['admin', 'portal', 'api', 'app', 'data', 'legal', 'privacy', 'login', 'register', 'new', 'index', 'shops', 'services',
        'coming-soon', 'imprint-protection', 'webcard', 'css', 'js', 'images', 'fonts', 'backend', 'tcd-studio', 'support', 'contact', 'info'];
}

/** Suggest a slug from a display name. */
function imprint_slug_suggest(string $displayName): string
{
    $slug = substr(slugify($displayName), 0, 40);
    return trim($slug, '-');
}

/**
 * Check a slug. @return string|null error message or null if it is fine and free
 */
function imprint_slug_error(string $slug, ?int $exceptProfileId = null): ?string
{
    if (preg_match('/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/D', $slug) !== 1 || strpos($slug, '--') !== false) {
        return __('The page name may contain only lower-case letters, digits and single hyphens (3–40 characters).');
    }
    if (in_array($slug, imprint_reserved_slugs(), true)) {
        return __('This page name is reserved. Please choose another one.');
    }
    $row = db_one('SELECT id FROM profiles WHERE slug = ?', [$slug]);
    if ($row !== null && ($exceptProfileId === null || (int) $row['id'] !== $exceptProfileId)) {
        return __('This page name is already taken.');
    }
    return null;
}

/**
 * Field-by-field differences between two public data sets (for the admin review screen).
 *
 * @param array<string,mixed> $old  data of the live revision ([] for a first submission)
 * @param array<string,mixed> $new
 * @return array<int,array{field:string,label:string,old:string,new:string}> only changed fields
 */
function imprint_diff(array $old, array $new): array
{
    $fmt = static function (array $d, string $f): string {
        if ($f === 'links') {
            $out = [];
            foreach ((array) ($d['links'] ?? []) as $l) {
                $out[] = ($l['label'] ?? '') . ' → ' . ($l['url'] ?? '');
            }
            return implode("\n", $out);
        }
        if ($f === 'show_contact_email') {
            return !empty($d['show_contact_email']) ? 'yes' : 'no';
        }
        if ($f === 'kind') {
            return (string) (imprint_kinds()[$d['kind'] ?? ''] ?? ($d['kind'] ?? ''));
        }
        return (string) ($d[$f] ?? '');
    };
    $labels = [
        'display_name' => 'Public name', 'kind' => 'Type', 'purpose' => 'Business purpose', 'links' => 'Links',
        'show_contact_email' => 'Show direct contact e-mail', 'contact_email' => 'Direct contact e-mail',
    ];
    $diff = [];
    foreach ($labels as $field => $label) {
        $o = $fmt($old, $field);
        $n = $fmt($new, $field);
        if ($o !== $n) {
            $diff[] = ['field' => $field, 'label' => __($label), 'old' => $o, 'new' => $n];
        }
    }
    return $diff;
}

/** Public URL of an imprint page. */
function imprint_public_url(string $slug): string
{
    return site_url('/legal/' . $slug . '/');
}
