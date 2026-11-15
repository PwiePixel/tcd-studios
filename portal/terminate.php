<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();

// The link from the confirmation mail needs a signed-in customer. There is deliberately no "next" parameter: after
// signing in, the customer opens the link from the mail again.
$token = is_post() ? post_str('token', 100) : get_str('token', 100);
if ($token !== '' && auth_current_user() === null) {
    redirect('/portal/login.php', ['error' => __('Please sign in first. Then open the link from your e-mail again.')]);
}
$user = auth_require('customer');
$uid = (int) $user['id'];
$self = '/portal/terminate.php';

$errors = [];
$formError = '';
$holdMessage = '';

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 20);
    $pending = termination_pending($uid);

    if ($action === 'request') {
        // Step 1: creates the deletion request in our system and mails the confirmation link.
        if ($pending !== null && $pending['status'] === 'on_hold') {
            redirect($self, ['error' => __('Your deletion request is on hold for legal reasons. We will delete your data as soon as the reason no longer applies.')]);
        }
        if (!rate_limit_hit('terminate-request:' . $uid, [5, 3600])) {
            redirect($self, ['error' => __('Too many requests. Please try again later.')]);
        }
        $result = termination_request($uid);
        if (!$result['ok']) {
            redirect($self, ['error' => $result['error'] !== '' ? $result['error'] : __('The request could not be created. Please try again later.')]);
        }
        redirect($self, ['ok' => __('We sent a confirmation link to your e-mail address. Nothing is deleted until you open the link and confirm.')]);
    }

    if ($action === 'cancel') {
        if (portal_termination_cancel($uid)) {
            redirect($self, ['ok' => __('Your request was withdrawn. Nothing was deleted and your service continues.')]);
        }
        redirect($self, ['error' => __('There is no request that can be withdrawn.')]);
    }

    if ($action !== 'confirm') {
        redirect($self, ['error' => __('Unknown action.')]);
    }

    // Step 2: the strong confirmation (checkbox + password + the token from the mail).
    $retry = $pending !== null && $pending['status'] === 'confirmed';       // an earlier attempt failed after the link was used
    if ($pending === null || (!termination_token_valid($uid, $token) && !$retry)) {
        $formError = __('This confirmation link is not valid any more (it may have expired or been used). Please request a new one below.');
        $token = '';
    } else {
        if (!post_bool('confirm')) {
            $errors['confirm'] = __('Please tick the box to confirm that the service ends and that your data is deleted irrevocably.');
        }
        $password = portal_post_password('password');
        if ($password === '') {
            $errors['password'] = __('Please enter your current password.');
        }
        if ($errors === []) {
            if (!rate_limit_hit('terminate:' . $uid, [5, 900])) {
                $errors['password'] = __('Too many attempts. Please try again in a few minutes.');
            } elseif (!password_verify($password, (string) $user['password_hash'])) {
                $errors['password'] = __('The password is wrong.');
            }
        }
        if ($errors === []) {
            $result = termination_confirm($uid);
            if ($result['status'] === 'completed') {
                // The account no longer exists: end the session without writing a new audit row about the erased user.
                unset($_SESSION['uid']);
                auth_logout();
                redirect('/portal/terminated.php');
            }
            if ($result['status'] === 'on_hold') {
                $holdMessage = $result['error'];
            } else {
                $formError = $result['error'] !== '' ? $result['error'] : __('The deletion could not be completed. Nothing else was deleted; please try again in a few minutes.');
                error_log('portal: termination of user #' . $uid . ' failed: ' . $result['error']);
            }
        }
    }
}

// What to show now.
$user = auth_current_user() ?? $user;
$pending = termination_pending($uid);
$state = portal_termination_state($user);
$tokenValid = $token !== '' && termination_token_valid($uid, $token);
if ($pending === null) {
    $mode = 'start';
} elseif ($tokenValid) {
    $mode = 'confirm';
} elseif ($pending['status'] === 'confirmed') {
    $mode = 'retry';
} elseif ($pending['status'] === 'on_hold') {
    $mode = 'hold';
} elseif ($token !== '') {
    $mode = 'invalid_link';
} else {
    $mode = 'requested';
}

render_view('portal/terminate', [
    'title'       => __('End the service'),
    'mode'        => $mode,
    'state'       => $state,
    'token'       => $tokenValid ? $token : '',
    'errors'      => $errors,
    'formError'   => $formError,
    'holdMessage' => $holdMessage,
    'effects'     => portal_termination_effects(),
    'email'       => (string) $user['email'],
    'linkHours'   => (int) config('termination_token_hours', 24),
]);
