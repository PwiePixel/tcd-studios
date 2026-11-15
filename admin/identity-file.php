<?php
declare(strict_types=1);

/**
 * Ciphertext of one identity file (GET, admin only). The server never decrypts: js/vault.js does that in the browser
 * with the private key the admin picks. Nothing personal is written to the audit log (only the file id).
 */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    abort(405, __('Method not allowed.'));
}
$id = admin_id();
$file = identity_file_get($id);
if ($file === null || $file['deleted_at'] !== null) {
    abort(404);
}
if (!rate_limit_hit('admin_idfile:' . (int) $admin['id'], [60, 600])) {
    http_response_code(429);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 300');
    echo 'Too many requests';
    exit;
}
try {
    $blob = vault_file_blob($id);
} catch (Throwable $e) {
    error_log('identity-file ' . $id . ': ' . $e->getMessage());
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'The storage system could not deliver the file';
    exit;
}
audit('identity.file_viewed', 'identity_file', $id);
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="identity-file-' . $id . '.tcdv"');
header('Content-Length: ' . strlen($blob));
echo $blob;
