<?php
declare(strict_types=1);

/** Identity vault setup: public key, in-browser key pair generator, archive settings (data/config.local.php), archive test and status. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$self = '/admin/vault.php';
$formError = '';
$keyValue = '';
$archiveErrors = [];      // dotted key (or 'admin_password', '_location') => message
$archiveForm = [];        // submitted non-secret values after an error
$local = config_local_load();
$archiveKeys = ['vault.driver', 'vault.dir', 'vault.s3.endpoint', 'vault.s3.region', 'vault.s3.bucket', 'vault.s3.prefix', 'vault.s3.path_style'];
$archiveSecrets = ['vault.s3.access_key', 'vault.s3.secret_key'];

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
        } elseif ($existing !== '' && !password_verify(admin_post_raw('admin_password'), (string) $admin['password_hash'])) {
            $formError = __('Please enter your current password to replace the public key.');
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
    } elseif ($action === 'save_archive') {
        foreach ($archiveKeys as $key) {
            $archiveForm[$key] = $key === 'vault.s3.path_style'
                ? (post_bool('vault_s3_path_style') ? 'true' : 'false')
                : post_str(str_replace('.', '_', $key), 1024);
        }
        $driver = $archiveForm['vault.driver'];
        // fields that do not apply to the chosen driver may stay empty (keep what is stored)
        $raw = [];
        foreach ($archiveForm as $key => $value) {
            $isS3 = strncmp($key, 'vault.s3.', 9) === 0;
            if ($value === '' && (($isS3 && $driver !== 's3') || ($key === 'vault.dir' && $driver !== 'directory'))) {
                continue;
            }
            if ($key === 'vault.dir' && $value === '') {
                continue;                        // handled below: empty = default directory in the data directory
            }
            $raw[$key] = $value;
        }
        $diff = admin_settings_diff($raw, $local);
        $archiveErrors = $diff['errors'];
        $changes = $diff['changes'];
        if ($archiveForm['vault.dir'] === '' && $driver === 'directory' && (string) admin_setting_info('vault.dir', $local)['configured'] !== '') {
            $changes['vault.dir'] = '';
        }
        foreach ($archiveSecrets as $key) {
            $f = str_replace('.', '_', $key);
            $new = admin_post_secret($f, true);
            $stored = (string) admin_setting_info($key, $local)['configured'];
            if (post_bool($f . '_clear')) {
                if ($new !== '') {
                    $archiveErrors[$key] = __('Either enter a new key or remove the stored one, not both.');
                } elseif ($stored !== '') {
                    $changes[$key] = '';
                }
            } elseif ($new !== '') {
                $err = config_setting_error($key, $new);
                if ($err !== null) {
                    $archiveErrors[$key] = __($err);
                } elseif (!hash_equals($stored, $new)) {
                    $changes[$key] = $new;
                }
            }
        }
        $result = static function (string $key) use (&$changes, $local) {
            return array_key_exists($key, $changes) ? $changes[$key] : admin_setting_info($key, $local)['configured'];
        };
        if (!$archiveErrors && $driver === 's3') {
            $labels = admin_vault_setting_labels();
            foreach (['vault.s3.endpoint', 'vault.s3.region', 'vault.s3.bucket', 'vault.s3.access_key', 'vault.s3.secret_key'] as $key) {
                if ((string) $result($key) === '') {
                    $archiveErrors[$key] = sprintf(__('%s is required for S3 storage.'), $labels[$key]);
                }
            }
        }
        // the location of already archived files must not move (see admin_vault_locked_keys())
        if (!$archiveErrors) {
            $moved = array_values(array_intersect(array_keys($changes), admin_vault_locked_keys()));
            if ($moved) {
                $counts = admin_vault_archived_counts();
                foreach ($moved as $key) {
                    $archiveErrors[$key] = __('Locked while archived ID files exist.');
                }
                $archiveErrors['_location'] = sprintf(
                    __('The archive location cannot be changed: %d archived ID file(s) are stored there. The database only remembers each file\'s name inside the current location, so after a change these files could neither be read for a check nor erased when a customer ends the service. Nothing was saved. The access keys, region, path style and key prefix can still be changed.'),
                    $counts['dir'] + $counts['s3']
                );
                audit('vault.archive_settings_refused', 'vault', 'archive', ['keys' => $moved, 'archived_files' => $counts['dir'] + $counts['s3']]);
            }
        }
        if (!$archiveErrors && $changes) {
            $err = admin_stepup_error($admin, admin_post_raw('admin_password'), __('Please enter your current password to change the archive settings.'));
            if ($err !== null) {
                $archiveErrors['admin_password'] = $err;
            }
        }
        if (!$archiveErrors) {
            if (!$changes) {
                redirect($self . '#archive-settings', ['ok' => __('Nothing changed.')]);
            }
            try {
                config_local_set($changes);
            } catch (Throwable $e) {
                error_log('vault: cannot write config.local.php: ' . $e->getMessage());
                redirect($self . '#archive-settings', ['error' => __('The archive settings could not be saved: data/config.local.php is not writable. Check the permissions of the data directory.')]);
            }
            setting_set('vault.probe', '');      // the last test was for the old settings
            audit('vault.archive_settings', 'vault', 'archive', ['keys' => array_keys($changes)]);
            // The test runs in a new request (config() still holds the old settings in this one): the page offers it.
            redirect($self . '?probe=1#archive-test', ['ok' => __('The archive settings were saved. Run "Test archive" now to check that the new settings work.')]);
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

$archiveInfo = [];
foreach (array_merge($archiveKeys, $archiveSecrets) as $key) {
    $archiveInfo[$key] = admin_setting_info($key, $local);
}
$status = admin_vault_status();
render_view('admin/vault', [
    'title' => __('Identity vault'),
    'status' => $status,
    'formError' => $formError,
    'keyValue' => $keyValue,
    'archiveInfo' => $archiveInfo,
    'archiveErrors' => $archiveErrors,
    'archiveForm' => $archiveForm,
    'archiveLocked' => admin_vault_locked_keys(),
    'archiveCounts' => admin_vault_archived_counts(),
    'offerProbe' => get_int('probe') === 1,
]);
