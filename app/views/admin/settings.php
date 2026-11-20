<?php
/**
 * @var array<string,string> $fields @var array<string,string> $defaults @var array<string,string> $current @var array<string,bool> $overrides
 * @var array<string,string> $errors @var array<int,array{label:string,status:string,detail:string}> $checks
 * @var array<string,array{configured:mixed,effective:mixed,source:string,env:string}> $cfgInfo
 * @var array<string,string> $cfgErrors @var array<string,string> $cfgForm @var string $cfgSection
 * @var bool $smtpTestAvailable @var array{at:int,ok:bool,detail:string}|null $smtpLastTest
 */
defined('TCD_APP') || exit;
$mailLabels = [
    'mail.from' => __('Sender of system mails'), 'mail.contact' => __('Contact form and requests'), 'mail.info' => __('General information'),
    'mail.imprint' => __('Imprint reviews and imprint contact'), 'mail.privacy' => __('Privacy requests'), 'mail.admin_notify' => __('Notifications for admins'),
    'mail.requests' => __('Service and shop requests'),
];
$badge = ['ok' => 'ok', 'warn' => 'pending', 'fail' => 'rejected'];
$badgeText = ['ok' => __('OK'), 'warn' => __('Warning'), 'fail' => __('Problem')];

/** Value shown in a form field: the submitted value after an error in that section, else the saved one. */
$val = static function (string $key, string $section) use ($cfgForm, $cfgSection, $cfgInfo): string {
    if ($cfgSection === $section && array_key_exists($key, $cfgForm)) {
        return (string) $cfgForm[$key];
    }
    $v = $cfgInfo[$key]['configured'] ?? '';
    return is_bool($v) ? ($v ? 'true' : 'false') : (is_scalar($v) ? (string) $v : '');
};
$err = static function (string $key, string $section) use ($cfgErrors, $cfgSection): string {
    return $cfgSection === $section && isset($cfgErrors[$key]) ? (string) $cfgErrors[$key] : '';
};
$field = static function (string $key): string {
    return str_replace('.', '_', $key);
};
$source = static function (string $key) use ($cfgInfo): string {
    return isset($cfgInfo[$key]) ? admin_setting_source_label($cfgInfo[$key]) : '';
};
$sectionError = static function (string $section) use ($cfgErrors, $cfgSection): string {
    return $cfgSection === $section && $cfgErrors
        ? '<div class="pf-flash pf-flash--error" role="alert">' . h(__('Nothing was saved. Please correct the marked fields.')) . '</div>' : '';
};
$transport = $val('mail.transport', 'delivery');
$encryption = $val('mail.smtp.encryption', 'delivery');
$pwSet = (string) ($cfgInfo['mail.smtp.password']['configured'] ?? '') !== '';
?>
<h1><?= h(__('Settings')) ?></h1>
<?= render_partial('admin/_subnav', ['current' => 'settings.php']) ?>

<section class="pf-card" id="website">
  <h2><?= h(__('Website')) ?></h2>
  <?= $sectionError('site') ?>
  <form class="pf-form pf-wide-form" method="post" action="/admin/settings.php">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_site">
    <div class="pf-field">
      <label for="site_url"><?= h(__('Website address')) ?></label>
      <input type="url" id="site_url" name="site_url" maxlength="300" required value="<?= h($val('site_url', 'site')) ?>"<?= $err('site_url', 'site') !== '' ? ' aria-invalid="true"' : '' ?>>
      <div class="pf-help"><?= h(__('The public address of this website without a path, e.g. https://tcd-studio.de. It is used for links in e-mails, the imprint pages and the sitemap.')) ?>
        <?= h(__('Source:')) ?> <?= h($source('site_url')) ?><?php if ($cfgInfo['site_url']['source'] === 'env'): ?> – <?= h(__('in effect:')) ?> <code><?= h((string) $cfgInfo['site_url']['effective']) ?></code><?php endif; ?></div>
      <?php if ($err('site_url', 'site') !== ''): ?><div class="pf-error-text"><?= h($err('site_url', 'site')) ?></div><?php endif; ?>
    </div>
    <div class="pf-check">
      <input type="checkbox" id="trust_forwarded_proto" name="trust_forwarded_proto" value="1"<?= $val('trust_forwarded_proto', 'site') === 'true' ? ' checked' : '' ?>>
      <label for="trust_forwarded_proto"><?= h(__('HTTPS is terminated by a proxy in front of this server (trust the X-Forwarded-Proto header)')) ?></label>
    </div>
    <div class="pf-help"><?= h(__('Tick this ONLY if visitors reach the site through a TLS-terminating proxy, for example a Cloudflare tunnel or a load balancer, that talks to this server over plain HTTP and always sets X-Forwarded-Proto. Without a proxy, anybody could send this header and fake a secure connection. If it is needed but not ticked, the site considers connections insecure (no Secure cookies, ID uploads refused).')) ?>
      <?= h(__('Source:')) ?> <?= h($source('trust_forwarded_proto')) ?></div>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Save website settings')) ?></button></div>
  </form>
