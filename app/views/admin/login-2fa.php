<?php
/** @var string $error */
defined('TCD_APP') || exit;
?>
<div class="pf-card pf-card--accent pf-narrow">
  <h1><?= h(__('Two-factor authentication')) ?></h1>
  <p><?= h(__('Enter the 6-digit code from your authenticator app, or one of your one-time recovery codes.')) ?></p>
  <?php if ($error !== ''): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form class="pf-form" method="post" action="/admin/login-2fa.php">
    <?= csrf_field() ?>
    <div class="pf-field">
      <label for="code"><?= h(__('Code')) ?></label>
      <input type="text" id="code" name="code" required maxlength="20" inputmode="numeric" autocomplete="one-time-code" autocapitalize="off" spellcheck="false" autofocus>
    </div>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Verify')) ?></button>
      <a class="pf-btn pf-btn--ghost" href="/admin/login.php"><?= h(__('Cancel')) ?></a></div>
  </form>
</div>
