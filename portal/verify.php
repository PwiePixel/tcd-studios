<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();

$token = get_str('token', 128);
$ok = $token !== '' && auth_verify_email($token);

render_view('portal/verify', [
    'title' => __('Confirm your e-mail address'),
    'ok'    => $ok,
]);
