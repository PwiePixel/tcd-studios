<?php
/** @var string $email @var string $error */
defined('TCD_APP') || exit;
?>
<div class="pf-narrow">
  <div class="pf-card pf-card--accent">
    <h1><?= h(__('Sign in')) ?></h1>
    <p class="pf-muted"><?= h(__('Customer area for Imprint Protection and our services.')) ?></p>
    <?php if ($error !== ''): ?>
      <div class="pf-flash pf-flash--error" role="alert"><?= h($error) ?></div>
    <?php endif; ?>
    <form class="pf-form" method="post" action="/portal/login.php">
      <?= csrf_field() ?>
      <div class="pf-field">
        <label for="f-email"><?= h(__('E-mail address')) ?></label>
        <input type="email" id="f-email" name="email" value="<?= h($email) ?>" maxlength="254" autocomplete="username" required>
      </div>
      <div class="pf-field">
        <label for="f-password"><?= h(__('Password')) ?></label>
        <input type="password" id="f-password" name="password" autocomplete="current-password" required>
      </div>
      <div class="pf-actions">
        <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Sign in')) ?></button>
      </div>
    </form>
    <p class="pf-small"><a href="/portal/forgot.php"><?= h(__('Forgot your password?')) ?></a></p>
    <p class="pf-muted pf-small"><?= h(__('New here?')) ?> <a href="/portal/register.php"><?= h(__('Create an account')) ?></a></p>
  </div>
</div>
