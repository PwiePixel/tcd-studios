<?php
/**
 * @var array<string,mixed> $user @var string $nameValue @var array<string,string> $errors
 * @var array<int,array<string,mixed>> $requests @var array{status:string,created_at:?int,link_until:?int,link_valid:bool,legal_hold:bool} $termination @var array{version:?string,at:?int,current:bool} $termsInfo
 */
defined('TCD_APP') || exit;
?>
<h1><?= h(__('Account')) ?></h1>
<p class="pf-muted"><?= h(__('Signed in as')) ?> <strong><?= h($user['email']) ?></strong></p>

<div class="pf-grid pf-grid--2">
  <div class="pf-card">
    <h2><?= h(__('Your name')) ?></h2>
    <form class="pf-form" method="post" action="/portal/account.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="name">
      <div class="pf-field">
        <label for="f-name"><?= h(__('Name')) ?></label>
        <input type="text" id="f-name" name="name" value="<?= h($nameValue) ?>" maxlength="80" required<?= portal_invalid($errors, 'name') ?>>
        <?= portal_err($errors, 'name') ?>
      </div>
      <div class="pf-actions"><button class="pf-btn" type="submit"><?= h(__('Save name')) ?></button></div>
    </form>
  </div>

  <div class="pf-card">
    <h2><?= h(__('Change password')) ?></h2>
    <form class="pf-form" method="post" action="/portal/account.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div class="pf-field">
        <label for="f-current_password"><?= h(__('Current password')) ?></label>
        <input type="password" id="f-current_password" name="current_password" autocomplete="current-password" required<?= portal_invalid($errors, 'current_password') ?>>
        <?= portal_err($errors, 'current_password') ?>
      </div>
      <div class="pf-field">
        <label for="f-new_password"><?= h(__('New password')) ?></label>
        <input type="password" id="f-new_password" name="new_password" autocomplete="new-password" minlength="10" required<?= portal_invalid($errors, 'new_password') ?>>
        <?= portal_err($errors, 'new_password') ?>
      </div>
      <div class="pf-field">
        <label for="f-new_password2"><?= h(__('Repeat new password')) ?></label>
        <input type="password" id="f-new_password2" name="new_password2" autocomplete="new-password" required<?= portal_invalid($errors, 'new_password2') ?>>
        <?= portal_err($errors, 'new_password2') ?>
      </div>
      <div class="pf-actions"><button class="pf-btn" type="submit"><?= h(__('Change password')) ?></button></div>
    </form>
  </div>
</div>

<div class="pf-card" id="terms">
  <h2><?= h(__('Terms and Conditions')) ?></h2>
<?php if ($termsInfo['version'] === null): ?>
  <p class="pf-muted"><?= h(__('You have not accepted our Terms and Conditions yet.')) ?></p>
<?php else: ?>
  <dl class="pf-kv">
    <dt><?= h(__('Accepted version')) ?></dt><dd><?= h($termsInfo['version']) ?><?= $termsInfo['current'] ? '' : ' <span class="pf-badge pf-badge--pending">' . h(__('outdated')) . '</span>' ?></dd>
    <dt><?= h(__('Accepted on')) ?></dt><dd><?= h(fmt_time($termsInfo['at'])) ?></dd>
  </dl>
<?php endif; ?>
  <p>
<?php if (!$termsInfo['current']): ?>
    <a class="pf-btn pf-btn--primary" href="/portal/terms.php"><?= h(__('Review and accept the current version')) ?></a>
<?php endif; ?>
    <a href="/agb/" target="_blank" rel="noopener"><?= h(__('Terms and Conditions (AGB)')) ?></a> ·
    <a href="/terms/" target="_blank" rel="noopener"><?= h(__('English convenience translation')) ?></a>
  </p>
</div>

<div class="pf-card">
  <h2><?= h(__('Your requests')) ?></h2>
<?php if ($requests === []): ?>
  <p class="pf-muted"><?= h(__('You have not sent any requests.')) ?></p>
<?php else: ?>
  <div class="pf-table-wrap">
    <table class="pf-table">
      <thead><tr><th><?= h(__('Service')) ?></th><th><?= h(__('Sent')) ?></th><th><?= h(__('Status')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($requests as $r): ?>
        <tr>
          <td><?= h($r['service_label']) ?></td>
          <td><?= h(portal_date((int) $r['created_at'])) ?></td>
          <td><?= status_badge((string) $r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>

<div class="pf-card">
  <h2><?= h(__('Your data')) ?></h2>
  <p><?= h(__('You can download a copy of everything we store about you: your account, your imprint profiles including the private details and all versions, and your requests. The file does not contain your password.')) ?></p>
  <form class="pf-form" method="post" action="/portal/account-export.php">
    <?= csrf_field() ?>
    <div class="pf-actions"><button class="pf-btn" type="submit"><?= h(__('Download my data (JSON)')) ?></button></div>
  </form>
  <hr class="pf-rule">
  <h3 id="end-service"><?= h(__('End the service and delete all my data')) ?></h3>
<?php if ($termination['status'] === 'on_hold'): ?>
  <div class="pf-notice" id="termination-notice"><?= h(__('Your deletion request is on hold: we are legally required to keep some of your data for now. It is deleted as soon as the reason no longer applies.')) ?></div>
<?php elseif ($termination['status'] !== 'none'): ?>
  <div class="pf-notice" id="termination-notice"><?= h(__('You asked to end the service. We mailed you a confirmation link; nothing is deleted until you confirm.')) ?></div>
<?php else: ?>
  <p class="pf-muted"><?= h(__('You can end Imprint Protection at any time. Your imprint pages go offline and all your data, including address data and ID photos, is deleted automatically and irrevocably. We e-mail you a confirmation link first.')) ?></p>
<?php endif; ?>
  <p><a class="pf-btn pf-btn--danger" href="/portal/terminate.php"><?= h($termination['status'] === 'none' ? __('End the service…') : __('Continue ending the service')) ?></a></p>
</div>
