<?php
declare(strict_types=1);

/** Admin accounts: list, create, disable/enable, reset 2FA. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$errors = [];
$values = ['name' => '', 'email' => ''];

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 30);
    $targetId = post_int('user_id');
    $target = $targetId > 0 ? user_get($targetId) : null;

    if ($action === 'create') {
        $values['name'] = post_str('name', 80);
        $values['email'] = mb_strtolower(post_str('email', 254), 'UTF-8');
        $password = admin_post_raw('password');
        if (!v_len($values['name'], 2, 80) || !v_oneline($values['name'])) {
            $errors['name'] = __('Please enter a name (2–80 characters).');
        }
        if (!v_email($values['email'])) {
            $errors['email'] = __('Please enter a valid e-mail address.');
        } elseif (user_get_by_email($values['email']) !== null) {
            $errors['email'] = __('This e-mail address is already in use.');
        }
        $pwError = auth_password_error($password, $values['email']);
        if ($pwError !== null) {
            $errors['password'] = $pwError;
        }
        if (!$errors) {
            try {
                $newId = auth_create_admin($values['email'], $values['name'], $password);
                audit('admin.create', 'user', $newId);
                redirect('/admin/admins.php', ['ok' => __('The admin account was created. Pass the initial password on securely; the new admin should change it in "My account" right after the first sign-in.')]);
            } catch (InvalidArgumentException $e) {
                $errors['form'] = __('The account could not be created. Please check your input.');
            }
        }
    } elseif ($target === null || $target['role'] !== 'admin') {
        abort(404);
    } elseif ($action === 'disable') {
        if ((int) $target['id'] === (int) $admin['id']) {
            redirect('/admin/admins.php', ['error' => __('You cannot disable your own account.')]);
        }
        $ok = db_transaction(static function () use ($target) {
            if ($target['status'] === 'active' && admin_active_admin_count() <= 1) {
                return false;
            }
            user_set_status((int) $target['id'], 'disabled');
            return true;
        });
        if (!$ok) {
            redirect('/admin/admins.php', ['error' => __('The last active admin cannot be disabled.')]);
        }
        audit('admin.disable', 'user', (int) $target['id']);
        redirect('/admin/admins.php', ['ok' => __('The admin was disabled and is signed out.')]);
    } elseif ($action === 'enable') {
        if ($target['status'] !== 'disabled') {
            redirect('/admin/admins.php', ['error' => __('The account is not disabled.')]);
        }
        user_set_status((int) $target['id'], 'active');
        audit('admin.enable', 'user', (int) $target['id']);
        redirect('/admin/admins.php', ['ok' => __('The admin was enabled.')]);
    } elseif ($action === 'reset_2fa') {
        if ((int) $target['id'] === (int) $admin['id']) {
            redirect('/admin/admins.php', ['error' => __('Use "My account" to change your own two-factor settings.')]);
        }
        admin_totp_disable((int) $target['id']);
        audit('admin.totp_reset', 'user', (int) $target['id']);
        redirect('/admin/admins.php', ['ok' => __('Two-factor authentication was switched off for this admin.')]);
    } else {
        abort(400, __('Unknown action.'));
    }
}

render_view('admin/admins', [
    'title' => __('Admins'),
    'admins' => users_list('admin'),
    'errors' => $errors,
    'values' => $values,
    'self' => (int) $admin['id'],
    'activeCount' => admin_active_admin_count(),
]);
