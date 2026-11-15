<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Ending the service and erasing all data of a customer.
 *
 * Flow (customer side):
 *   1. termination_request():   the customer asks to end the service -> a deletion request exists in the system (the team
 *                               is notified) and a confirmation link is mailed (valid config('termination_token_hours')).
 *   2. termination_confirm():   the customer confirms (logged in + password + the mailed token) that the service ends, that
 *                               everything is erased irrevocably and that the service may no longer be used. The erasure
 *                               then runs automatically (account_erase()).
 * A "legal hold" (users.legal_hold, set by an admin, e.g. open claim or authority request) blocks the automatic erasure; the
 * request then waits with status 'on_hold'.
 */

/** The open deletion request of a user (requested/confirmed/on_hold), or null. @return array<string,mixed>|null */
function termination_pending(int $userId): ?array
{
    return db_one('SELECT * FROM deletion_requests WHERE user_id = ? AND status IN (\'requested\',\'confirmed\',\'on_hold\') ORDER BY id DESC LIMIT 1', [$userId]);
}

/**
 * Step 1: create (or refresh) the deletion request and mail the confirmation link.
 *
 * @return array{ok:bool,error:string}
 */
function termination_request(int $userId): array
{
    $user = user_get($userId);
    if ($user === null || $user['role'] !== 'customer') {
        return ['ok' => false, 'error' => __('This account cannot be closed here.')];
    }
    $token = random_token(32);
    $expires = now() + (int) config('termination_token_hours', 24) * 3600;
    $pending = termination_pending($userId);
    if ($pending !== null) {
        db_exec('UPDATE deletion_requests SET token_hash = ?, token_expires = ? WHERE id = ?', [hash('sha256', $token), $expires, (int) $pending['id']]);
        $id = (int) $pending['id'];
    } else {
        $id = db_insert(
            'INSERT INTO deletion_requests (user_id, user_ref, status, token_hash, token_expires, created_at) VALUES (?,?,?,?,?,?)',
            [$userId, $userId, 'requested', hash('sha256', $token), $expires, now()]
        );
    }
    audit('termination.requested', 'deletion_request', $id, [], $userId);
    send_mail((string) $user['email'], __('Confirm that you want to end the service'), sprintf(
        "Hello %s,\n\nyou asked to end Imprint Protection and to delete all your data.\n\nTo confirm, open this link while you are signed in (valid for %d hours):\n\n%s\n\n"
        . "WARNING: after the confirmation your imprint page goes offline immediately and ALL your data (account, imprint profiles, address data, identity documents) is deleted irrevocably. "
        . "If you did not ask for this, ignore this message: nothing happens without your confirmation.",
        $user['name'],
        (int) config('termination_token_hours', 24),
        site_url('/portal/terminate.php?token=' . $token)
    ));
    notify_admins('Deletion request: customer ends the service', "A customer asked to end the service and to delete all data (customer #" . $userId . ").\nThe deletion runs automatically after the customer's confirmation, unless a legal hold is set.\nAdmin panel: " . site_url('/admin/requests.php'));
    return ['ok' => true, 'error' => ''];
}

/** Is $token the valid, unexpired confirmation token of the user's open deletion request? */
function termination_token_valid(int $userId, string $token): bool
{
    $r = termination_pending($userId);
    return $r !== null && $r['token_hash'] !== null && $r['token_expires'] !== null && (int) $r['token_expires'] >= now()
        && preg_match('/^[a-f0-9]{64}$/D', $token) === 1 && hash_equals((string) $r['token_hash'], hash('sha256', $token));
}

/**
 * Step 2: the customer confirmed (the caller has checked the session, the password, the checkbox and the token).
 * Runs the erasure. @return array{ok:bool,status:string,error:string} status: completed | on_hold | error
 */
