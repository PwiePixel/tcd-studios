<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');
portal_require_terms($user);         // creating a profile needs the current Terms and Conditions

[$count, $max] = portal_profile_limit((int) $user['id']);
$limitReached = $count >= $max;
$values = ['display_name' => '', 'kind' => 'creator', 'slug' => ''];
$errors = [];

if (is_post()) {
    csrf_verify();
    if ($limitReached) {
        redirect('/portal/dashboard.php', ['error' => sprintf(__('You can have at most %d imprint profiles.'), $max)]);
    }
    $values['display_name'] = post_str('display_name', 200);
    $values['kind'] = post_str('kind', 20);
    $slugInput = post_str('slug', 100);

    // Imprint Protection is for creators only: customers can never create a shop (or "other") profile themselves.
    $creatorKinds = imprint_creator_kinds();
    $kindIsCreator = isset($creatorKinds[$values['kind']]);
    $kindForDefaults = $kindIsCreator ? $values['kind'] : 'creator';
    $public = imprint_public_defaults($kindForDefaults, $values['display_name']);
    $public['display_name'] = $values['display_name'];
    $public['kind'] = $kindForDefaults;
    [$cleanPublic, $publicErrors] = imprint_clean_public($public);
    foreach (['display_name', 'kind'] as $field) {
        if (isset($publicErrors[$field])) {
            $errors[$field] = $publicErrors[$field];
        }
    }
    if (!$kindIsCreator) {
        $errors['kind'] = isset(imprint_kinds()[$values['kind']])
            ? __('Imprint Protection is for content creators and streamers. Please choose one of these types. Imprint protection for shops is available on request.')
            : __('Please choose a valid type.');
        $values['kind'] = 'creator';
    }

    // Page name: lenient input ("My Page" becomes "my-page"); empty = suggestion from the public name.
    $slug = $slugInput !== '' ? slugify($slugInput) : imprint_slug_suggest($values['display_name']);
    $values['slug'] = $slug;
    $slugError = imprint_slug_error($slug);
    if ($slugError !== null) {
        $errors['slug'] = $slugError;
    }

    if ($errors === []) {
        try {
            $pid = profile_create((int) $user['id'], $slug, $cleanPublic, imprint_private_defaults());
        } catch (PDOException $e) {
            // Lost a race for the page name (UNIQUE constraint).
            $errors['slug'] = __('This page name is already taken.');
            $pid = 0;
        }
        if ($pid > 0) {
            audit('profile.create', 'profile', $pid, ['slug' => $slug]);
            redirect('/portal/profile-edit.php?id=' . $pid, ['ok' => __('Your imprint profile was created. Please fill in your details below.')]);
        }
    }
} elseif ($limitReached) {
    $errors['limit'] = sprintf(__('You can have at most %d imprint profiles.'), $max);
}

render_view('portal/profile-new', [
    'title'        => __('Create imprint profile'),
    'values'       => $values,
    'errors'       => $errors,
    'limitReached' => $limitReached,
    'maxProfiles'  => $max,
    'baseUrl'      => site_url('/legal/'),
]);
