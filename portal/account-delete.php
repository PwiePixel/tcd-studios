<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

require_post();
csrf_verify();

if (!post_bool('confirm')) {
    redirect('/portal/account.php', ['error' => __('Please tick the box to confirm your request.')]);
}
foreach (requests_by_user((int) $user['id']) as $request) {
    if ($request['service_slug'] === 'account-deletion' && in_array($request['status'], ['new', 'in_progress'], true)) {
        redirect('/portal/account.php', ['ok' => __('Your deletion request is already with our team.')]);
    }
}

// Nothing is deleted automatically: a team member processes the request (and may contact the customer).
$rid = request_create('account-deletion', (int) $user['id'], (string) $user['name'], (string) $user['email'], ['message' => 'Please delete my account and my imprint profiles.']);
send_mail(
    (string) config('mail.requests'),
    'Account deletion requested',
    "A customer asked for the deletion of the account.\n\nName:   " . $user['name'] . "\nE-mail: " . $user['email'] . "\n\nProcess it in the admin panel: " . site_url('/admin/requests.php'),
    (string) $user['email']
);
audit('account.deletion_requested', 'request', $rid, []);
redirect('/portal/account.php', ['ok' => __('We received your request. Our team will process it and contact you. Nothing has been deleted yet.')]);
