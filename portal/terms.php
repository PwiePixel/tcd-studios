<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

$errors = [];

if (is_post()) {
    csrf_verify();
    if (portal_terms_accepted($user)) {
        redirect('/portal/dashboard.php', ['ok' => __('You have already accepted the current Terms and Conditions.')]);
    }
    if (!post_bool('accept_terms')) {
        $errors['accept_terms'] = __('Please confirm that you are a content creator and accept the current Terms and Conditions (AGB).');
    } else {
        portal_terms_accept((int) $user['id']);
        redirect('/portal/dashboard.php', ['ok' => __('Thank you! Your acceptance of the Terms and Conditions was recorded.')]);
    }
}

$info = portal_terms_info($user);
render_view('portal/terms', [
    'title'    => __('Terms and Conditions'),
    'accepted' => portal_terms_accepted($user),
    'info'     => $info,
    'version'  => portal_terms_version(),
    'errors'   => $errors,
]);
