<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

$errors = [];
$nameValue = (string) $user['name'];

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 30);
    if ($action === 'name') {
        $nameValue = post_str('name', 200);
        if (!v_len($nameValue, 2, 80) || !v_oneline($nameValue)) {
            $errors['name'] = __('Please enter your name (2–80 characters).');
        } else {
            portal_user_set_name((int) $user['id'], $nameValue);
            redirect('/portal/account.php', ['ok' => __('Your name was updated.')]);
        }
    } elseif ($action === 'password') {
        $new = portal_post_password('new_password');
        if ($new !== portal_post_password('new_password2')) {
            $errors['new_password2'] = __('The two passwords do not match.');
        } else {
            $failure = auth_change_password((int) $user['id'], portal_post_password('current_password'), $new);
            if ($failure === null) {
                session_regenerate_id(true);
                redirect('/portal/account.php', ['ok' => __('Your password was changed.')]);
            }
            $errors[$failure === __('The current password is wrong.') ? 'current_password' : 'new_password'] = $failure;
        }
    } else {
        redirect('/portal/account.php', ['error' => __('Unknown action.')]);
    }
}

$requests = [];
$deletionOpen = false;
foreach (requests_by_user((int) $user['id']) as $request) {
    $request['service_label'] = portal_service_label((string) $request['service_slug']);
    $requests[] = $request;
    if ($request['service_slug'] === 'account-deletion' && in_array($request['status'], ['new', 'in_progress'], true)) {
        $deletionOpen = true;
    }
}

render_view('portal/account', [
    'title'        => __('Account'),
    'nameValue'    => $nameValue,
    'errors'       => $errors,
    'requests'     => $requests,
    'deletionOpen' => $deletionOpen,
]);
