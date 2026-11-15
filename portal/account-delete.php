<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
auth_require('customer');

// The old manual deletion request is gone: ending the service is done on terminate.php (request, mailed link, confirmation).
redirect('/portal/terminate.php');
