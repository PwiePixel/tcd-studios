<?php
declare(strict_types=1);

/** Management block of the imprints, regeneration, mailbox overview and system checks. */
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

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 30);

    if ($action === 'save_management') {
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
    } elseif ($action === 'testmail') {
        if (!rate_limit_hit('admin_testmail:' . (int) $admin['id'], [5, 3600])) {
            redirect('/admin/settings.php', ['error' => __('Too many test mails. Please try again later.')]);
        }
        $sent = send_mail((string) $admin['email'], __('Test mail from the admin panel'), "This is a test message from the admin panel of " . (string) config('site_name') . ".\nIf you can read it, outgoing mail works.");
        audit('settings.testmail', 'settings', null, ['sent' => $sent]);
        redirect('/admin/settings.php', $sent
            ? ['ok' => __('A test mail was handed over to the mail system. Check your inbox (and spam folder).')]
            : ['error' => __('The test mail could not be sent. Check the mail configuration of the server.')]);
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

render_view('admin/settings', [
    'title' => __('Settings'),
    'fields' => $fields,
    'defaults' => $defaults,
    'current' => $formValues !== null ? array_merge($current, $formValues) : $current,
    'overrides' => $overrides,
    'errors' => $errors,
    'mailboxes' => (array) config('mail', []),
    'checks' => $checks,
]);