function termination_confirm(int $userId): array
{
    $r = termination_pending($userId);
    if ($r === null) {
        return ['ok' => false, 'status' => 'error', 'error' => __('There is no open deletion request.')];
    }
    db_exec('UPDATE deletion_requests SET status = \'confirmed\', confirmed_at = ?, token_hash = NULL, token_expires = NULL WHERE id = ?', [now(), (int) $r['id']]);
    audit('termination.confirmed', 'deletion_request', (int) $r['id'], [], $userId);
    $res = account_erase($userId, 'customer');
    return ['ok' => $res['ok'], 'status' => $res['status'], 'error' => $res['error']];
}

/**
 * Erase ALL data of a customer irrevocably: published pages, identity documents (inbox + archive), imprint profiles and
 * revisions (incl. private address data), requests, tokens, the account. Audit log entries are pseudonymised (the log
 * itself stays as a record of what happened, without the person). A non-personal deletion_log entry is kept.
 *
 * @param string $initiator 'customer' | 'admin'
 * @return array{ok:bool,status:string,error:string,counts:array<string,int>}
 *         status: completed | on_hold | error (nothing is lost when 'error': it is safe to retry)
 */
function account_erase(int $userId, string $initiator): array
{
    $user = user_get($userId);
    $fail = static function (string $status, string $msg) {
        return ['ok' => false, 'status' => $status, 'error' => $msg, 'counts' => []];
    };
    if ($user === null || $user['role'] !== 'customer') {
        return $fail('error', __('Account not found.'));
    }
    $open = termination_pending($userId);
    if (!empty($user['legal_hold'])) {
        if ($open !== null) {
            db_exec('UPDATE deletion_requests SET status = \'on_hold\' WHERE id = ?', [(int) $open['id']]);
        }
        audit('erase.blocked_by_legal_hold', 'user', $userId, ['initiator' => $initiator], null);
        return $fail('on_hold', __('We are legally required to keep some of your data for now. We will delete it as soon as the reason no longer applies.'));
    }

    $counts = ['profiles' => 0, 'identity_files' => 0, 'requests' => 0, 'revisions' => 0];
    $email = (string) $user['email'];
    $name = (string) $user['name'];
    try {
        // 1. take every published page offline (aborts everything if a file cannot be removed)
        $profiles = db_all('SELECT id, status FROM profiles WHERE owner_id = ?', [$userId]);
        foreach ($profiles as $p) {
            imprint_unpublish((int) $p['id']);
            $counts['profiles']++;
        }
        // 2. identity documents: inbox + archive, found by uploader AND by profile ownership (idempotent; a failing or unavailable
        //    storage aborts before anything else is deleted)
        $files = db_all(
            'SELECT DISTINCT f.id FROM identity_files f JOIN identity_requests r ON r.id = f.request_id
              WHERE f.deleted_at IS NULL AND (r.user_id = ? OR r.profile_id IN (SELECT id FROM profiles WHERE owner_id = ?))',
            [$userId, $userId]
        );
        foreach ($files as $f) {
            vault_delete_file((int) $f['id']);
            $counts['identity_files']++;
        }
    } catch (Throwable $e) {
        error_log('account_erase(' . $userId . ') stopped: ' . $e->getMessage());
        return $fail('error', __('The deletion could not be completed because a storage system did not answer. Parts of your data may already have been deleted, the rest is still there. Please try again in a few minutes (it is safe to retry) or contact us.'));
    }

    $notifyRef = $open !== null ? (int) $open['id'] : 0;
    db_transaction(static function () use ($userId, &$counts, $notifyRef, $initiator, $email) {
        $counts['revisions'] = (int) (db_one('SELECT COUNT(*) AS n FROM revisions WHERE profile_id IN (SELECT id FROM profiles WHERE owner_id = ?)', [$userId])['n'] ?? 0);
        $counts['requests'] = db_exec('DELETE FROM service_requests WHERE user_id = ? OR lower(email) = lower(?)', [$userId, $email]);
        // Pseudonymise the audit log: no name/e-mail, no IP address and no details (page names) for rows about this person.
        foreach (db_all('SELECT id, slug FROM profiles WHERE owner_id = ?', [$userId]) as $pr) {
            audit_scrub_profile((int) $pr['id'], (string) $pr['slug'], $userId);
        }
        audit_scrub_profile(0, '', $userId);
        db_exec('UPDATE audit_log SET actor_label = ?, ip = \'\', meta_json = \'{}\' WHERE actor_id = ?', ['erased-user#' . $userId, $userId]);
        db_exec('UPDATE audit_log SET target_id = NULL, meta_json = \'{}\' WHERE target_type = \'user\' AND target_id = ?', [(string) $userId]);

        db_exec('DELETE FROM profiles WHERE owner_id = ?', [$userId]);            // revisions + identity rows cascade
        db_exec('DELETE FROM identity_requests WHERE user_id = ?', [$userId]);
        if ($notifyRef > 0) {
            db_exec('UPDATE deletion_requests SET status = \'completed\', completed_at = ?, user_id = NULL, counts_json = ?, token_hash = NULL, token_expires = NULL, hold_note = \'\' WHERE id = ?',
                [now(), json_str($counts), $notifyRef]);
        } else {
            db_exec('INSERT INTO deletion_requests (user_id, user_ref, status, created_at, completed_at, counts_json) VALUES (NULL,?,?,?,?,?)',
                [$userId, 'completed', now(), now(), json_str($counts)]);
        }
        db_exec('DELETE FROM users WHERE id = ?', [$userId]);                    // tokens cascade
        db_exec('INSERT INTO audit_log (at, actor_id, actor_label, action, target_type, target_id, meta_json, ip) VALUES (?,?,?,?,?,?,?,?)',
            [now(), null, 'system', 'account.erased', 'erased-user', (string) $userId, json_str(['initiator' => $initiator] + $counts), '']);
    });

    try {
        imprint_try_sitemap();                   // the erased pages must disappear from the public sitemap as well
    } catch (Throwable $e) {
        error_log('account_erase: sitemap refresh failed: ' . $e->getMessage());
    }
    // Last mail to the address that is now being forgotten (nothing about it is stored any more).
    send_mail($email, __('Your data has been deleted'), sprintf(
        "Hello %s,\n\nas requested, Imprint Protection has ended and all your data (account, imprint profiles, address data and identity documents) has been deleted irrevocably. Your imprint page is offline.\n\nThank you for using the service.",
        $name
    ));
    return ['ok' => true, 'status' => 'completed', 'error' => '', 'counts' => $counts];
}

