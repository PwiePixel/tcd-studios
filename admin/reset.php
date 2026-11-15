<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');

$token = is_post() ? post_str('token', 100) : get_str('token', 100);
if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
    redirect('/admin/forgot.php', ['error' => __('This link is invalid or has expired. Please request a new one.')]);
}

$error = '';
if (is_post()) {
    csrf_verify();
    $pw = admin_post_raw('password');
    if (!rate_limit_hit('reset_post:ip:' . client_ip(), config('rate.reset_ip'))) {
        $error = __('Too many attempts. Please try again later.');
    } elseif (!safe_equals($pw, admin_post_raw('password2'))) {
        $error = __('The two passwords are not identical.');
    } else {
        $err = auth_reset_password($token, $pw, 'admin');
        if ($err === null) {
            redirect('/admin/login.php', ['ok' => __('Your password was changed. Please sign in.')]);
        }
        $error = $err;
    }
}
render_view('admin/reset', ['title' => __('Choose a new password'), 'error' => $error, 'token' => $token]);
