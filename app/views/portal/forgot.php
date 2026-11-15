<?php
/** @var string $email @var string $error @var bool $done */
defined('TCD_APP') || exit;
?>
<div class="pf-narrow">
  <div class="pf-card pf-card--accent">
<?php if ($done): ?>
    <h1><?= h(__('Check your inbox')) ?></h1>
    <p><?= h(__('If an account exists for this e-mail address, we have sent a link to reset the password. It is valid for one hour.')) ?></p>
    <p><a class="pf-btn" href="/portal/login.php"><?= h(__('Back to sign in')) ?></a></p>
<?php else: ?>
    <h1><?= h(__('Forgot your password?')) ?></h1>
    <p class="pf-muted"><?= h(__('Enter the e-mail address of your account. We will send you a link to choose a new password.')) ?></p>
    <?php if ($error !== ''): ?>
      <div class="pf-flash pf-flash--error" role="alert"><?= h($error) ?></div>
    <?php endif; ?>
    <form class="pf-form" method="post" action="/portal/forgot.php">
      <?= csrf_field() ?>
      <div class="pf-field">
        <label for="f-email"><?= h(__('E-mail address')) ?></label>
        <input type="email" id="f-email" name="email" value="<?= h($email) ?>" maxlength="254" autocomplete="email" required>
      </div>
      <div class="pf-actions">
        <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Send reset link')) ?></button>
        <a class="pf-btn pf-btn--ghost" href="/portal/login.php"><?= h(__('Cancel')) ?></a>
      </div>
    </form>
<?php endif; ?>
  </div>
</div>
