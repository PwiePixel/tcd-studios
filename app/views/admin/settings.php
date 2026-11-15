<?php
/**
 * @var array<string,string> $fields @var array<string,string> $defaults @var array<string,string> $current @var array<string,bool> $overrides
 * @var array<string,string> $errors @var array<string,string> $mailboxes @var array<int,array{label:string,status:string,detail:string}> $checks
 */
defined('TCD_APP') || exit;
$mailLabels = [
    'from' => __('Sender of system mails'), 'contact' => __('Contact form and requests'), 'info' => __('General information'),
    'imprint' => __('Imprint reviews and imprint contact'), 'privacy' => __('Privacy requests'), 'admin_notify' => __('Notifications for admins'),
    'requests' => __('Service and shop requests'),
];
$badge = ['ok' => 'ok', 'warn' => 'pending', 'fail' => 'rejected'];
$badgeText = ['ok' => __('OK'), 'warn' => __('Warning'), 'fail' => __('Problem')];
?>
<h1><?= h(__('Settings')) ?></h1>
<?= render_partial('admin/_subnav', ['current' => 'settings.php']) ?>

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

<section class="pf-card">
  <h2><?= h(__('Mailboxes')) ?></h2>
  <p class="pf-muted"><?= h(__('Read-only. The mailboxes are changed in data/config.local.php on the server (section "mail"). Create them at your mail host first.')) ?></p>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Purpose')) ?></th><th><?= h(__('Address')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($mailboxes as $key => $addr): ?>
      <tr><td><?= h($mailLabels[$key] ?? (string) $key) ?></td><td><?= h((string) $addr) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <form class="pf-form" method="post" action="/admin/settings.php"><?= csrf_field() ?><input type="hidden" name="action" value="testmail">
    <div class="pf-actions"><button class="pf-btn" type="submit"><?= h(__('Send a test mail to my address')) ?></button></div></form>
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
