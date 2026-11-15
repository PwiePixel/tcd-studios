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

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 20);
    $noteValue = post_str('note', 2000);
    $fingerprint = post_str('fp', 80);      // what the admin saw; see revision_fingerprint()
    $owner = $profile['owner_id'] !== null ? user_get((int) $profile['owner_id']) : null;
    $pid = (int) $profile['id'];
    $title = (string) ($rev['public']['display_name'] ?? $profile['slug']);

    if ($rev['state'] !== 'submitted') {
        redirect($self, ['error' => __('This revision has already been processed.')]);
    }

    if ($action === 'approve') {
        if (!post_bool('verified')) {
            $formError = __('Please confirm that you verified the identity and address.');
        } else {
            try {
                db_transaction(static function () use ($id, $pid, $admin, $noteValue, $fingerprint) {
                    $fresh = db_one('SELECT state FROM revisions WHERE id = ?', [$id]);
                    if (!$fresh || $fresh['state'] !== 'submitted') {
                        throw new RuntimeException('already processed', ADMIN_STATE_CONFLICT);
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
                if ($owner !== null) {
                    admin_mail_user($owner, __('Your imprint is online'), sprintf(
                        "your imprint \"%s\" has been approved and is now online:\n\n%s\n\n%sYou can change it at any time in your customer area; changes are reviewed again before they go live.",
                        $title,
                        imprint_public_url((string) $profile['slug']),
                        $noteValue !== '' ? "Note from our team:\n" . $noteValue . "\n\n" : ''
                    ));
                }
                redirect('/admin/reviews.php', ['ok' => sprintf(__('Approved and published: %s'), imprint_public_url((string) $profile['slug']))]);
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

render_view('admin/review', [
    'title' => __('Review') . ' #' . $id,
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
