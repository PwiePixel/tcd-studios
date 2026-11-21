<?php
declare(strict_types=1);

/** Old address of the admin sign-in: the page now lives at /admin/ (GET redirects there, POSTs of old forms still work). */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');

if (auth_current_user() !== null) {
    redirect('/admin/');
}
if (!is_post()) {
    header('Location: /admin/', true, 301);
    exit;
}
admin_login_page();
