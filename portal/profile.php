<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

$profile = portal_owned_profile(get_int('id'), $user);     // 404 for foreign ids
$pid = (int) $profile['id'];
$summary = portal_profile_summary($profile);
$revisions = revisions_for_profile($pid);
$open = $summary['open'];
$live = $summary['live'];
$previewRev = $open ?? $live ?? ($revisions[0] ?? null);

$preview = '';
if ($previewRev !== null) {
    try {
        $preview = imprint_render_fragment($profile, $previewRev['public']);
    } catch (Throwable $e) {
        error_log('profile preview failed: ' . $e->getMessage());
        $preview = '';
    }
}

render_view('portal/profile', [
    'title'        => $summary['name'],
    'profile'      => $profile,
    's'            => $summary,
    'revisions'    => $revisions,
    'open'         => $open,
    'previewRev'   => $previewRev,
    'preview'      => $preview,
    'rejection'    => portal_last_rejection($revisions),
    'missing'      => $open !== null ? portal_private_missing($open['private']) : [],
    'readOnly'     => $profile['status'] === 'suspended',
]);
