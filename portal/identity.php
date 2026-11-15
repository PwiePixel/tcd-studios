<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

// Identity check of one imprint profile: status + upload form. Uploading/removing is done by identity-upload.php.
$profile = portal_owned_profile(get_int('id'), $user);      // 404 for foreign ids
render_view('portal/identity', portal_identity_view_vars($profile));
