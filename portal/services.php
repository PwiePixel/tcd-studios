<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

$sent = [];
foreach (requests_by_user((int) $user['id']) as $request) {
    if (!isset($sent[$request['service_slug']])) {
        $sent[$request['service_slug']] = $request;       // newest first
    }
}

render_view('portal/services', [
    'title'    => __('Services'),
    'services' => services_all(),
    'sent'     => $sent,
]);
