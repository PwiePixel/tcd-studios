<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');

$error = '';
$sent = false;
if (is_post()) {
    csrf_verify();
    $res = auth_request_reset(post_str('email', 254), 'admin');
    if ($res['ok']) {
        $sent = true;
    } else {
        $error = $res['error'];
    }
}
render_view('admin/forgot', ['title' => __('Forgot password'), 'error' => $error, 'sent' => $sent]);