</section>

<section class="pf-card" id="mail-addresses">
  <h2><?= h(__('Mail addresses')) ?></h2>
  <p class="pf-muted"><?= h(__('The mailboxes are hosted by your mail provider. Create them there first; this site only uses the addresses.')) ?></p>
  <?= $sectionError('addresses') ?>
  <form class="pf-form pf-wide-form" method="post" action="/admin/settings.php">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_mail_addresses">
    <?php foreach ($mailLabels as $key => $label): $e = $err($key, 'addresses'); ?>
      <div class="pf-field">
        <label for="<?= h($field($key)) ?>"><?= h($label) ?></label>
        <input type="email" id="<?= h($field($key)) ?>" name="<?= h($field($key)) ?>" maxlength="254" required value="<?= h($val($key, 'addresses')) ?>"<?= $e !== '' ? ' aria-invalid="true"' : '' ?>>
        <div class="pf-help"><?= h(__('Source:')) ?> <?= h($source($key)) ?></div>
        <?php if ($e !== ''): ?><div class="pf-error-text"><?= h($e) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Save mail addresses')) ?></button></div>
  </form>
</section>

<section class="pf-card" id="mail-delivery">
  <h2><?= h(__('Mail delivery')) ?></h2>
  <p class="pf-muted"><?= h(__('How system mails (confirmation links, password resets, notifications) leave this server. If your mailboxes are hosted by an external provider, use that provider\'s SMTP server with the login of the sender mailbox.')) ?></p>
  <?= $sectionError('delivery') ?>
  <form class="pf-form pf-wide-form" method="post" action="/admin/settings.php">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_mail_delivery">
    <div class="pf-field">
      <label for="mail_transport"><?= h(__('Delivery method')) ?></label>
      <select id="mail_transport" name="mail_transport">
        <option value="smtp"<?= $transport === 'smtp' ? ' selected' : '' ?>><?= h(__('External SMTP server (mail provider)')) ?></option>
        <option value="mail"<?= $transport !== 'smtp' ? ' selected' : '' ?>><?= h(__('PHP mail() – only with a mail server on this machine')) ?></option>
      </select>
      <div class="pf-help"><?= h(__('Source:')) ?> <?= h($source('mail.transport')) ?></div>
      <?php if ($err('mail.transport', 'delivery') !== ''): ?><div class="pf-error-text"><?= h($err('mail.transport', 'delivery')) ?></div><?php endif; ?>
    </div>
    <fieldset class="pf-fieldset">
      <legend><?= h(__('SMTP server')) ?></legend>
      <?php foreach (['mail.smtp.host' => [__('Server (host name)'), 'text', 'smtp.example.com', __('The outgoing mail server of your provider.')],
                      'mail.smtp.port' => [__('Port'), 'number', '587', __('587 for STARTTLS (usual), 465 for SSL/TLS.')],
                      'mail.smtp.username' => [__('User name (login)'), 'text', 'no-reply@example.com', __('Usually the full address of the sender mailbox. Leave empty if the server needs no login.')]] as $key => [$label, $type, $ph, $help]):
          $e = $err($key, 'delivery'); ?>
        <?php if ($key === 'mail.smtp.username'): ?>
        <div class="pf-field">
          <label for="mail_smtp_encryption"><?= h(__('Encryption')) ?></label>
          <select id="mail_smtp_encryption" name="mail_smtp_encryption"<?= $err('mail.smtp.encryption', 'delivery') !== '' ? ' aria-invalid="true"' : '' ?>>
            <option value="tls"<?= $encryption === 'tls' ? ' selected' : '' ?>><?= h(__('STARTTLS (port 587)')) ?></option>
            <option value="ssl"<?= $encryption === 'ssl' ? ' selected' : '' ?>><?= h(__('SSL/TLS (port 465)')) ?></option>
            <option value="none"<?= $encryption === 'none' ? ' selected' : '' ?>><?= h(__('None – only for a server on this machine')) ?></option>
          </select>
          <div class="pf-help"><?= h(__('Source:')) ?> <?= h($source('mail.smtp.encryption')) ?></div>
          <?php if ($err('mail.smtp.encryption', 'delivery') !== ''): ?><div class="pf-error-text"><?= h($err('mail.smtp.encryption', 'delivery')) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="pf-field">
          <label for="<?= h($field($key)) ?>"><?= h($label) ?></label>
          <input type="<?= h($type) ?>" id="<?= h($field($key)) ?>" name="<?= h($field($key)) ?>" maxlength="253" placeholder="<?= h($ph) ?>"<?= $type === 'number' ? ' min="1" max="65535"' : '' ?> autocomplete="off" value="<?= h($val($key, 'delivery')) ?>"<?= $e !== '' ? ' aria-invalid="true"' : '' ?>>
          <div class="pf-help"><?= h($help) ?> <?= h(__('Source:')) ?> <?= h($source($key)) ?></div>
          <?php if ($e !== ''): ?><div class="pf-error-text"><?= h($e) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="pf-field">
        <label for="mail_smtp_password"><?= h(__('Password')) ?>
          <?php if ($pwSet): ?><span class="pf-badge pf-badge--ok"><?= h(__('set')) ?></span><?php else: ?><span class="pf-badge pf-badge--draft"><?= h(__('not set')) ?></span><?php endif; ?></label>
        <input type="password" id="mail_smtp_password" name="mail_smtp_password" maxlength="256" autocomplete="new-password" value=""<?= $err('mail.smtp.password', 'delivery') !== '' ? ' aria-invalid="true"' : '' ?>>
        <div class="pf-help"><?= h(__('Write-only: the stored password is never shown. Leave the field empty to keep it.')) ?></div>
        <?php if ($err('mail.smtp.password', 'delivery') !== ''): ?><div class="pf-error-text"><?= h($err('mail.smtp.password', 'delivery')) ?></div><?php endif; ?>
      </div>
      <?php if ($pwSet): ?>
      <div class="pf-check">
        <input type="checkbox" id="mail_smtp_password_clear" name="mail_smtp_password_clear" value="1">
        <label for="mail_smtp_password_clear"><?= h(__('Remove the stored password')) ?></label>
      </div>
      <?php endif; ?>
    </fieldset>
    <div class="pf-field">
      <label for="settings_admin_password"><?= h(__('Your current password')) ?></label>
      <input type="password" id="settings_admin_password" name="admin_password" autocomplete="current-password"<?= $err('admin_password', 'delivery') !== '' ? ' aria-invalid="true"' : '' ?>>
      <div class="pf-help"><?= h(__('Required when you change the SMTP server, port, encryption, login or password: these decide where the mailbox password is sent.')) ?></div>
      <?php if ($err('admin_password', 'delivery') !== ''): ?><div class="pf-error-text"><?= h($err('admin_password', 'delivery')) ?></div><?php endif; ?>
    </div>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Save mail delivery')) ?></button></div>
  </form>

  <h3><?= h(__('Test connection')) ?></h3>
  <p class="pf-muted"><?= h(__('Connects to the saved SMTP server, starts encryption and logs in – then disconnects. No e-mail is sent. Save your changes first.')) ?></p>
  <?php if ($smtpLastTest !== null): ?>
    <p><span class="pf-badge pf-badge--<?= $smtpLastTest['ok'] ? 'ok' : 'rejected' ?>"><?= h($smtpLastTest['ok'] ? __('last test OK') : __('last test FAILED')) ?></span>
      <span class="pf-muted"><?= h(fmt_time($smtpLastTest['at'])) ?> (<?= h(admin_age($smtpLastTest['at'])) ?>)</span><br>
      <span class="pf-small"><?= h($smtpLastTest['detail']) ?></span></p>
  <?php endif; ?>
  <?php if ($smtpTestAvailable): ?>
  <form class="pf-form" method="post" action="/admin/settings.php"><?= csrf_field() ?><input type="hidden" name="action" value="smtp_test">
    <button class="pf-btn" type="submit"<?= (string) ($cfgInfo['mail.smtp.host']['configured'] ?? '') === '' ? ' disabled' : '' ?>><?= h(__('Test connection (no e-mail is sent)')) ?></button></form>
  <?php else: ?>
    <p class="pf-help"><?= h(__('The connection test is not available on this installation yet.')) ?></p>
  <?php endif; ?>
