<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

render_view('admin/reviews', ['title' => __('Review queue'), 'queue' => revisions_submitted()]);
