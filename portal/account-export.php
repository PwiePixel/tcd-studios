<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

require_post();
csrf_verify();

$json = json_encode(portal_export_data($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    redirect('/portal/account.php', ['error' => __('The export failed. Please try again later.')]);
}
audit('account.export', 'user', (int) $user['id']);

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="tcd-studio-my-data-' . gmdate('Y-m-d') . '.json"');
header('Content-Length: ' . strlen($json));
echo $json;
