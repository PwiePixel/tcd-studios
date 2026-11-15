<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$q = get_str('q', 100);
$status = get_str('status', 20);
if (!in_array($status, ['pending', 'active', 'disabled'], true)) {
    $status = '';
}
$customers = users_list('customer', $q);
if ($status !== '') {
    $customers = array_values(array_filter($customers, static function (array $u) use ($status): bool {
        return $u['status'] === $status;
    }));
}

render_view('admin/customers', ['title' => __('Customers'), 'customers' => $customers, 'q' => $q, 'status' => $status]);
