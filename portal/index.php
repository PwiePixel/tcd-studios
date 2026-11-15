<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();

redirect(auth_current_user() !== null ? '/portal/dashboard.php' : '/portal/login.php');
