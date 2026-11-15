<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$stats = admin_dashboard_stats();
$pending = array_slice(revisions_submitted(), 0, 6);
$requests = array_slice(db_all('SELECT * FROM service_requests ORDER BY created_at DESC LIMIT 6'), 0, 6);
$problems = admin_system_problem_count(admin_system_checks());

render_view('admin/index', [
    'title' => __('Dashboard'),
    'admin' => $admin,
    'stats' => $stats,
    'pending' => $pending,
    'requests' => $requests,
    'problems' => $problems,
    'noTotp' => !admin_totp_active($admin),
]);
