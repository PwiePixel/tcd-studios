<?php
declare(strict_types=1);

/** The signed-in admin's own account: password and two-factor authentication. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');
$uid = (int) $admin['id'];
$self = '/admin/account.php';
$errors = [];

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 30);

    if ($action === 'change_password') {
        if (!rate_limit_hit('pwchange:' . $uid, [6, 900])) {
            $errors['password'] = __('Too many attempts. Please try again in a few minutes.');
        } elseif (!safe_equals(admin_post_raw('new_password'), admin_post_raw('new_password2'))) {
            $errors['password'] = __('The two new passwords are not identical.');
        } else {
            $err = auth_change_password($uid, admin_post_raw('current_password'), admin_post_raw('new_password'));
            if ($err === null) {
                session_regenerate_id(true);
                redirect($self, ['ok' => __('Your password was changed.')]);
            }
            $errors['password'] = $err;
        }
    } elseif ($action === 'totp_start') {
        if (admin_totp_active($admin)) {
            redirect($self, ['error' => __('Two-factor authentication is already on.')]);
        }
        $_SESSION['totp_setup'] = ['secret' => totp_generate_secret(), 'at' => now()];
        redirect($self . '#totp');
    } elseif ($action === 'totp_cancel') {
        unset($_SESSION['totp_setup']);
        redirect($self);
    } elseif ($action === 'totp_enable') {
        $setup = isset($_SESSION['totp_setup']) && is_array($_SESSION['totp_setup']) ? $_SESSION['totp_setup'] : [];
        $secret = (string) ($setup['secret'] ?? '');
        if ($secret === '' || now() - (int) ($setup['at'] ?? 0) > 1800 || admin_totp_active($admin)) {
            unset($_SESSION['totp_setup']);
            redirect($self, ['error' => __('The setup has expired. Please start again.')]);
        }
        if (!admin_totp_rate_ok($uid)) {
            $errors['totp'] = __('Too many attempts. Please try again in a few minutes.');
        } else {
            $step = totp_verify($secret, post_str('code', 20));
            if ($step === null) {
                $errors['totp'] = __('The code is wrong. Check the time on your phone and try again.');
            } else {
                $codes = admin_totp_enable($uid, $secret, $step);
                unset($_SESSION['totp_setup']);
                $_SESSION['recovery_show'] = $codes;
                audit('admin.totp_enabled', 'user', $uid);
                redirect($self . '#recovery', ['ok' => __('Two-factor authentication is now on.')]);
            }
        }
    } elseif ($action === 'totp_disable' || $action === 'totp_regen') {
        if (!admin_totp_active($admin)) {
            redirect($self, ['error' => __('Two-factor authentication is not on.')]);
        }
        if (!admin_totp_rate_ok($uid)) {
            $errors['totp_manage'] = __('Too many attempts. Please try again in a few minutes.');
        } elseif (!password_verify(admin_post_raw('password'), (string) $admin['password_hash'])) {
            $errors['totp_manage'] = __('The password is wrong.');
        } elseif (admin_totp_check($admin, post_str('code', 40)) === '') {
            $errors['totp_manage'] = __('The code is wrong or has already been used.');
        } elseif ($action === 'totp_disable') {
            admin_totp_disable($uid);
            audit('admin.totp_disabled', 'user', $uid);
            redirect($self, ['ok' => __('Two-factor authentication was switched off.')]);
        } else {
            $_SESSION['recovery_show'] = admin_recovery_generate($uid);
            audit('admin.totp_recovery_regenerated', 'user', $uid);
            redirect($self . '#recovery', ['ok' => __('New recovery codes were created. The old ones no longer work.')]);
        }
    } else {
        abort(400, __('Unknown action.'));
    }
}

// Recovery codes are shown exactly once.
$recoveryCodes = [];
if (!empty($_SESSION['recovery_show']) && is_array($_SESSION['recovery_show'])) {
    $recoveryCodes = array_values(array_filter($_SESSION['recovery_show'], 'is_string'));
}
unset($_SESSION['recovery_show']);

$setup = isset($_SESSION['totp_setup']) && is_array($_SESSION['totp_setup']) ? $_SESSION['totp_setup'] : [];
$setupSecret = !admin_totp_active($admin) && now() - (int) ($setup['at'] ?? 0) <= 1800 ? (string) ($setup['secret'] ?? '') : '';
$issuer = (string) config('site_name', 'TCD-Studio/Agency') . ' Admin';

render_view('admin/account', [
    'title' => __('My account'),
    'admin' => $admin,
    'errors' => $errors,
    'totpActive' => admin_totp_active($admin),
    'recoveryCodes' => $recoveryCodes,
    'recoveryLeft' => admin_recovery_count($uid),
    'setupSecret' => $setupSecret,
    'setupUri' => $setupSecret !== '' ? totp_uri($setupSecret, (string) $admin['email'], $issuer) : '',
]);
