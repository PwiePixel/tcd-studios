<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

// GET shows the confirmation step, POST (with CSRF token) does it.
$profile = portal_owned_profile(is_post() ? post_int('id') : get_int('id'), $user);
$pid = (int) $profile['id'];
$back = '/portal/profile.php?id=' . $pid;
if ($profile['status'] !== 'live') {
    redirect($back, ['error' => __('Only an imprint that is online can be taken offline.')]);
}

if (is_post()) {
    csrf_verify();
    if (!post_bool('confirm')) {
        redirect('/portal/profile-offline.php?id=' . $pid, ['error' => __('Please tick the box to confirm.')]);
    }
    if (portal_take_offline($profile)) {
        redirect($back, ['ok' => __('Your imprint page is offline now.')]);
    }
    redirect($back, ['error' => __('The page could not be taken offline. Please contact us.')]);
}

$summary = portal_profile_summary($profile);
render_view('portal/profile-offline', [
    'title'   => __('Take imprint offline'),
    'profile' => $profile,
    's'       => $summary,
]);
