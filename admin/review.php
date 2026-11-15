<?php
declare(strict_types=1);

/** Review of one submitted revision: identity check, diff, preview, approve / reject. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$id = admin_id();
$rev = revision_get($id);
if ($rev === null) {
    abort(404);
}
$profile = profile_get((int) $rev['profile_id']);
if ($profile === null) {
    abort(404);
}
$self = '/admin/review.php?id=' . $id;
$formError = '';
$noteValue = '';
$overrideTicked = false;
$overrideNote = '';

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 20);
    $noteValue = post_str('note', 2000);
    $fingerprint = post_str('fp', 80);      // what the admin saw; see revision_fingerprint()
    $idFingerprint = post_str('fp_id', 80); // the ID images the admin saw; see identity_fingerprint()
    $owner = $profile['owner_id'] !== null ? user_get((int) $profile['owner_id']) : null;
    $pid = (int) $profile['id'];
    $title = (string) ($rev['public']['display_name'] ?? $profile['slug']);

    if ($rev['state'] !== 'submitted') {
        redirect($self, ['error' => __('This revision has already been processed.')]);
    }

    // Identity gate: a first submission (or changed address data) of a customer needs an uploaded ID photo,
    // unless the admin documents that the identity was verified by other proof (exceptional cases).
    $idRequired = $owner !== null && identity_required_for($profile, $rev);
    $idMissing = $idRequired && !identity_upload_present($pid);
    $overrideTicked = post_bool('identity_override');
    $overrideNote = post_str('identity_override_note', 500);

    if ($action === 'approve') {
        if (!post_bool('verified')) {
            $formError = __('Please confirm that you verified the identity and address.');
        } elseif ($idMissing && !$overrideTicked) {
            $formError = vault_configured()
                ? __('No ID photo has been uploaded for this submission, so it cannot be approved. Ask the customer to upload one – or, in an exceptional case, tick "Identity verified by other proof" and describe the proof.')
                : __('No ID photo has been uploaded and the identity vault has no public key yet, so customers cannot upload one. Set up the vault first – or, in an exceptional case, tick "Identity verified by other proof" and describe the proof.');
        } elseif ($idMissing && $overrideNote === '') {
            $formError = __('Please describe the other proof of identity in the note field (for example "video identification on 2026-11-15"). Do not copy personal data into it.');
        } else {
            try {
                db_transaction(static function () use ($id, $pid, $admin, $noteValue, $fingerprint, $idFingerprint) {
                    $fresh = db_one('SELECT state FROM revisions WHERE id = ?', [$id]);
                    if (!$fresh || $fresh['state'] !== 'submitted') {
                        throw new RuntimeException('already processed', ADMIN_STATE_CONFLICT);
                    }
                    if (!hash_equals(identity_fingerprint($pid), $idFingerprint)) {
                        throw new RuntimeException('content changed');      // the customer changed the ID images after the page was opened
                    }
                    revision_approve($id, (int) $admin['id'], $noteValue, $fingerprint);
                    db_exec('UPDATE profiles SET identity_verified = 1 WHERE id = ?', [$pid]);
                    imprint_publish($pid);
                });
            } catch (Throwable $e) {
                if ($e->getCode() === ADMIN_STATE_CONFLICT) {
                    redirect($self, ['error' => __('This revision has already been processed.')]);
                }
                if ($e->getMessage() === 'content changed') {
                    redirect($self, ['error' => __('The customer changed this submission after you opened it. Please review the current version before you decide.')]);
                }
                if ($e->getMessage() === 'profile suspended') {
                    redirect($self, ['error' => __('This imprint is suspended. Reactivate it first or reject the submission.')]);
                }
                error_log('review approve failed (revision ' . $id . '): ' . $e->getMessage());
                $formError = __('The imprint could not be published, so nothing was changed. Please check the server log and try again.');
            }
            if ($formError === '') {
                audit('review.approve', 'revision', $id, ['profile_id' => $pid, 'slug' => (string) $profile['slug'], 'number' => (int) $rev['number']]);
                // Identity: mark the request verified and move the encrypted files to the archive. The approval stays valid
                // even if archiving fails; the admin is sent to the identity page to retry.
                $archiveWarning = '';
                $archiveNote = '';
                try {
                    if ($idMissing) {
                        $oid = admin_identity_record_override((int) $owner['id'], $pid, (int) $admin['id'], $overrideNote);
                        audit('review.identity_override', 'identity_request', $oid, ['profile_id' => $pid, 'revision_id' => $id]);
                    } else {
                        $idRes = admin_identity_verify_and_archive($pid, (int) $admin['id']);
                        if ($idRes !== null) {
                            audit('identity.verified', 'identity_request', $idRes['request_id'], ['revision_id' => $id, 'archived' => $idRes['archived'], 'failed' => $idRes['failed']]);
                            if ($idRes['failed'] > 0) {
                                $archiveWarning = '/admin/identity.php?id=' . $idRes['request_id'];
                                $archiveNote = implode('; ', array_slice($idRes['errors'], 0, 2));
                            } elseif ($idRes['archived'] > 0) {
                                $archiveNote = 'archived';
                            }
                        }
                    }
                } catch (Throwable $e) {
                    error_log('review identity step failed (revision ' . $id . '): ' . $e->getMessage());
                    $row = admin_identity_open_request($pid);
                    $archiveWarning = $row !== null ? '/admin/identity.php?id=' . (int) $row['id'] : '';
                    $archiveNote = __('unexpected error, see the server log');
                }
                if ($owner !== null) {
                    admin_mail_user($owner, __('Your imprint is online'), sprintf(
                        "your imprint \"%s\" has been approved and is now online:\n\n%s\n\n%sYou can change it at any time in your customer area; changes are reviewed again before they go live.",
                        $title,
                        imprint_public_url((string) $profile['slug']),
                        $noteValue !== '' ? "Note from our team:\n" . $noteValue . "\n\n" : ''
                    ));
                }
                if ($archiveWarning !== '') {
                    redirect($archiveWarning, ['error' => sprintf(
                        __('Approved and published (%s), BUT the ID files could not be moved to the secure archive: %s. They are still on the web server. Use "Archive now" below to retry.'),
                        imprint_public_url((string) $profile['slug']),
                        $archiveNote
                    )]);
                }
                redirect('/admin/reviews.php', ['ok' => sprintf(__('Approved and published: %s'), imprint_public_url((string) $profile['slug']))
                    . ($archiveNote === 'archived' ? ' ' . __('The ID files are now in the secure archive and were deleted from the web server.') : '')]);
            }
        }
    } elseif ($action === 'reject') {
        if ($noteValue === '') {
            $formError = __('Please explain to the customer why the imprint was rejected (note is required).');
        } else {
            try {
                db_transaction(static function () use ($id, $admin, $noteValue, $fingerprint) {
                    $fresh = db_one('SELECT state FROM revisions WHERE id = ?', [$id]);
                    if (!$fresh || $fresh['state'] !== 'submitted') {
                        throw new RuntimeException('already processed', ADMIN_STATE_CONFLICT);
                    }
                    revision_reject($id, (int) $admin['id'], $noteValue, $fingerprint);
                });
            } catch (Throwable $e) {
                if ($e->getCode() === ADMIN_STATE_CONFLICT) {
                    redirect($self, ['error' => __('This revision has already been processed.')]);
                }
                if ($e->getMessage() === 'content changed') {
                    redirect($self, ['error' => __('The customer changed this submission after you opened it. Please review the current version before you decide.')]);
                }
                error_log('review reject failed (revision ' . $id . '): ' . $e->getMessage());
                $formError = __('The revision could not be rejected. Please try again.');
            }
            if ($formError === '') {
                audit('review.reject', 'revision', $id, ['profile_id' => $pid, 'slug' => (string) $profile['slug'], 'number' => (int) $rev['number']]);
                if ($owner !== null) {
                    admin_mail_user($owner, __('Your imprint needs changes'), sprintf(
                        "your imprint \"%s\" could not be approved yet.\n\nReason:\n%s\n\nPlease update your details in the customer area and submit them again:\n%s",
                        $title,
                        $noteValue,
                        site_url('/portal/dashboard.php')
                    ));
                }
                redirect('/admin/reviews.php', ['ok' => __('The revision was rejected and the customer was informed.')]);
            }
        }
    } else {
        abort(400, __('Unknown action.'));
    }
}

$owner = $profile['owner_id'] !== null ? user_get((int) $profile['owner_id']) : null;
$live = revision_live((int) $profile['id']);
if ($live !== null && (int) $live['id'] === $id) {
    $live = null;              // reviewing the live revision itself: nothing to compare with
}
$diff = imprint_diff($live ? $live['public'] : [], $rev['public']);
$privateChanged = [];
if ($live !== null) {
    foreach (imprint_private_defaults() as $field => $_) {
        if ((string) ($live['private'][$field] ?? '') !== (string) ($rev['private'][$field] ?? '')) {
            $privateChanged[$field] = (string) ($live['private'][$field] ?? '');
        }
    }
}

$identity = [
    'required' => $owner !== null && identity_required_for($profile, $rev),
    'present' => identity_upload_present((int) $profile['id']),
    'request' => admin_identity_open_request((int) $profile['id']) ?? identity_request_for_profile((int) $profile['id']),
    'vault_ready' => vault_configured(),
];
$identity['files'] = $identity['request'] !== null ? count(identity_files((int) $identity['request']['id'])) : 0;

render_view('admin/review', [
    'title' => __('Review') . ' #' . $id,
    'identity' => $identity,
    'overrideTicked' => $overrideTicked,
    'overrideNote' => $overrideNote,
    'rev' => $rev,
    'profile' => $profile,
    'owner' => $owner,
    'live' => $live,
    'diff' => $diff,
    'privateChanged' => $privateChanged,
    'preview' => imprint_render_fragment($profile, $rev['public']),
    'history' => revisions_for_profile((int) $profile['id']),
    'formError' => $formError,
    'noteValue' => $noteValue,
    'reviewers' => admin_reviewer_names(revisions_for_profile((int) $profile['id'])),
]);
