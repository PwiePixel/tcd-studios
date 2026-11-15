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
    abort(400, __('Unknown action.'));
}

render_view('admin/customer', [
    'title' => $customer['name'] !== '' ? (string) $customer['name'] : (string) $customer['email'],
    'customer' => $customer,
    'profiles' => profiles_by_owner($id),
    'requests' => requests_by_user($id),
]);