</section>

<section class="pf-card">
  <h2><?= h(__('Management block of the imprints')) ?></h2>
  <p class="pf-muted"><?= h(__('These details are printed in every imprint instead of the private address of the creator. Changes apply to pages written from now on; use the button below to update the existing pages.')) ?></p>
  <form class="pf-form pf-wide-form" method="post" action="/admin/settings.php">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_management">
    <div class="pf-actions pf-actions--top"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Save management block')) ?></button></div>
    <?php foreach ($fields as $key => $label): if (!array_key_exists($key, $defaults)) { continue; } ?>
      <div class="pf-field">
        <label for="m_<?= h($key) ?>"><?= h($label) ?><?= $overrides[$key] ? ' <span class="pf-badge pf-badge--info">' . h(__('customised')) . '</span>' : '' ?></label>
        <div class="pf-row pf-row--nowrap">
          <input type="text" id="m_<?= h($key) ?>" name="m_<?= h($key) ?>" maxlength="250" value="<?= h($current[$key] ?? '') ?>"<?= isset($errors[$key]) ? ' aria-invalid="true"' : '' ?>>
          <?php if ($overrides[$key]): ?><button class="pf-btn pf-btn--small pf-btn--ghost" type="submit" form="reset-form" name="field" value="<?= h($key) ?>"><?= h(__('Reset to default')) ?></button><?php endif; ?>
        </div>
        <div class="pf-help"><?= h(__('Default:')) ?> <?= h((string) $defaults[$key]) ?></div>
        <?php if (isset($errors[$key])): ?><div class="pf-error-text"><?= h($errors[$key]) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </form>
  <form id="reset-form" method="post" action="/admin/settings.php"><?= csrf_field() ?><input type="hidden" name="action" value="reset_field"></form>
