<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();

if (auth_current_user() !== null) {
    redirect('/portal/dashboard.php');
}

$values = ['name' => '', 'email' => ''];
$errors = [];
$done = false;

if (is_post()) {
    csrf_verify();
    $values['name'] = post_str('name', 200);
    $values['email'] = post_str('email', 300);
    $password = portal_post_password('password');
    $password2 = portal_post_password('password2');

    if (post_str('website_url', 200) !== '') {
        // Honeypot filled: a bot. Behave exactly like a success, do nothing.
        $done = true;
    } else {
        if (!post_bool('privacy')) {
            $errors['privacy'] = __('Please confirm that you have read the privacy policy.');
        }
        if ($password !== $password2) {
            $errors['password2'] = __('The two passwords do not match.');
        }
        if ($errors === []) {
            $result = auth_register($values['email'], $values['name'], $password);
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
]);
