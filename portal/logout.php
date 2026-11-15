<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();

if (!is_post()) {
    redirect('/portal/');
}
csrf_verify();
auth_logout();
redirect('/portal/login.php', ['ok' => __('You have been signed out.')]);
