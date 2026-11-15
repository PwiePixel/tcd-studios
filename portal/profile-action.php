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
$action = post_str('action', 40);

if ($profile['status'] === 'suspended') {
    redirect($back, ['error' => __('This imprint was suspended by our team. Please contact us.')]);
}
$open = revision_open($pid);

if ($action === 'withdraw_submission') {
    if ($open === null || $open['state'] !== 'submitted' || !portal_revision_withdraw((int) $open['id'])) {
        redirect($back, ['error' => __('There is no submission that can be withdrawn (it may have been reviewed in the meantime).')]);
    }
    audit('revision.withdraw', 'revision', (int) $open['id'], ['profile_id' => $pid]);
    redirect($back, ['ok' => __('Submission withdrawn. It is a draft again and you can edit it.')]);
}

if ($action === 'request_changes') {
    if ($open !== null) {
        redirect($back, ['error' => __('There is already an open version of this imprint.')]);
    }
    try {
        $rid = revision_create_draft($pid);
    } catch (RuntimeException $e) {
        redirect($back, ['error' => __('There is already an open version of this imprint.')]);
    }
    audit('revision.create_draft', 'revision', $rid, ['profile_id' => $pid]);
    redirect('/portal/profile-edit.php?id=' . $pid, ['ok' => __('A new draft was created from your last version. Your live imprint stays online until the new version is approved.')]);
}

redirect($back, ['error' => __('Unknown action.')]);
