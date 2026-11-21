<?php
declare(strict_types=1);

/** One imprint profile: details, private data, revision history and admin actions. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$id = admin_id();
$profile = profile_get($id);
if ($profile === null) {
    abort(404);
}
$self = '/admin/profile.php?id=' . $id;
$slug = (string) $profile['slug'];
$title = admin_profile_title($profile);
$owner = $profile['owner_id'] !== null ? user_get((int) $profile['owner_id']) : null;
$isLive = $profile['status'] === 'live' && $profile['live_revision_id'] !== null;

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 30);

    if ($action === 'suspend') {
        if ($profile['status'] !== 'live') {
            redirect($self, ['error' => __('Only live imprints can be suspended.')]);
        }
        $reason = post_str('reason', 1000);
        db_transaction(static function () use ($id) {
            profile_set_status($id, 'suspended');
            imprint_unpublish($id);
        });
        audit('profile.suspend', 'profile', $id, ['slug' => $slug]);
        admin_mail_user($owner, __('Your imprint has been suspended'), sprintf(
            "your imprint \"%s\" (%s) has been suspended and is no longer publicly available.\n\n%sIf you have questions, please contact us at %s.",
            $title,
            imprint_public_url($slug),
            $reason !== '' ? "Reason:\n" . $reason . "\n\n" : '',
            (string) config('mail.imprint')
        ));
        redirect($self, ['ok' => __('The imprint was suspended and taken offline.')]);
    }

    if ($action === 'reactivate') {
        if ($profile['status'] !== 'suspended' || $profile['live_revision_id'] === null) {
            redirect($self, ['error' => __('Only a suspended imprint with an approved version can be reactivated.')]);
        }
        try {
            db_transaction(static function () use ($id) {
                profile_set_status($id, 'live');
                imprint_publish($id);
            });
        } catch (Throwable $e) {
            error_log('profile reactivate failed (' . $id . '): ' . $e->getMessage());
            redirect($self, ['error' => __('The imprint page could not be written, so nothing was changed. Please check the server log.')]);
        }
        audit('profile.reactivate', 'profile', $id, ['slug' => $slug]);
        admin_mail_user($owner, __('Your imprint is online again'), sprintf("your imprint \"%s\" is online again:\n\n%s", $title, imprint_public_url($slug)));
        redirect($self, ['ok' => __('The imprint is live again.')]);
    }

    if ($action === 'republish') {
        if (!$isLive) {
            redirect($self, ['error' => __('Only live imprints can be re-published.')]);
        }
        try {
            imprint_publish($id);
        } catch (Throwable $e) {
            error_log('profile republish failed (' . $id . '): ' . $e->getMessage());
            redirect($self, ['error' => __('The imprint page could not be written. Please check the server log.')]);
        }
        audit('profile.republish', 'profile', $id, ['slug' => $slug]);
        redirect($self, ['ok' => __('The page was written again.')]);
    }

    if ($action === 'assign_owner') {
        $email = mb_strtolower(post_str('owner_email', 254), 'UTF-8');
        $newOwnerId = null;
        if ($email !== '') {
            $target = user_get_by_email($email);
            if ($target === null || $target['role'] !== 'customer') {
                redirect($self, ['error' => __('There is no customer account with this e-mail address.')]);
            }
            $newOwnerId = (int) $target['id'];
        }
        db_exec('UPDATE profiles SET owner_id = ?, updated_at = ? WHERE id = ?', [$newOwnerId, now(), $id]);
        db_exec('UPDATE identity_requests SET user_id = ? WHERE profile_id = ?', [$newOwnerId, $id]);     // keep ID files findable for the new owner's erasure
        audit('profile.owner_changed', 'profile', $id, ['slug' => $slug, 'from_user' => $profile['owner_id'] !== null ? (int) $profile['owner_id'] : null, 'to_user' => $newOwnerId]);
        redirect($self, ['ok' => $newOwnerId === null ? __('The owner was removed.') : __('The owner was changed.')]);
    }

    if ($action === 'change_package') {
        $packageId = post_int('package_id');
        $package = package_get($packageId);
        if ($package === null) {
            redirect($self . '#package', ['error' => __('Please choose a package.')]);
        }
        $before = package_of_profile($profile);
        profile_set_package($id, $packageId);
        audit('profile.package', 'profile', $id, ['slug' => $slug, 'from' => $profile['package_id'] !== null && $before !== null ? $before['slug'] : null,
            'to' => $package['slug'], 'by' => 'admin']);
        $msg = ['ok' => sprintf(__('The package was changed to "%s".'), (string) $package['name'])];
        if ($owner !== null) {
            $ids = package_ids_of_owner((int) $owner['id']);
            $limit = package_limit_for($ids);
            if (count($ids) > $limit) {
                $msg['error'] = sprintf(__('Note: the owner now has %1$d imprint profiles, but his packages include only %2$d. He cannot create more profiles until this is resolved.'), count($ids), $limit);
            }
        }
        redirect($self . '#package', $msg);
    }

    if ($action === 'change_slug') {
        $new = mb_strtolower(post_str('slug', 60), 'UTF-8');
        if ($new === $slug) {
            redirect($self, ['error' => __('The page name is unchanged.')]);
        }
        $err = imprint_slug_error($new, $id);
        if ($err !== null) {
            redirect($self, ['error' => $err]);
        }
        try {
            db_transaction(static function () use ($id, $new, $isLive) {
                if ($isLive) {
                    imprint_unpublish($id);          // removes the file of the OLD slug (looked up from the database)
                }
                db_exec('UPDATE profiles SET slug = ?, updated_at = ? WHERE id = ?', [$new, now(), $id]);
                if ($isLive) {
                    imprint_publish($id);
                }
            });
        } catch (Throwable $e) {
            error_log('profile slug change failed (' . $id . '): ' . $e->getMessage());
            if ($isLive) {
                try {
                    imprint_publish($id);            // the database is rolled back: bring the old page back
                } catch (Throwable $e2) {
                    error_log('profile slug change: restoring the old page failed: ' . $e2->getMessage());
                }
            }
            redirect($self, ['error' => __('The page name could not be changed. Please check the server log.')]);
        }
        if ($isLive) {
            try {
                imprint_write_legacy_redirects();
            } catch (Throwable $e) {
                error_log('legacy redirects failed: ' . $e->getMessage());
            }
        }
        audit('profile.slug_changed', 'profile', $id, ['from' => $slug, 'to' => $new]);
        if ($isLive) {
            admin_mail_user($owner, __('The address of your imprint has changed'), sprintf("the address of your imprint \"%s\" has changed.\n\nNew address:\n%s\n\nThe old address no longer works.", $title, imprint_public_url($new)));
        }
        redirect($self, ['ok' => __('The page name was changed.')]);
    }

    if ($action === 'delete') {
        if (!safe_equals($slug, post_str('confirm_slug', 60))) {
            redirect($self, ['error' => __('To delete the imprint, type its page name exactly.')]);
        }
        try {
            db_transaction(static function () use ($id, $slug, $profile) {
                imprint_unpublish($id);
                vault_delete_profile_files($id);                       // ID photos in inbox AND archive, whoever uploaded them
                audit_scrub_profile($id, $slug, $profile['owner_id'] !== null ? (int) $profile['owner_id'] : null);
                db_exec('DELETE FROM revisions WHERE profile_id = ?', [$id]);
                db_exec('DELETE FROM identity_requests WHERE profile_id = ?', [$id]);
                db_exec('DELETE FROM profiles WHERE id = ?', [$id]);
            });
        } catch (Throwable $e) {
            error_log('admin profile delete failed: ' . $e->getMessage());
            redirect($self, ['error' => __('The imprint could not be deleted completely (a storage system did not answer). Nothing else was removed; please try again.')]);
        }
        try {
            imprint_try_sitemap();
        } catch (Throwable $e) {
            error_log('sitemap refresh failed: ' . $e->getMessage());
        }
        audit('profile.delete', 'profile', $id, []);
        redirect('/admin/profiles.php', ['ok' => sprintf(__('The imprint "%s" was deleted.'), $slug)]);
    }

    abort(400, __('Unknown action.'));
}

$history = revisions_for_profile($id);
render_view('admin/profile', [
    'title' => $title,
    'profile' => $profile,
    'owner' => $owner,
    'name' => $title,
    'latest' => revision_latest($id),
    'history' => $history,
    'reviewers' => admin_reviewer_names($history),
    'isLive' => $isLive,
    'package' => package_of_profile($profile),
    'packages' => packages_all(),
]);
