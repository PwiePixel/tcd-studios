<?php
declare(strict_types=1);

/** Imprint Protection packages ("plans"): shown as pricing cards on /imprint-protection/ and chosen per imprint profile. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

const ADMIN_PACKAGES_URL = '/admin/packages.php';

/** Raw form input of a package form. @return array<string,string> */
function admin_package_input(): array
{
    return [
        'slug' => post_str('slug', 60), 'name' => post_str('name', 200), 'tagline' => post_str('tagline', 300),
        'price_month' => post_str('price_month', 20), 'price_year' => post_str('price_year', 20),
        'features' => post_str('features', 8000), 'extras' => post_str('extras', 4000), 'badge' => post_str('badge', 100),
        'max_profiles' => post_str('max_profiles', 10), 'sort_order' => post_str('sort_order', 10), 'active' => post_bool('active') ? '1' : '',
    ];
}

/** Form values of a stored package. @param array<string,mixed> $p @return array<string,string> */
function admin_package_values(array $p): array
{
    return [
        'slug' => (string) $p['slug'], 'name' => (string) $p['name'], 'tagline' => (string) $p['tagline'],
        'price_month' => package_price_input($p['price_month_cents']), 'price_year' => package_price_input($p['price_year_cents']),
        'features' => implode("\n", $p['features']), 'extras' => implode("\n", $p['extras']), 'badge' => (string) $p['badge'],
        'max_profiles' => (string) $p['max_profiles'], 'sort_order' => (string) $p['sort_order'], 'active' => $p['active'] === 1 ? '1' : '',
    ];
}

$errors = [];     // form key (package id or "_new" / "_note") => field errors
$values = [];     // form key => submitted values
$note = packages_note();

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 20);
    $id = post_int('id');

    if ($action === 'create' || $action === 'save') {
        $isNew = $action === 'create';
        $existing = $isNew ? null : package_get($id);
        if (!$isNew && $existing === null) {
            abort(404);
        }
        $key = $isNew ? '_new' : (string) $id;
        $input = admin_package_input();
        [$clean, $err] = package_validate($input, $isNew ? null : $id);
        if ($err) {
            $errors[$key] = $err;
            $values[$key] = $input;
            flash_add('error', __('Please check the highlighted fields. Nothing was saved.'));
        } else {
            $savedId = package_save($clean, $isNew ? null : $id);
            audit($isNew ? 'package.create' : 'package.update', 'package', $savedId, [
                'slug' => $clean['slug'], 'name' => $clean['name'], 'active' => $clean['active'], 'max_profiles' => $clean['max_profiles'],
                'price_month_cents' => $clean['price_month_cents'], 'price_year_cents' => $clean['price_year_cents'],
            ]);
            redirect(ADMIN_PACKAGES_URL . '#pkg-' . $savedId, ['ok' => $isNew ? __('The package was added.') : __('The package was saved.')]);
        }
    } elseif ($action === 'activate' || $action === 'deactivate') {
        $p = package_get($id);
        if ($p === null) {
            abort(404);
        }
        package_set_active($id, $action === 'activate');
        audit('package.' . $action, 'package', $id, ['slug' => $p['slug']]);
        redirect(ADMIN_PACKAGES_URL . '#pkg-' . $id, ['ok' => $action === 'activate' ? __('The package is offered again.') : __('The package is no longer offered. Existing imprint profiles keep it.')]);
    } elseif ($action === 'delete') {
        $p = package_get($id);
        if ($p === null) {
            abort(404);
        }
        if (!package_delete($id)) {
            redirect(ADMIN_PACKAGES_URL . '#pkg-' . $id, ['error' => __('This package is used by imprint profiles and cannot be deleted. Deactivate it instead.')]);
        }
        audit('package.delete', 'package', $id, ['slug' => $p['slug'], 'name' => $p['name']]);
        redirect(ADMIN_PACKAGES_URL, ['ok' => sprintf(__('The package "%s" was deleted.'), (string) $p['name'])]);
    } elseif ($action === 'note') {
        $raw = post_str('note', 1200);
        $clean = trim((string) preg_replace('/\s+/u', ' ', $raw));
        if (str_len($clean) > 1000) {
            $errors['_note'] = ['note' => __('The note may have at most 1000 characters.')];
            $note = $raw;
        } else {
            setting_set('packages.note', $clean);
            audit('package.note', 'setting', 'packages.note', ['length' => str_len($clean)]);
            redirect(ADMIN_PACKAGES_URL . '#pkg-note', ['ok' => __('The note was saved.')]);
        }
    } else {
        abort(400, __('Unknown action.'));
    }
}

$packages = packages_all();
$usage = [];
foreach ($packages as $p) {
    $usage[$p['id']] = package_profile_count($p['id']);
}
$withoutPackage = (int) (db_one('SELECT COUNT(*) AS n FROM profiles WHERE package_id IS NULL')['n'] ?? 0);
$default = package_default();

render_view('admin/packages', [
    'title' => __('Packages'),
    'packages' => $packages,
    'usage' => $usage,
    'withoutPackage' => $withoutPackage,
    'defaultPackage' => $default,
    'errors' => $errors,
    'values' => $values,
    'note' => $note,
    'globalLimit' => package_global_limit(),
]);
