<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
portal_guard();
send_security_headers();

// Final page after the account was erased: public, static, no session and no personal data.
render_view('portal/terminated', ['title' => __('Service ended')]);
