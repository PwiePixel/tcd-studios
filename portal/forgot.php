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
$done = false;

if (is_post()) {
    csrf_verify();
    $email = post_str('email', 300);
    $result = auth_request_reset($email, 'customer');
    if ($result['ok']) {
        $done = true;      // same answer whether or not the address has an account
    } else {
        $error = $result['error'];
    }
}

render_view('portal/forgot', [
    'title' => __('Forgot your password?'),
    'email' => $email,
    'error' => $error,
    'done'  => $done,
]);
