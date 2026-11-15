<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
require_post();
if (auth_current_user() === null) {
    redirect('/admin/login.php');
}
csrf_verify();
auth_logout();
redirect('/admin/login.php', ['ok' => __('You have been signed out.')]);
