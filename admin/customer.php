<?php
declare(strict_types=1);

/** One customer account: profiles, requests and account actions. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$id = admin_id();
$customer = user_get($id);
if ($customer === null || $customer['role'] !== 'customer') {
    abort(404);
}
$self = '/admin/customer.php?id=' . $id;

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 30);
    $status = (string) $customer['status'];

    if ($action === 'disable') {
        if ($status === 'disabled') {
            redirect($self, ['error' => __('The account is already disabled.')]);
        }
        user_set_status($id, 'disabled');
        audit('customer.disable', 'user', $id);
        redirect($self, ['ok' => __('The account was disabled. The customer can no longer sign in; published imprints stay online.')]);
    }
    if ($action === 'enable') {
        if ($status !== 'disabled') {
            redirect($self, ['error' => __('The account is not disabled.')]);
        }
        $new = $customer['email_verified_at'] !== null ? 'active' : 'pending';
        user_set_status($id, $new);
        audit('customer.enable', 'user', $id, ['status' => $new]);
        redirect($self, ['ok' => $new === 'active' ? __('The account was enabled.') : __('The account was enabled but still needs e-mail confirmation.')]);
    }
    if ($action === 'activate') {
        if ($status !== 'pending') {
            redirect($self, ['error' => __('Only pending accounts can be activated manually.')]);
        }
        db_transaction(static function () use ($id) {
            db_exec('UPDATE users SET status = \'active\', email_verified_at = ? WHERE id = ? AND status = \'pending\'', [now(), $id]);
            db_exec('DELETE FROM tokens WHERE user_id = ? AND type = \'verify\'', [$id]);
        });
        audit('customer.activate', 'user', $id);
        redirect($self, ['ok' => __('The account was activated.')]);
    }
    if ($action === 'resend_verification') {
        if ($status !== 'pending') {
            redirect($self, ['error' => __('Only pending accounts need a confirmation e-mail.')]);
        }
        if (!rate_limit_hit('admin_resend:' . $id, [3, 3600])) {
            redirect($self, ['error' => __('Too many mails for this account. Please try again later.')]);
        }
        $token = auth_create_token($id, 'verify', 48 * 3600);
        $sent = admin_mail_user($customer, __('Confirm your e-mail address'), "please confirm your e-mail address to activate your account:\n\n"
            . site_url('/portal/verify.php?token=' . $token) . "\n\nThe link is valid for 48 hours.");
        audit('customer.resend_verification', 'user', $id, ['sent' => $sent]);
        redirect($self, $sent ? ['ok' => __('The confirmation e-mail was sent.')] : ['error' => __('The e-mail could not be sent.')]);
    }
    if ($action === 'legal_hold') {
        $hold = post_bool('hold');
        $note = post_str('hold_note', 500);
        if ($hold && $note === '') {
            redirect($self, ['error' => __('Please write down why the legal hold is set (for example "open claim" or "authority request"). Do not copy personal data into the note.')]);
        }
        if ($hold === !empty($customer['legal_hold']) && ($hold === false || $note === (string) $customer['legal_hold_note'])) {
            redirect($self, ['error' => __('Nothing changed.')]);
        }
        db_transaction(static function () use ($id, $hold, $note) {
            db_exec('UPDATE users SET legal_hold = ?, legal_hold_note = ? WHERE id = ?', [$hold ? 1 : 0, $hold ? $note : '', $id]);
            // A deletion request that waits for the hold is released again (and parked while the hold is set).
            db_exec('UPDATE deletion_requests SET status = ? WHERE user_id = ? AND status = ?', $hold ? ['on_hold', $id, 'confirmed'] : ['confirmed', $id, 'on_hold']);
        });
        // The reason is stored with the account (erased with it); the audit log only records that and when.
        audit($hold ? 'customer.legal_hold_set' : 'customer.legal_hold_cleared', 'user', $id, ['note_length' => $hold ? str_len($note) : 0]);
        $waiting = termination_pending($id) !== null;
        redirect($self, ['ok' => $hold
            ? __('The legal hold is set. Automatic erasure of this customer\'s data is blocked until you remove it.')
            : ($waiting
                ? __('The legal hold was removed. The customer\'s deletion request is waiting: use "Erase now" to complete it.')
                : __('The legal hold was removed.'))]);
    }
    if ($action === 'erase') {
        if (mb_strtolower(post_str('confirm_email', 254), 'UTF-8') !== mb_strtolower((string) $customer['email'], 'UTF-8')) {
            redirect($self, ['error' => __('The e-mail address you typed does not match. Nothing was deleted.')]);
        }
        if (!rate_limit_hit('admin_erase:' . (int) $admin['id'], [10, 3600])) {
            redirect($self, ['error' => __('Too many erase attempts. Please try again later.')]);
        }
        $res = account_erase($id, 'admin');
        if ($res['status'] === 'completed') {
            // The account is gone: the audit entry references the erased id only, never the e-mail address.
            audit('customer.erase_now', 'erased-user', (string) $id, ['counts' => $res['counts']]);
            $c = $res['counts'];
            redirect('/admin/customers.php', ['ok' => sprintf(
                __('The customer was erased: %d imprint(s), %d revision(s), %d identity file(s) and %d request(s) were deleted.'),
                (int) ($c['profiles'] ?? 0), (int) ($c['revisions'] ?? 0), (int) ($c['identity_files'] ?? 0), (int) ($c['requests'] ?? 0)
            )]);
        }
        audit('customer.erase_failed', 'user', $id, ['status' => $res['status']]);
        redirect($self, ['error' => $res['status'] === 'on_hold'
            ? __('Not erased: a legal hold is set for this customer. Remove the hold first.')
            : $res['error']]);
    }
    abort(400, __('Unknown action.'));
}

render_view('admin/customer', [
    'title' => $customer['name'] !== '' ? (string) $customer['name'] : (string) $customer['email'],
    'customer' => $customer,
    'profiles' => profiles_by_owner($id),
    'requests' => requests_by_user($id),
    'identityRequests' => admin_identity_requests_of_user($id),
    'deletion' => termination_pending($id),
]);
