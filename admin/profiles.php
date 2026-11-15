<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$status = get_str('status', 20);
if (!in_array($status, admin_profile_statuses(), true)) {
    $status = '';
}
$q = get_str('q', 100);

render_view('admin/profiles', [
    'title' => __('Imprints'),
    'profiles' => profiles_list($status, $q),
    'status' => $status,
    'q' => $q,
]);
