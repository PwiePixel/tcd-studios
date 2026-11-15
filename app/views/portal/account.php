<?php
/**
 * @var array<string,mixed> $user @var string $nameValue @var array<string,string> $errors
 * @var array<int,array<string,mixed>> $requests @var bool $deletionOpen @var array{version:?string,at:?int,current:bool} $termsInfo
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
  <h3><?= h(__('Delete my account')) ?></h3>
<?php if ($deletionOpen): ?>
  <div class="pf-notice pf-notice--info"><?= h(__('Your deletion request is with our team. Nothing has been deleted yet; we will contact you.')) ?></div>
<?php else: ?>
  <p class="pf-muted"><?= h(__('Send us a request to delete your account. Nothing is deleted automatically: our team will process your request and contact you. Data that we are legally required to keep may be retained.')) ?></p>
  <form class="pf-form" method="post" action="/portal/account-delete.php">
    <?= csrf_field() ?>
    <div class="pf-check">
      <input type="checkbox" id="f-confirm" name="confirm" value="1" required>
      <label for="f-confirm"><?= h(__('Yes, I want to request the deletion of my account and my imprint profiles.')) ?></label>
    </div>
    <div class="pf-actions"><button class="pf-btn pf-btn--danger" type="submit"><?= h(__('Request account deletion')) ?></button></div>
  </form>
<?php endif; ?>
</div>
