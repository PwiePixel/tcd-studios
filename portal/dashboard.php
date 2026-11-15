<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

$rows = [];
foreach (profiles_by_owner((int) $user['id']) as $profile) {
    $rows[] = portal_profile_summary($profile);
}
[$count, $max] = portal_profile_limit((int) $user['id']);
$requests = [];
foreach (requests_by_user((int) $user['id']) as $request) {
    $request['service_label'] = portal_service_label((string) $request['service_slug']);
    $requests[] = $request;
}

render_view('portal/dashboard', [
    'title'    => __('Dashboard'),
    'rows'     => $rows,
    'canCreate' => $count < $max,
    'maxProfiles' => $max,
    'requests' => $requests,
    'termsAccepted' => portal_terms_accepted($user),
    'termsInfo' => portal_terms_info($user),
    'termination' => portal_termination_state($user),
]);
