<?php
declare(strict_types=1);

/** Identity vault setup: public key, in-browser key pair generator, archive test and status. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$self = '/admin/vault.php';
$formError = '';
$keyValue = '';

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 20);

    if ($action === 'save_key') {
        $pem = post_str('public_key', 8000);
        $keyValue = $pem;
        $existing = vault_public_key_pem();
        if (stripos($pem, 'PRIVATE KEY') !== false) {
            // Never echo it back, never keep it: a private key does not belong on a server.
            $pem = '';
            $keyValue = '';
            $err = __('This looks like a PRIVATE key. NEVER paste a private key here: it must stay offline. Nothing was saved. Please treat this key as compromised and generate a new pair.');
        } else {
            $err = $pem === '' ? __('Please paste a public key or generate a key pair first.') : vault_validate_public_key($pem);
        }
        if ($err !== null) {
            $formError = $err;
        } elseif ($existing !== '' && admin_vault_same_key($existing, $pem)) {
            redirect($self, ['ok' => __('This public key is already set. Nothing was changed.')]);
        } elseif ($existing !== '' && !post_bool('confirm_replace')) {
            $formError = __('A public key is already set. Please tick the confirmation box to replace it.');
        } else {
            try {
                vault_set_public_key($pem);
            } catch (InvalidArgumentException $e) {
                $formError = $e->getMessage();
            }
            if ($formError === '') {
                $newFp = vault_fingerprint(vault_public_key_pem());
                $oldFp = $existing !== '' ? vault_fingerprint($existing) : '';
                audit('vault.public_key_set', 'vault', 'public_key', ['fingerprint' => $newFp, 'replaced' => $existing !== '', 'previous_fingerprint' => $oldFp]);
                if ($existing !== '') {
                    notify_admins('Identity vault: public key was replaced', sprintf(
                        "The public key of the identity vault was replaced by %s.\n\nNew fingerprint (SHA-256): %s\nPrevious fingerprint: %s\n\nID photos uploaded from now on can only be read with the new private key. If you did not expect this change, check the audit log immediately.",
                        (string) $admin['email'],
                        $newFp,
                        $oldFp
                    ));
                }
                redirect($self, ['ok' => $existing !== ''
                    ? __('The public key was replaced. New ID photos are encrypted for the new key; older files still need the OLD private key.')
                    : __('The public key was saved. Customers can now upload ID photos.')]);
            }
        }
    } elseif ($action === 'probe') {
        if (!rate_limit_hit('admin_vault_probe:' . (int) $admin['id'], [10, 3600])) {
            redirect($self, ['error' => __('Too many archive tests. Please try again later.')]);
        }
        $res = admin_vault_probe();
        audit('vault.probe', 'vault', 'archive', ['ok' => $res['ok'], 'driver' => (string) config('vault.driver', 'directory')]);
        redirect($self, [$res['ok'] ? 'ok' : 'error' => $res['detail']]);
    } else {
        abort(400, __('Unknown action.'));
    }
}

$status = admin_vault_status();
render_view('admin/vault', [
    'title' => __('Identity vault'),
    'status' => $status,
    'formError' => $formError,
    'keyValue' => $keyValue,
]);
