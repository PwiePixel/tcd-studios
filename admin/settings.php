<?php
declare(strict_types=1);

/**
 * Settings: website address, mail addresses, mail delivery (PHP mail() or the provider's SMTP server, connection test without
 * sending any e-mail), management block of the imprints, regeneration and system checks.
 * Website and mail settings are stored in data/config.local.php (whitelist + validation: app/lib/config_settings.php).
 */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

/** @return array<string,string> field key => human label */
function admin_management_fields(): array
{
    return [
        'name' => __('Name of the management'), 'street' => __('Street'), 'city' => __('Postcode, city and country'),
        'phone' => __('Phone'), 'email' => __('E-mail'), 'website' => __('Website'), 'legal_form' => __('Legal form'),
        'chamber' => __('Chamber / association'), 'authority' => __('Supervisory authority'), 'regulations_url' => __('Link to the regulations (professional rules)'),
        'notice' => __('Important notice about the address'),
    ];
}

/** Validate one management value; returns an error message or null. */
function admin_management_error(string $key, string $value): ?string
{
    if (!v_len($value, 1, 200) || !v_oneline($value)) {
        return __('Use 1–200 characters on a single line.');
    }
    if ($key === 'email' && !v_email($value)) {
        return __('Please enter a valid e-mail address.');
    }
    if (($key === 'website' || $key === 'regulations_url') && !v_https_url($value)) {
        return __('Please enter a complete https:// address.');
    }
    if ($key === 'phone' && preg_match('/^[0-9+()\/. -]{3,40}$/D', $value) !== 1) {
        return __('Please enter a valid phone number.');
    }
    return null;
}

$defaults = (array) config('management', []);
$fields = admin_management_fields();
$errors = [];
$formValues = null;
$self = '/admin/settings.php';
$local = config_local_load();
$siteKeys = ['site_url', 'trust_forwarded_proto'];
$mailKeys = ['mail.from', 'mail.contact', 'mail.info', 'mail.imprint', 'mail.privacy', 'mail.admin_notify', 'mail.requests'];
$smtpKeys = ['mail.smtp.host', 'mail.smtp.port', 'mail.smtp.encryption', 'mail.smtp.username', 'mail.smtp.password'];
$cfgErrors = [];        // dotted key (or 'admin_password') => message, for the section in $cfgSection
$cfgForm = [];          // dotted key => submitted raw value (never secrets)
$cfgSection = '';

/** POST field name of a dotted setting key (PHP turns dots in field names into underscores anyway). */
function admin_settings_field(string $key): string
{
    return str_replace('.', '_', $key);
}

/**
 * Save validated changes to data/config.local.php, audit the changed KEY NAMES (never values) and redirect.
 *
 * @param array<string,mixed> $changes
 */
