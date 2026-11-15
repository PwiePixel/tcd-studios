<?php
declare(strict_types=1);

/**
 * One identity request: customer, entered address data, stored files and the in-browser decryption of the ID photos.
 * The page itself never sees an ID photo or the private key (js/vault.js decrypts in the browser).
 */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
send_security_headers(['img-src' => "'self' data: blob:", 'connect-src' => "'self'"]);   // blob: images + fetch of the ciphertext
$admin = auth_require('admin');

$id = admin_id();
$req = identity_request_get($id);
if ($req === null || $req['status'] === 'erased') {
    abort(404);
}
$self = '/admin/identity.php?id=' . $id;

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 20);
    if ($action !== 'archive') {
        abort(400, __('Unknown action.'));
    }
    if (!in_array($req['status'], ['verified', 'archived'], true)) {
        redirect($self, ['error' => __('This request has not been verified yet. It is archived automatically when you approve the imprint.')]);
    }
    try {
        $res = identity_archive_request($id);
    } catch (Throwable $e) {
        error_log('identity archive failed (request ' . $id . '): ' . $e->getMessage());
        $res = ['archived' => 0, 'failed' => 1, 'errors' => [$e->getMessage()]];
    }
    audit('identity.archive', 'identity_request', $id, ['archived' => $res['archived'], 'failed' => $res['failed']]);
    if ($res['failed'] > 0) {
        redirect($self, ['error' => sprintf(__('%d file(s) could not be archived: %s'), $res['failed'], implode('; ', array_slice($res['errors'], 0, 3)))]);
    }
    redirect($self, ['ok' => $res['archived'] > 0 ? sprintf(__('%d file(s) are in the secure archive; the web server copies were deleted.'), $res['archived']) : __('There are no files to archive.')]);
}

$customer = $req['user_id'] !== null ? user_get((int) $req['user_id']) : null;
$profile = $req['profile_id'] !== null ? profile_get((int) $req['profile_id']) : null;
$revision = null;
if ($profile !== null) {
    $revision = revision_open((int) $profile['id']) ?? revision_latest((int) $profile['id']);
}
$verifier = $req['verified_by'] !== null ? user_get((int) $req['verified_by']) : null;
$files = identity_files($id);
$pending = 0;                       // files that should be in the archive but still have a web server copy
foreach ($files as $f) {
    if ($f['inbox_name'] !== null && $f['inbox_name'] !== '' && in_array($req['status'], ['verified', 'archived'], true)) {
        $pending++;
    }
}

render_view('admin/identity', [
    'title' => __('Identity documents') . ' #' . $id,
    'req' => $req,
    'customer' => $customer,
    'profile' => $profile,
    'revision' => $revision,
    'verifier' => $verifier,
    'files' => $files,
    'pending' => $pending,
    'vaultReady' => vault_configured(),
]);
