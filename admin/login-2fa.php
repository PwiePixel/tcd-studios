<?php
declare(strict_types=1);

/** Second login step: 6-digit authenticator code or a one-time recovery code. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');

if (auth_current_user() !== null) {
    redirect('/admin/');
}
$pending = isset($_SESSION['totp_pending']) && is_array($_SESSION['totp_pending']) ? $_SESSION['totp_pending'] : [];
$uid = (int) ($pending['uid'] ?? 0);
if ($uid <= 0 || now() - (int) ($pending['at'] ?? 0) > 600) {
    unset($_SESSION['totp_pending']);
    redirect('/admin/', ['error' => __('Please sign in again.')]);
}
$user = user_get($uid);
if (!$user || $user['role'] !== 'admin' || $user['status'] !== 'active' || !admin_totp_active($user)) {
    unset($_SESSION['totp_pending']);
    redirect('/admin/', ['error' => __('Please sign in again.')]);
}

$error = '';
if (is_post()) {
    csrf_verify();
    if (!admin_totp_rate_ok($uid)) {
        $error = __('Too many attempts. Please try again in a few minutes.');
    } else {
        $how = admin_totp_check($user, post_str('code', 40));
        if ($how !== '') {
            rate_limit_clear('totp:user:' . $uid);
            unset($_SESSION['totp_pending']);
            auth_login_complete($user);
            if ($how === 'recovery') {
                audit('admin.totp_recovery_used', 'user', $uid, ['remaining' => admin_recovery_count($uid)], $uid);
                flash_add('error', sprintf(__('You signed in with a recovery code. %d recovery code(s) left – generate new ones in your account settings.'), admin_recovery_count($uid)));
            }
            redirect('/admin/');
        }
        audit('admin.totp_failed', 'user', $uid, [], $uid);
        $error = __('The code is wrong or has already been used.');
    }
}

render_view('admin/login-2fa', ['title' => __('Two-factor authentication'), 'error' => $error]);
