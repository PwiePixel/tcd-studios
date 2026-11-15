<?php
declare(strict_types=1);

/** Service catalogue editor (read by the public site and the customer portal). */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

/**
 * Validate one service form.
 *
 * @param array<string,string> $in
 * @return array{0:array<string,mixed>,1:array<string,string>}
 */
function admin_services_validate(array $in, bool $isNew): array
{
    $err = [];
    $slug = (string) ($in['slug'] ?? '');
    if ($isNew) {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1 || strlen($slug) < 2 || strlen($slug) > 40) {
            $err['slug'] = __('The identifier may contain only lower-case letters, digits and single hyphens (2–40 characters).');
        } elseif (service_get($slug) !== null) {
            $err['slug'] = __('This identifier already exists.');
        }
    }
    $name = trim((string) ($in['name'] ?? ''));
    if (!v_len($name, 2, 80) || !v_oneline($name)) {
        $err['name'] = __('The name needs 2–80 characters on one line.');
    }
    $summary = trim((string) ($in['summary'] ?? ''));
    if (str_len($summary) > 500) {
        $err['summary'] = __('The summary may have at most 500 characters.');
    }
    $details = trim((string) ($in['details'] ?? ''));
    if (str_len($details) > 4000) {
        $err['details'] = __('The details may have at most 4000 characters.');
    }
    $availability = (string) ($in['availability'] ?? '');
    if (!in_array($availability, admin_service_availabilities(), true)) {
        $err['availability'] = __('Please choose an availability.');
    }
    $sort = (string) ($in['sort_order'] ?? '100');
    if (preg_match('/^\d{1,4}$/D', $sort) !== 1) {
        $err['sort_order'] = __('The sort order must be a number between 0 and 9999.');
    }
    return [[
        'slug' => $slug, 'name' => $name, 'summary' => $summary, 'details' => $details, 'availability' => $availability,
        'requestable' => !empty($in['requestable']) ? 1 : 0, 'sort_order' => (int) $sort,
    ], $err];
}

$errors = [];     // form key (service slug or "_new") => field errors
$values = [];     // form key => submitted values

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 20);
    $slug = post_str('slug', 40);
    $input = [
        'slug' => $slug, 'name' => post_str('name', 200), 'summary' => post_str('summary', 600), 'details' => post_str('details', 4200),
        'availability' => post_str('availability', 20), 'requestable' => post_bool('requestable') ? '1' : '', 'sort_order' => post_str('sort_order', 10),
    ];
    if ($action === 'create' || $action === 'save') {
        $isNew = $action === 'create';
        $key = $isNew ? '_new' : $slug;
        if (!$isNew && service_get($slug) === null) {
            abort(404);
        }
        [$clean, $err] = admin_services_validate($input, $isNew);
        if ($err) {
            $errors[$key] = $err;
            $values[$key] = $input;
        } else {
            service_save($clean);
            audit($isNew ? 'service.create' : 'service.update', 'service', $clean['slug'], ['availability' => $clean['availability'], 'requestable' => $clean['requestable']]);
            redirect('/admin/services.php', ['ok' => $isNew ? __('The service was added.') : __('The service was saved.')]);
        }
    } else {
        abort(400, __('Unknown action.'));
    }
}

render_view('admin/services', ['title' => __('Services'), 'services' => services_all(true), 'errors' => $errors, 'values' => $values]);
