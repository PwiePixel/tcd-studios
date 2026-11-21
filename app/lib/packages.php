<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Imprint Protection packages ("plans"): table packages (migration 7), profiles.package_id.
 * Edited in admin/packages.php, shown as pricing cards on /imprint-protection/ and chosen by the customer
 * for every imprint profile (portal/profile-new.php, portal/profile-edit.php).
 *
 * Profile limit: a customer may own at most the max_profiles of the highest package among his profiles,
 * and never more than config('max_profiles_per_user'). A profile without a package counts as package_default().
 */

const PACKAGES_NOTE_DEFAULT = 'All prices in EUR per month, for consumers incl. 20 % VAT (businesses: plus VAT), plus the one-time setup fee where stated. Yearly plans are invoiced once a year in advance, monthly plans every month; invoices are payable within 14 days (see Terms § 6).';

/** Limits of the admin form. */
const PACKAGE_MAX_FEATURES = 20;
const PACKAGE_MAX_EXTRAS = 10;
const PACKAGE_MAX_PRICE_CENTS = 9999999;     // EUR 99,999.99

/**
 * Package row with decoded lists and integer fields.
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function package_decode(array $row): array
{
    $list = static function ($json): array {
        $out = [];
        foreach (json_arr(is_string($json) ? $json : '[]') as $v) {
            if (is_string($v) && trim($v) !== '') {
                $out[] = $v;
            }
        }
        return $out;
    };
    $row['id'] = (int) $row['id'];
    $row['features'] = $list($row['features_json'] ?? '[]');
    $row['extras'] = $list($row['extras_json'] ?? '[]');
    $row['price_month_cents'] = $row['price_month_cents'] !== null ? (int) $row['price_month_cents'] : null;
    $row['price_year_cents'] = $row['price_year_cents'] !== null ? (int) $row['price_year_cents'] : null;
    $row['max_profiles'] = (int) $row['max_profiles'];
    $row['sort_order'] = (int) $row['sort_order'];
    $row['active'] = (int) $row['active'];
    $row['badge'] = (string) $row['badge'];
    $row['tagline'] = (string) $row['tagline'];
    return $row;
}

/** All packages in display order. @return array<int,array<string,mixed>> */
function packages_all(bool $activeOnly = false): array
{
    $rows = db_all('SELECT * FROM packages' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, id');
    return array_map('package_decode', $rows);
}

/** @return array<string,mixed>|null */
function package_get(int $id): ?array
{
    $row = $id > 0 ? db_one('SELECT * FROM packages WHERE id = ?', [$id]) : null;
    return $row ? package_decode($row) : null;
}

/** @return array<string,mixed>|null */
function package_get_by_slug(string $slug): ?array
{
    if (preg_match('/^[a-z0-9-]{1,40}$/D', $slug) !== 1) {
        return null;
    }
    $row = db_one('SELECT * FROM packages WHERE slug = ?', [$slug]);
    return $row ? package_decode($row) : null;
}

/** An ACTIVE package by slug (what customers may choose), or null. @return array<string,mixed>|null */
function package_active_by_slug(string $slug): ?array
{
    $p = package_get_by_slug($slug);
    return $p !== null && $p['active'] === 1 ? $p : null;
}

/** Default package (first active one; if none is active, the first one at all). @return array<string,mixed>|null */
function package_default(): ?array
{
    $active = packages_all(true);
    if ($active !== []) {
        return $active[0];
    }
    $all = packages_all();
    return $all[0] ?? null;
}

/** Package of a profile; profiles without one (created before packages existed) count as the default package. @return array<string,mixed>|null */
function package_of_profile(array $profile): ?array
{
    $p = isset($profile['package_id']) && $profile['package_id'] !== null ? package_get((int) $profile['package_id']) : null;
    return $p ?? package_default();
}

/** Number of profiles that use the package. */
function package_profile_count(int $id): int
{
    $row = db_one('SELECT COUNT(*) AS n FROM profiles WHERE package_id = ?', [$id]);
    return (int) ($row['n'] ?? 0);
}

/** "3.90" for 390 cents. */
function package_money(int $cents): string
{
    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
}

/**
 * Price input in EUR ("4,90", "4.9", "12") to cents. '' = no price (null).
 *
 * @return array{0:?int,1:bool} [cents, valid]
 */
function package_parse_price(string $in): array
{
    $in = str_replace(' ', '', trim($in));
    if ($in === '') {
        return [null, true];
    }
    if (preg_match('/^(\d{1,5})(?:[.,](\d{1,2}))?$/D', $in, $m) !== 1) {
        return [null, false];
    }
    $cents = (int) $m[1] * 100 + (isset($m[2]) ? (int) str_pad($m[2], 2, '0') : 0);
    return $cents <= PACKAGE_MAX_PRICE_CENTS ? [$cents, true] : [null, false];
}

/** Price for an input field ("4,90" style is accepted, we show "4.90"); '' for none. */
function package_price_input(?int $cents): string
{
    return $cents === null ? '' : package_money($cents);
}

/**
 * Price lines of a card: [main, secondary], e.g. ["EUR 3.90 / month billed yearly", "or EUR 4.90 monthly"].
 *
 * @param array<string,mixed> $p
 * @return array{0:string,1:string}
 */
function package_price_lines(array $p): array
{
    $year = $p['price_year_cents'];
    $month = $p['price_month_cents'];
    if ($year !== null && $month !== null) {
        return [sprintf(__('EUR %s / month billed yearly'), package_money($year)), sprintf(__('or EUR %s monthly'), package_money($month))];
    }
    if ($year !== null) {
        return [sprintf(__('EUR %s / month billed yearly'), package_money($year)), ''];
    }
    if ($month !== null) {
        return [sprintf(__('EUR %s / month'), package_money($month)), ''];
    }
    return [__('Price on request'), ''];
}

/** Short price text for lists ("EUR 3.90 / EUR 4.90"). @param array<string,mixed> $p */
function package_price_short(array $p): string
{
    $parts = [];
    if ($p['price_year_cents'] !== null) {
        $parts[] = sprintf(__('EUR %s yearly'), package_money($p['price_year_cents']));
    }
    if ($p['price_month_cents'] !== null) {
        $parts[] = sprintf(__('EUR %s monthly'), package_money($p['price_month_cents']));
    }
    return $parts !== [] ? implode(' · ', $parts) : __('no price');
}

/** Lowest monthly price of the active packages (yearly billing preferred), or null. */
function packages_from_price(array $packages): ?int
{
    $min = null;
    foreach ($packages as $p) {
        $c = $p['price_year_cents'] ?? $p['price_month_cents'];
        if ($c !== null && ($min === null || $c < $min)) {
            $min = $c;
        }
    }
    return $min;
}

/** Note shown under the pricing cards (site setting packages.note). */
function packages_note(): string
{
    $v = setting_get('packages.note');
    return $v === null ? PACKAGES_NOTE_DEFAULT : (string) $v;
}

/** Non-empty trimmed lines of a textarea. @return string[] */
function package_lines(string $text): array
{
    $out = [];
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $line = trim((string) preg_replace('/\s+/u', ' ', $line));
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

/**
 * Validate the admin form of a package.
 *
 * @param array<string,string> $in raw strings: slug, name, tagline, price_month, price_year, features, extras, badge, max_profiles, sort_order, active
 * @return array{0:array<string,mixed>,1:array<string,string>} [clean values, errors by field]
 */
function package_validate(array $in, ?int $id): array
{
    $e = [];
    $slug = mb_strtolower(trim((string) ($in['slug'] ?? '')), 'UTF-8');
    if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1 || strlen($slug) < 2 || strlen($slug) > 40) {
        $e['slug'] = __('The identifier may contain only lower-case letters, digits and single hyphens (2–40 characters).');
    } else {
        $other = package_get_by_slug($slug);
        if ($other !== null && $other['id'] !== $id) {
            $e['slug'] = __('This identifier already exists.');
        }
    }
    $name = trim((string) ($in['name'] ?? ''));
    if (!v_len($name, 2, 60) || !v_oneline($name)) {
        $e['name'] = __('The name needs 2–60 characters on one line.');
    }
    $tagline = trim((string) ($in['tagline'] ?? ''));
    if (!v_len($tagline, 0, 200) || !v_oneline($tagline)) {
        $e['tagline'] = __('The tagline may have at most 200 characters on one line.');
    }
    [$month, $okMonth] = package_parse_price((string) ($in['price_month'] ?? ''));
    if (!$okMonth) {
        $e['price_month'] = __('Please enter a price in EUR such as 4.90 or 4,90 (or leave the field empty).');
    }
    [$year, $okYear] = package_parse_price((string) ($in['price_year'] ?? ''));
    if (!$okYear) {
        $e['price_year'] = __('Please enter a price in EUR such as 3.90 or 3,90 (or leave the field empty).');
    }
    $features = package_lines((string) ($in['features'] ?? ''));
    if (count($features) > PACKAGE_MAX_FEATURES) {
        $e['features'] = sprintf(__('At most %d features are allowed.'), PACKAGE_MAX_FEATURES);
    }
    foreach ($features as $f) {
        if (str_len($f) > 200) {
            $e['features'] = __('Every feature may have at most 200 characters.');
        }
    }
    $extras = package_lines((string) ($in['extras'] ?? ''));
    if (count($extras) > PACKAGE_MAX_EXTRAS) {
        $e['extras'] = sprintf(__('At most %d usage-based fees are allowed.'), PACKAGE_MAX_EXTRAS);
    }
    foreach ($extras as $f) {
        if (str_len($f) > 200) {
            $e['extras'] = __('Every usage-based fee may have at most 200 characters.');
        }
    }
    $badge = trim((string) ($in['badge'] ?? ''));
    if (!v_len($badge, 0, 40) || !v_oneline($badge)) {
        $e['badge'] = __('The badge may have at most 40 characters on one line.');
    }
    $max = trim((string) ($in['max_profiles'] ?? ''));
    if (preg_match('/^\d{1,3}$/D', $max) !== 1 || (int) $max < 1 || (int) $max > 100) {
        $e['max_profiles'] = __('The number of imprint profiles must be between 1 and 100.');
    }
    $sort = trim((string) ($in['sort_order'] ?? ''));
    if (preg_match('/^\d{1,4}$/D', $sort) !== 1) {
        $e['sort_order'] = __('The sort order must be a number between 0 and 9999.');
    }
    return [[
        'slug' => $slug, 'name' => $name, 'tagline' => $tagline, 'price_month_cents' => $month, 'price_year_cents' => $year,
        'features' => $features, 'extras' => $extras, 'badge' => $badge, 'max_profiles' => (int) $max, 'sort_order' => (int) $sort,
        'active' => !empty($in['active']) ? 1 : 0,
    ], $e];
}

/** Insert ($id null) or update a validated package; returns its id. @param array<string,mixed> $c */
function package_save(array $c, ?int $id): int
{
    $t = now();
    $vals = [$c['slug'], $c['name'], $c['tagline'], $c['price_month_cents'], $c['price_year_cents'], json_str(array_values($c['features'])),
        json_str(array_values($c['extras'])), $c['badge'], (int) $c['max_profiles'], (int) $c['sort_order'], (int) $c['active']];
    if ($id === null) {
        return db_insert(
            'INSERT INTO packages (slug,name,tagline,price_month_cents,price_year_cents,features_json,extras_json,badge,max_profiles,sort_order,active,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            array_merge($vals, [$t, $t])
        );
    }
    db_exec(
        'UPDATE packages SET slug=?, name=?, tagline=?, price_month_cents=?, price_year_cents=?, features_json=?, extras_json=?, badge=?,
           max_profiles=?, sort_order=?, active=?, updated_at=? WHERE id = ?',
        array_merge($vals, [$t, $id])
    );
    return $id;
}

function package_set_active(int $id, bool $active): void
{
    db_exec('UPDATE packages SET active = ?, updated_at = ? WHERE id = ?', [$active ? 1 : 0, now(), $id]);
}

/** Delete a package that no profile uses. @return bool false if it is in use (nothing deleted) */
function package_delete(int $id): bool
{
    return (bool) db_transaction(static function () use ($id) {
        if (package_profile_count($id) > 0) {
            return false;
        }
        return db_exec('DELETE FROM packages WHERE id = ?', [$id]) === 1;
    });
}

/** Set the package of a profile. */
function profile_set_package(int $profileId, ?int $packageId): void
{
    db_exec('UPDATE profiles SET package_id = ?, updated_at = ? WHERE id = ?', [$packageId, now(), $profileId]);
}

/* ------------------------------------------------------------------ profile limit */

/** The global upper limit of imprint profiles per customer (config max_profiles_per_user). */
function package_global_limit(): int
{
    return max(1, (int) config('max_profiles_per_user', 5));
}

/**
 * Profile limit for a customer whose profiles have these packages: max_profiles of the highest package,
 * never more than the global limit. null entries count as the default package.
 *
 * @param array<int|string,?int> $packageIds
 */
function package_limit_for(array $packageIds): int
{
    $global = package_global_limit();
    $default = null;
    $best = 0;
    foreach ($packageIds as $pid) {
        $p = $pid !== null ? package_get((int) $pid) : null;
        if ($p === null) {
            $default = $default ?? package_default();
            $p = $default;
        }
        $best = max($best, $p !== null ? $p['max_profiles'] : $global);
    }
    return $best > 0 ? min($global, $best) : $global;
}

/** profile id => package id (null = none) of every profile the user owns. @return array<int,?int> */
function package_ids_of_owner(int $userId): array
{
    $out = [];
    foreach (db_all('SELECT id, package_id FROM profiles WHERE owner_id = ? ORDER BY id', [$userId]) as $r) {
        $out[(int) $r['id']] = $r['package_id'] !== null ? (int) $r['package_id'] : null;
    }
    return $out;
}

/**
 * Highest number of profiles the user could reach by choosing the best active package for a new profile
 * (used to decide whether "Create imprint profile" is offered at all).
 */
function package_reachable_limit(int $userId): int
{
    $ids = array_values(package_ids_of_owner($userId));
    foreach (packages_all(true) as $p) {
        $ids[] = $p['id'];
    }
    return package_limit_for($ids);
}

/**
 * Check the profile limit for a new profile ($profileId null) with $packageId, or for changing the package
 * of one of the user's profiles. Returns an error message, or null when the change is allowed.
 *
 * $onlyRefuseDowngrade (package changes of existing profiles): a change that does not LOWER the user's current
 * limit is always allowed, even if he already owns more profiles than his packages include (profiles from before
 * packages existed count as the default package, or the admin assigned packages). Otherwise such a customer could
 * not save any draft, not even with the package unchanged or upgraded.
 */
function package_limit_error(int $userId, ?int $profileId, ?int $packageId, bool $onlyRefuseDowngrade = false): ?string
{
    $ids = package_ids_of_owner($userId);
    $before = $profileId !== null && $onlyRefuseDowngrade ? package_limit_for($ids) : null;
    if ($profileId === null) {
        $ids['new'] = $packageId;
    } else {
        $ids[$profileId] = $packageId;
    }
    $limit = package_limit_for($ids);
    if (count($ids) <= $limit || ($before !== null && $limit >= $before)) {
        return null;
    }
    if ($limit >= package_global_limit()) {
        return sprintf(__('You can have at most %d imprint profiles.'), $limit);
    }
    return $profileId === null
        ? sprintf(__('With this package you can have at most %d imprint profiles in total. Please choose a package that includes more imprint profiles.'), $limit)
        : sprintf(__('You have %1$d imprint profiles, but after this change your packages would allow only %2$d. Please keep a package that includes more imprint profiles.'), count($ids), $limit);
}

/**
 * Packages a customer can choose for a profile: the active ones, plus the profile's current package
 * if it is no longer offered (so it stays selectable and visible).
 *
 * @return array<int,array<string,mixed>>
 */
function package_choices(?array $current): array
{
    $list = packages_all(true);
    if ($current !== null && $current['active'] !== 1) {
        $list[] = $current;
    }
    return $list;
}