/**
 * Set or release the legal hold of a customer (admin action; the caller audits it).
 * Releasing a hold runs a deletion that was waiting for it automatically (the customer had already confirmed).
 *
 * @return array{ok:bool,status:string,error:string,erased:bool} status: held | released | completed | error
 */
function legal_hold_set(int $userId, bool $on, string $note = ''): array
{
    $user = user_get($userId);
    if ($user === null || $user['role'] !== 'customer') {
        return ['ok' => false, 'status' => 'error', 'error' => __('Customer not found.'), 'erased' => false];
    }
    db_exec('UPDATE users SET legal_hold = ?, legal_hold_note = ? WHERE id = ?', [$on ? 1 : 0, $on ? mb_substr(trim($note), 0, 500, 'UTF-8') : '', $userId]);
    if ($on) {
        return ['ok' => true, 'status' => 'held', 'error' => '', 'erased' => false];
    }
    $waiting = db_one('SELECT id FROM deletion_requests WHERE user_id = ? AND status = \'on_hold\' AND confirmed_at IS NOT NULL ORDER BY id DESC LIMIT 1', [$userId]);
    if ($waiting === null) {
        return ['ok' => true, 'status' => 'released', 'error' => '', 'erased' => false];
    }
    audit('erase.after_hold_release', 'deletion_request', (int) $waiting['id'], []);
    $res = account_erase($userId, 'customer');
    return ['ok' => $res['ok'], 'status' => $res['ok'] ? 'completed' : 'error', 'error' => $res['error'], 'erased' => $res['ok']];
}