function admin_settings_save(string $auditAction, string $target, array $changes, string $okMessage): void
{
    if (!$changes) {
        redirect('/admin/settings.php', ['ok' => __('Nothing changed.')]);
    }
    try {
        config_local_set($changes);
    } catch (Throwable $e) {
        error_log('settings: cannot write config.local.php: ' . $e->getMessage());
        redirect('/admin/settings.php', ['error' => __('The settings could not be saved: data/config.local.php is not writable. Check the permissions of the data directory.')]);
    }
    audit($auditAction, 'settings', $target, ['keys' => array_keys($changes)]);
    redirect('/admin/settings.php#' . $target, ['ok' => $okMessage]);
}

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 30);

    if ($action === 'save_site') {
        $cfgForm = [
            'site_url' => post_str(admin_settings_field('site_url'), 300),
            'trust_forwarded_proto' => post_bool(admin_settings_field('trust_forwarded_proto')) ? 'true' : 'false',
        ];
        $diff = admin_settings_diff($cfgForm, $local);
        $cfgErrors = $diff['errors'];
        $cfgSection = 'site';
        if (!$cfgErrors) {
            admin_settings_save('settings.site', 'website', $diff['changes'], __('The website settings were saved.'));
        }
    } elseif ($action === 'save_mail_addresses') {
        foreach ($mailKeys as $key) {
            $cfgForm[$key] = post_str(admin_settings_field($key), 254);
        }
        $diff = admin_settings_diff($cfgForm, $local);
        $cfgErrors = $diff['errors'];
        $cfgSection = 'addresses';
        if (!$cfgErrors) {
            admin_settings_save('settings.mail_addresses', 'mail-addresses', $diff['changes'], __('The mail addresses were saved.'));
        }
    } elseif ($action === 'save_mail_delivery') {
        $cfgSection = 'delivery';
        foreach (['mail.transport', 'mail.smtp.host', 'mail.smtp.port', 'mail.smtp.encryption', 'mail.smtp.username'] as $key) {
            $cfgForm[$key] = post_str(admin_settings_field($key), 260);
        }
        $diff = admin_settings_diff($cfgForm, $local);
        $cfgErrors = $diff['errors'];
        $changes = $diff['changes'];
        // write-only password: empty input keeps the stored one, "clear" removes it
        $pwInfo = admin_setting_info('mail.smtp.password', $local);
        $pwStored = (string) $pwInfo['configured'];
        $pwNew = admin_post_secret(admin_settings_field('mail.smtp.password'));
        if (post_bool('mail_smtp_password_clear')) {
            if ($pwNew !== '') {
                $cfgErrors['mail.smtp.password'] = __('Either enter a new password or remove the stored one, not both.');
            } elseif ($pwStored !== '') {
                $changes['mail.smtp.password'] = '';
            }
        } elseif ($pwNew !== '') {
            $err = config_setting_error('mail.smtp.password', $pwNew);
            if ($err !== null) {
                $cfgErrors['mail.smtp.password'] = __($err);
            } elseif (!hash_equals($pwStored, $pwNew)) {
                $changes['mail.smtp.password'] = $pwNew;
            }
        }
        $pwNew = '';
        // cross-field rules on the resulting configuration
        $result = static function (string $key) use ($changes, $local) {
            return array_key_exists($key, $changes) ? $changes[$key] : admin_setting_info($key, $local)['configured'];
        };
        if (!$cfgErrors) {
            $host = (string) $result('mail.smtp.host');
            if ($result('mail.transport') === 'smtp' && $host === '') {
                $cfgErrors['mail.smtp.host'] = __('Please enter the SMTP server of your mail provider (for example smtp.example.com).');
            } elseif ($host !== '' && $result('mail.smtp.encryption') === 'none' && !in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true)) {
                $cfgErrors['mail.smtp.encryption'] = __('Without encryption the password would travel in plain text: "none" is only allowed for a server on this machine (127.0.0.1 or localhost). Use STARTTLS (port 587) or SSL/TLS (port 465).');
            }
        }
        // step-up: the SMTP server and its login decide where the mailbox password is sent
        $smtpChanged = array_values(array_intersect(array_keys($changes), $smtpKeys));
        if (!$cfgErrors && $smtpChanged) {
            $err = admin_stepup_error($admin, admin_post_raw('admin_password'), __('Please enter your current password to change the SMTP server or its login.'));
            if ($err !== null) {
                $cfgErrors['admin_password'] = $err;
            }
        }
        if (!$cfgErrors) {
            if ($smtpChanged) {
                admin_smtp_forget_test();
            }
            admin_settings_save('settings.mail_delivery', 'mail-delivery', $changes, $changes
                ? __('The mail delivery settings were saved. Use "Test connection" to check them – no e-mail is sent.')
                : __('Nothing changed.'));
        }
    } elseif ($action === 'smtp_test') {
        if (!rate_limit_hit('admin_smtp_test:' . (int) $admin['id'], [10, 3600])) {
            redirect($self . '#mail-delivery', ['error' => __('Too many connection tests. Please try again later.')]);
        }
        $res = admin_smtp_test();
        audit('settings.smtp_test', 'settings', 'mail-delivery', ['ok' => $res['ok']]);
        redirect($self . '#mail-delivery', [$res['ok'] ? 'ok' : 'error' => $res['detail']]);
    } elseif ($action === 'save_management') {
        $formValues = [];
        $changed = [];
        foreach ($fields as $key => $_label) {
            if (!array_key_exists($key, $defaults)) {
                continue;
            }
            $value = post_str('m_' . $key, 250);
            $formValues[$key] = $value;
            if ($value !== '' && $value !== (string) $defaults[$key]) {
                $err = admin_management_error($key, $value);
                if ($err !== null) {
                    $errors[$key] = $err;
                }
            }
        }
        if (!$errors) {
            db_transaction(static function () use ($formValues, $defaults, &$changed) {
                foreach ($formValues as $key => $value) {
                    $current = setting_get('management.' . $key);
                    if ($value === '' || $value === (string) $defaults[$key]) {
                        if ($current !== null) {
                            db_exec('DELETE FROM settings WHERE k = ?', ['management.' . $key]);
                            $changed[] = $key;
                        }
                    } elseif ($current !== $value) {
                        setting_set('management.' . $key, $value);
                        $changed[] = $key;
                    }
                }
            });
            audit('settings.management', 'settings', 'management', ['fields' => $changed]);
            redirect('/admin/settings.php', ['ok' => $changed ? __('The management block was saved. Use "Regenerate all imprint pages" to apply it to the published pages.') : __('Nothing changed.')]);
        }
    } elseif ($action === 'reset_field') {
        $key = post_str('field', 40);
        if (!isset($fields[$key]) || !array_key_exists($key, $defaults)) {
            abort(400, __('Unknown field.'));
        }
        db_exec('DELETE FROM settings WHERE k = ?', ['management.' . $key]);
        audit('settings.management_reset', 'settings', 'management', ['field' => $key]);
        redirect('/admin/settings.php', ['ok' => sprintf(__('"%s" was reset to the default.'), $fields[$key])]);
    } elseif ($action === 'regenerate') {
        try {
            $n = imprint_publish_all();
            imprint_write_legacy_redirects();
        } catch (Throwable $e) {
            error_log('regenerate failed: ' . $e->getMessage());
            audit('settings.regenerate_failed', 'settings', null, []);
            redirect('/admin/settings.php', ['error' => __('Not all pages could be written. Please check the server log and the write permissions of the imprint directory.')]);
        }
        audit('settings.regenerate', 'settings', null, ['pages' => $n]);
        redirect('/admin/settings.php', ['ok' => sprintf(__('%d imprint page(s) were written again.'), $n)]);
    } elseif ($action === 'probe') {
        $res = admin_probe_data_exposure();
        audit('settings.probe_data', 'settings', null, ['result' => $res['status']]);
        redirect('/admin/settings.php', [($res['status'] === 'ok' ? 'ok' : 'error') => $res['detail']]);
    } else {
        abort(400, __('Unknown action.'));
    }
}

$current = management_config();
$overrides = [];
foreach (array_keys($defaults) as $key) {
    $overrides[$key] = setting_get('management.' . $key) !== null && setting_get('management.' . $key) !== '';
}
$checks = admin_system_checks();

$cfgInfo = [];
foreach (config_setting_keys() as $key => $_type) {
    $cfgInfo[$key] = admin_setting_info($key, $local);
}

render_view('admin/settings', [
    'title' => __('Settings'),
    'fields' => $fields,
    'defaults' => $defaults,
    'current' => $formValues !== null ? array_merge($current, $formValues) : $current,
    'overrides' => $overrides,
    'errors' => $errors,
    'cfgInfo' => $cfgInfo,
    'cfgErrors' => $cfgErrors,
    'cfgForm' => $cfgForm,
    'cfgSection' => $cfgSection,
    'smtpTestAvailable' => admin_smtp_test_available(),
    'smtpLastTest' => admin_smtp_last_test(),
    'checks' => $checks,
]);
