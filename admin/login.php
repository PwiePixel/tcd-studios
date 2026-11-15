<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');

if (auth_current_user() !== null) {
    redirect('/admin/index.php');
}
if (!admin_exists()) {
    redirect('/admin/setup.php');
}

$error = '';
$email = '';
if (is_post()) {
    csrf_verify();
    $email = post_str('email', 254);
    $res = auth_login($email, admin_post_raw('password'), 'admin');
    if ($res['ok']) {
        $user = (array) $res['user'];
        if (admin_totp_active($user)) {
            // Password is right, the second factor is still missing: no uid in the session yet.
            session_regenerate_id(true);
            $keep = $_SESSION['last_seen'] ?? now();
            $_SESSION = ['totp_pending' => ['uid' => (int) $user['id'], 'at' => now()], 'last_seen' => $keep];
            redirect('/admin/login-2fa.php');
        }
        auth_login_complete($user);
        redirect('/admin/index.php');
    }
    $error = $res['error'];
}

render_view('admin/login', ['title' => __('Sign in'), 'error' => $error, 'email' => $email]);
