<?php
declare(strict_types=1);

/** First-run setup: creates the first admin account. Only works while no admin exists; needs the setup token. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');

if (admin_exists()) {
    abort(404);
}

$token = admin_setup_token();
$errors = [];
$values = ['name' => '', 'email' => ''];

if ($token !== '' && is_post()) {
    csrf_verify();
    $values['name'] = post_str('name', 80);
    $values['email'] = mb_strtolower(post_str('email', 254), 'UTF-8');
    $given = post_str('token', 200);
    $password = admin_post_raw('password');

    if (!rate_limit_hit('setup:ip:' . client_ip(), [6, 900])) {
        $errors['form'] = __('Too many attempts. Please try again in a few minutes.');
    } elseif (!safe_equals($token, $given)) {
        $errors['token'] = __('The setup token is wrong.');
    } else {
        if (!v_len($values['name'], 2, 80) || !v_oneline($values['name'])) {
            $errors['name'] = __('Please enter your name (2–80 characters).');
        }
        if (!v_email($values['email'])) {
            $errors['email'] = __('Please enter a valid e-mail address.');
        }
        $pwError = auth_password_error($password, $values['email']);
        if ($pwError !== null) {
            $errors['password'] = $pwError;
        } elseif (!safe_equals($password, admin_post_raw('password2'))) {
            $errors['password2'] = __('The two passwords are not identical.');
        }
        if (!$errors) {
            try {
                $uid = db_transaction(static function () use ($values, $password) {
                    if (admin_exists()) {
                        throw new RuntimeException('exists', ADMIN_STATE_CONFLICT);
                    }
                    return auth_create_admin($values['email'], $values['name'], $password);
                });
                @unlink(admin_setup_token_file());
                audit('admin.setup_completed', 'user', $uid, [], $uid);
                redirect('/admin/login.php', ['ok' => __('The admin account was created. Please sign in.')]);
            } catch (RuntimeException $e) {
                abort(404);
            } catch (InvalidArgumentException $e) {
                $errors['form'] = __('The account could not be created. Please check your input.');
            }
        }
    }
}

render_view('admin/setup', ['title' => __('First-run setup'), 'errors' => $errors, 'values' => $values, 'tokenReady' => $token !== '']);
