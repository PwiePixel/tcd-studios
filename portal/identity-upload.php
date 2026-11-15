<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

require_post();
// When the whole request is bigger than post_max_size, PHP throws away $_POST and $_FILES (the CSRF token with them).
// Nothing was received or stored then: explain it instead of showing a confusing "form expired" error.
if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $guess = get_int('id');
    redirect($guess > 0 ? '/portal/identity.php?id=' . $guess : '/portal/dashboard.php', ['error' => sprintf(
        __('The upload was too large (maximum %d MB per image). Nothing was saved. Please choose smaller images.'),
        portal_identity_max_mb()
    )]);
}
csrf_verify();
$profile = portal_owned_profile(get_int('id') ?: post_int('id'), $user);      // 404 for foreign ids
$pid = (int) $profile['id'];
$uid = (int) $user['id'];
$self = '/portal/identity.php?id=' . $pid;
$action = post_str('action', 20);

if ($action === 'remove') {
    // One image of the open request, own files only (a foreign or unknown id answers 404 like a foreign profile).
    $file = identity_file_get(post_int('file_id'));
    $request = $file !== null ? identity_request_get((int) $file['request_id']) : null;
    if ($file === null || $request === null || (int) $request['user_id'] !== $uid || (int) $request['profile_id'] !== $pid) {
        abort(404, __('This page does not exist.'));
    }
    if ($file['deleted_at'] !== null) {
        redirect($self, ['ok' => __('This image was already removed.')]);
    }
    if ($request['status'] !== 'uploaded' || !empty(portal_identity_state($profile)['under_review'])) {
        redirect($self, ['error' => __('Your images are already being checked and cannot be changed any more.')]);
    }
    try {
        vault_delete_file((int) $file['id']);
    } catch (RuntimeException $e) {
        error_log('portal: identity remove failed: ' . $e->getMessage());
        redirect($self, ['error' => __('The image could not be removed because of a technical problem. Please try again later.')]);
    }
    audit('identity.remove', 'profile', $pid, ['side' => (string) $file['side']]);
    redirect($self, ['ok' => __('The image was removed and deleted.')]);
}

if ($action !== 'upload') {
    redirect($self, ['error' => __('Unknown action.')]);
}

$state = portal_identity_state($profile);
if (!empty($state['under_review'])) {
    redirect($self, ['error' => __('Your submission is being reviewed. The ID photos cannot be changed until the review is finished (you can withdraw the submission first).')]);
}
if (!vault_configured()) {
    redirect($self, ['error' => __('The identity check is temporarily unavailable. Please try again later.')]);
}
if (!vault_transport_ok()) {
    redirect($self, ['error' => __('For your security, ID photos can only be uploaded over a secure HTTPS connection. Please open this page with https:// and try again.')]);
}
if (!$state['can_upload']) {
    redirect($self, ['error' => (string) $profile['status'] === 'suspended'
        ? __('This imprint was suspended by our team. Please contact us.')
        : __('Your identity check is already complete. Nothing has to be uploaded.')]);
}

// Decoding a large image costs CPU and memory, so successful uploads are limited tightly (12/hour per customer); failed attempts
// are cheap (they are refused before decoding) and only limited loosely.
$limitOk = [12, 3600];
if (!rate_limit_peek('idupload:ok:' . $uid, $limitOk) || !rate_limit_hit('idupload:try:' . $uid, [90, 3600]) || !rate_limit_hit('idupload:ip:' . client_ip(), [150, 3600])) {
    redirect($self, ['error' => __('Too many uploads. Please try again in an hour.')]);
}

$errors = [];
$submitted = [];
foreach (['front', 'back'] as $side) {
    $f = $_FILES[$side] ?? null;
    if (!is_array($f)) {
        continue;
    }
    if (is_array($f['error'] ?? null)) {
        $errors[$side] = __('Please choose a single image file.');
        continue;
    }
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;                                      // this side was left empty
    }
    $submitted[$side] = $f;
}
if ($submitted === [] && $errors === []) {
    $errors['_form'] = __('Please choose at least one image: the front or the back of your ID card.');
}

$saved = [];
if ($submitted !== []) {
    // Images of a rejected request are of no use any more: delete them when the customer starts over.
    $previous = identity_request_for_profile($pid);
    if ($previous !== null && $previous['status'] === 'rejected') {
        foreach (identity_files((int) $previous['id']) as $old) {
            try {
                vault_delete_file((int) $old['id']);
            } catch (RuntimeException $e) {
                error_log('portal: cleanup of a rejected identity file failed: ' . $e->getMessage());
            }
        }
    }
    $rid = identity_request_open($uid, $pid);
    foreach ($submitted as $side => $upload) {
        try {
            vault_store_upload($rid, $side, $upload);
            rate_limit_hit('idupload:ok:' . $uid, $limitOk);      // counts only images that were really decoded and stored
            $saved[] = $side;
            audit('identity.upload', 'profile', $pid, ['side' => $side]);
        } catch (InvalidArgumentException $e) {
            $errors[$side] = $e->getMessage();          // customer-facing text from vault.php
        } catch (RuntimeException $e) {
            error_log('portal: identity upload failed: ' . $e->getMessage());
            $errors[$side] = __('We could not store this image because of a technical problem. Please try again later.');
        }
    }
}

if ($errors === []) {
    redirect($self, ['ok' => count($saved) > 1
        ? __('Thank you! Both images were uploaded and encrypted. Our team will check them when your imprint is reviewed.')
        : __('Thank you! The image was uploaded and encrypted. Our team will check it when your imprint is reviewed.')]);
}
if ($saved !== []) {
    flash_add('ok', sprintf(__('Saved and encrypted: %s.'), implode(' + ', array_map(static function (string $side): string {
        return $side === 'front' ? __('front side') : __('back side');
    }, $saved))));
}
flash_add('error', __('Not everything could be saved. Please check the messages below.'));
render_view('portal/identity', portal_identity_view_vars(portal_owned_profile($pid, $user), $errors));
