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
        // Keep a package chosen before the registration (see register.php) across the fresh login session.
        $packageChoice = isset($_SESSION['package_choice']) && is_string($_SESSION['package_choice']) ? $_SESSION['package_choice'] : '';
        auth_login_complete($result['user']);
        if ($packageChoice !== '' && package_active_by_slug($packageChoice) !== null) {
            $_SESSION['package_choice'] = $packageChoice;
            if (profiles_by_owner((int) $result['user']['id']) === []) {
                redirect('/portal/profile-new.php');     // fixed target: the package comes from the session
            }
        }
        redirect('/portal/dashboard.php');      // fixed target: no "next" parameter, so no open redirect
    }
    $error = $result['error'];
}

render_view('portal/login', [
    'title' => __('Sign in'),
    'email' => $email,
    'error' => $error,
]);
