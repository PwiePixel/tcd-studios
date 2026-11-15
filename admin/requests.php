<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$status = get_str('status', 20);
if (!in_array($status, admin_request_statuses(), true)) {
    $status = '';
}
$service = get_str('service', 60);
if (preg_match('/^[a-z0-9-]{1,60}$/D', $service) !== 1) {
    $service = '';
}
$slugs = [];
foreach (db_all('SELECT slug AS s FROM services UNION SELECT DISTINCT service_slug AS s FROM service_requests ORDER BY s') as $row) {
    $slugs[] = (string) $row['s'];
}

// Second tab: deletion requests (customers ending the service), see erase.php.
$tab = get_str('tab', 20) === 'deletions' ? 'deletions' : 'requests';
$dStatus = get_str('dstatus', 20);
if (!in_array($dStatus, admin_deletion_statuses(), true)) {
    $dStatus = '';
}

render_view('admin/requests', [
    'title' => __('Requests'),
    'tab' => $tab,
    'requests' => $tab === 'requests' ? requests_list($status, $service) : [],
    'status' => $status,
    'service' => $service,
    'slugs' => $slugs,
    'deletions' => $tab === 'deletions' ? admin_deletion_requests($dStatus) : [],
    'dStatus' => $dStatus,
    'deletionCounts' => admin_deletion_counts(),
]);
