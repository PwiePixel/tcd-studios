<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

$slug = get_str('service', 60);
$service = $slug !== '' ? service_get($slug) : null;
if ($service === null || (int) $service['requestable'] !== 1 || $service['availability'] === 'hidden') {
    abort(404, __('This service does not exist.'));
}
if ($slug === 'imprint-protection') {
    redirect('/portal/profile-new.php');          // has its own flow
}
$isShop = $slug === 'shops';
$ranges = portal_revenue_ranges();

$values = [
    'name' => (string) $user['name'], 'email' => (string) $user['email'], 'message' => '',
    'shop_name' => '', 'shop_url' => '', 'country' => '', 'monthly_revenue' => '', 'payment_provider' => '',
];
$errors = [];

if (is_post()) {
    csrf_verify();
    $values['name'] = post_str('name', 200);
    $values['email'] = mb_strtolower(post_str('email', 300), 'UTF-8');
    $values['message'] = post_str('message', 4000);
    if ($isShop) {
        foreach (['shop_name', 'shop_url', 'country', 'monthly_revenue', 'payment_provider'] as $field) {
            $values[$field] = post_str($field, 400);
        }
    }

    if (!v_len($values['name'], 2, 80) || !v_oneline($values['name'])) {
        $errors['name'] = __('Please enter your name (2–80 characters).');
    }
    if (!v_email($values['email'])) {
        $errors['email'] = __('Please enter a valid e-mail address.');
    }
    if (!v_len($values['message'], $isShop ? 0 : 10, 2000)) {
        $errors['message'] = $isShop ? __('The message may have at most 2000 characters.') : __('Please write 10–2000 characters.');
    }
    if ($isShop) {
        if (!v_len($values['shop_name'], 2, 80) || !v_oneline($values['shop_name'])) {
            $errors['shop_name'] = __('Please enter the name of your shop (2–80 characters).');
        }
        if (!v_https_url($values['shop_url'])) {
            $errors['shop_url'] = __('Please enter the complete https:// address of your shop.');
        }
        if (!v_len($values['country'], 2, 60) || !v_oneline($values['country'])) {
            $errors['country'] = __('Please enter the country of your business.');
        }
        if (!isset($ranges[$values['monthly_revenue']])) {
            $errors['monthly_revenue'] = __('Please choose an option.');
        }
        if (!v_len($values['payment_provider'], 0, 80) || !v_oneline($values['payment_provider'])) {
            $errors['payment_provider'] = __('Please use at most 80 characters.');
        }
    }

    if ($errors === []) {
        if (!rate_limit_hit('request:ip:' . client_ip(), config('rate.request_ip'))) {
            $errors['form'] = __('Too many requests from your network. Please try again later.');
        } else {
            $payload = ['message' => $values['message']];
            if ($isShop) {
                $payload += [
                    'shop_name' => $values['shop_name'], 'shop_url' => $values['shop_url'], 'country' => $values['country'],
                    'monthly_revenue' => $ranges[$values['monthly_revenue']], 'payment_provider' => $values['payment_provider'],
                ];
            }
            $rid = request_create($slug, (int) $user['id'], $values['name'], $values['email'], $payload);
            $lines = ['New service request (#' . $rid . '): ' . $service['name'], '', 'Name:   ' . $values['name'], 'E-mail: ' . $values['email'], 'Account: ' . $user['email']];
            if ($isShop) {
                $lines[] = 'Shop:    ' . $values['shop_name'] . ' (' . $values['shop_url'] . ')';
                $lines[] = 'Country: ' . $values['country'];
                $lines[] = 'Expected monthly revenue: ' . $ranges[$values['monthly_revenue']];
                $lines[] = 'Current payment provider: ' . ($values['payment_provider'] !== '' ? $values['payment_provider'] : '-');
            }
            $lines[] = '';
            $lines[] = $values['message'] !== '' ? $values['message'] : '(no message)';
            $lines[] = '';
            $lines[] = 'Admin panel: ' . site_url('/admin/requests.php');
            send_mail((string) config('mail.requests'), 'Service request: ' . $service['name'], implode("\n", $lines), $values['email']);
            audit('service.request', 'request', $rid, ['service' => $slug]);
            redirect('/portal/dashboard.php', ['ok' => __('Thank you! We received your request and will get back to you by e-mail.')]);
        }
    }
}

render_view('portal/request', [
    'title'   => sprintf(__('Request: %s'), (string) $service['name']),
    'service' => $service,
    'isShop'  => $isShop,
    'ranges'  => $ranges,
    'values'  => $values,
    'errors'  => $errors,
]);
