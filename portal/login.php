<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();

if (auth_current_user() !== null) {
    redirect('/portal/dashboard.php');
}

$email = '';
$error = '';

if (is_post()) {
    csrf_verify();
    $email = post_str('email', 300);
    $result = auth_login($email, portal_post_password('password'), 'customer');
    if ($result['ok'] && $result['user'] !== null) {
        auth_login_complete($result['user']);
        redirect('/portal/dashboard.php');      // fixed target: no "next" parameter, so no open redirect
    }
    $error = $result['error'];
}

render_view('portal/login', [
    'title' => __('Sign in'),
    'email' => $email,
    'error' => $error,
]);
