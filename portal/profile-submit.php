<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

require_post();
csrf_verify();
$profile = portal_owned_profile(post_int('id'), $user);
$pid = (int) $profile['id'];
$back = '/portal/profile.php?id=' . $pid;

if ($profile['status'] === 'suspended') {
    redirect($back, ['error' => __('This imprint was suspended by our team. Please contact us.')]);
}
$open = revision_open($pid);
if ($open === null || $open['state'] !== 'draft') {
    redirect($back, ['error' => __('There is no draft to submit.')]);
}

$result = portal_submit_draft($profile, $open, $user);
if (!$result['ok']) {
    $fields = portal_error_fields($result['errors']);
    $message = $result['errors']['_form'] ?? __('Your draft cannot be submitted yet. Please check:') . ' ' . $fields;
    redirect('/portal/profile-edit.php?id=' . $pid . '&check=1', ['error' => $message]);
}
redirect($back, ['ok' => __('Thank you! Your imprint was submitted. Our team will review it and send you an e-mail.')]);
