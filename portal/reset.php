<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();

if (auth_current_user() !== null) {
    redirect('/portal/dashboard.php');
}

$error = '';
if (is_post()) {
    csrf_verify();
    $token = post_str('token', 128);
    $password = portal_post_password('password');
    if ($password !== portal_post_password('password2')) {
        $error = __('The two passwords do not match.');
    } else {
        $failure = auth_reset_password($token, $password, 'customer');
        if ($failure === null) {
            redirect('/portal/login.php', ['ok' => __('Your password has been changed. You can sign in now.')]);
        }
        $error = $failure;
    }
} else {
    $token = get_str('token', 128);
}

render_view('portal/reset', [
    'title' => __('Choose a new password'),
    'token' => $token,
    'valid' => portal_reset_token_valid($token),
    'error' => $error,
]);