</section>

<section class="pf-card">
  <h2><?= h(__('Regenerate all imprint pages')) ?></h2>
  <p class="pf-muted"><?= h(__('Writes the static page of every live imprint again (for example after changing the management block or the page template) and refreshes the redirect pages for the old addresses.')) ?></p>
  <form class="pf-form" method="post" action="/admin/settings.php"><?= csrf_field() ?><input type="hidden" name="action" value="regenerate">
    <button class="pf-btn" type="submit"><?= h(__('Regenerate all imprint pages')) ?></button></form>
</section>

<section class="pf-card" id="effective-config">
  <h2><?= h(__('Effective configuration')) ?></h2>
  <p class="pf-muted"><?= h(__('What the site uses right now and where each value comes from. Values saved here are stored in data/config.local.php; environment variables set on the server win over them. Secrets are never shown.')) ?></p>
  <details>
    <summary><?= h(__('Show all settings')) ?></summary>
    <div class="pf-table-wrap"><table class="pf-table">
      <thead><tr><th><?= h(__('Setting')) ?></th><th><?= h(__('Value in effect')) ?></th><th><?= h(__('Source')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($cfgInfo as $key => $info): ?>
        <tr><td><code><?= h($key) ?></code></td><td class="pf-break"><?= h(admin_setting_display($key, $info['effective'])) ?></td><td><?= h(admin_setting_source_label($info)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="pf-help"><?= h(__('The archive settings (vault.*) are changed on the Identity vault page.')) ?></p>
  </details>
</section>

<section class="pf-card" id="system-checks">
  <h2><?= h(__('System checks')) ?></h2>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Check')) ?></th><th><?= h(__('Result')) ?></th><th><?= h(__('Details')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($checks as $c): ?>
      <tr><td><?= h($c['label']) ?></td><td><span class="pf-badge pf-badge--<?= h($badge[$c['status']] ?? 'draft') ?>"><?= h($badgeText[$c['status']] ?? $c['status']) ?></span></td><td><?= h($c['detail']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <form class="pf-form" method="post" action="/admin/settings.php"><?= csrf_field() ?><input type="hidden" name="action" value="probe">
    <p class="pf-help"><?= h(__('The probe asks this website for /data/.htaccess to make sure the data directory cannot be downloaded.')) ?></p>
    <button class="pf-btn" type="submit"><?= h(__('Probe data directory over HTTP')) ?></button></form>
</section>
