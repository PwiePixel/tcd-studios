<?php
/** @var string $error @var bool $sent */
defined('TCD_APP') || exit;
?>
<div class="pf-card pf-card--accent pf-narrow">
  <h1><?= h(__('Forgot password')) ?></h1>
  <?php if ($sent): ?>
    <div class="pf-notice pf-notice--ok"><?= h(__('If an admin account exists for this address, we have sent a link to reset the password. It is valid for one hour.')) ?></div>
    <p><a href="/admin/"><?= h(__('Back to sign in')) ?></a></p>
  <?php else: ?>
    <?php if ($error !== ''): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form class="pf-form" method="post" action="/admin/forgot.php">
      <?= csrf_field() ?>
      <div class="pf-field">
        <label for="email"><?= h(__('E-mail')) ?></label>
        <input type="email" id="email" name="email" required maxlength="254" autocomplete="username" autofocus>
      </div>
      <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Send reset link')) ?></button>
        <a class="pf-btn pf-btn--ghost" href="/admin/"><?= h(__('Cancel')) ?></a></div>
    </form>
  <?php endif; ?>
</div>
