<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();

// A package chosen on the public page (/imprint-protection/: register.php?package=<slug>) is remembered in the
// session and preselected when the customer creates the first imprint profile.
$package = get_str('package', 40) !== '' ? portal_package_remember(get_str('package', 40)) : null;
if ($package === null && isset($_SESSION['package_choice']) && is_string($_SESSION['package_choice'])) {
    $package = package_active_by_slug($_SESSION['package_choice']);
}

if (auth_current_user() !== null) {
    redirect($package !== null ? '/portal/profile-new.php?package=' . rawurlencode((string) $package['slug']) : '/portal/dashboard.php');
}

$values = ['name' => '', 'email' => '', 'privacy' => '', 'accept_terms' => ''];
$errors = [];
$done = false;

if (is_post()) {
    csrf_verify();
    $values['name'] = post_str('name', 200);
    $values['email'] = post_str('email', 300);
    $values['privacy'] = post_bool('privacy') ? '1' : '';
    $values['accept_terms'] = post_bool('accept_terms') ? '1' : '';
    $password = portal_post_password('password');
    $password2 = portal_post_password('password2');

    if (post_str('website_url', 200) !== '') {
        // Honeypot filled: a bot. Behave exactly like a success, do nothing.
        $done = true;
    } else {
        if (!post_bool('privacy')) {
            $errors['privacy'] = __('Please confirm that you have read the privacy policy.');
        }
        if (!post_bool('accept_terms')) {
            $errors['accept_terms'] = __('Please confirm that you are a content creator and accept the Terms and Conditions (AGB).');
        }
        if ($password !== $password2) {
            $errors['password2'] = __('The two passwords do not match.');
        }
        if ($errors === []) {
            $result = auth_register($values['email'], $values['name'], $password, true);
            if ($result['ok']) {
                $done = true;     // identical for new and existing addresses (no account enumeration)
            } else {
                $errors['form'] = $result['error'];
            }
        }
    }
}

render_view('portal/register', [
    'title'  => __('Create your account'),
    'values' => $values,
    'errors' => $errors,
    'done'   => $done,
    'package' => $package,
]);
