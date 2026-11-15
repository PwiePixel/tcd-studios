<?php
/** @var string $token @var bool $valid @var string $error */
defined('TCD_APP') || exit;
?>
<div class="pf-narrow">
  <div class="pf-card pf-card--accent">
<?php if (!$valid): ?>
    <h1><?= h(__('Link not valid')) ?></h1>
    <p><?= h(__('This link is invalid or has expired. Please request a new one.')) ?></p>
    <p><a class="pf-btn pf-btn--primary" href="/portal/forgot.php"><?= h(__('Request a new link')) ?></a></p>
<?php else: ?>
    <h1><?= h(__('Choose a new password')) ?></h1>
    <?php if ($error !== ''): ?>
      <div class="pf-flash pf-flash--error" role="alert"><?= h($error) ?></div>
    <?php endif; ?>
    <form class="pf-form" method="post" action="/portal/reset.php">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <div class="pf-field">
        <label for="f-password"><?= h(__('New password')) ?></label>
        <input type="password" id="f-password" name="password" autocomplete="new-password" minlength="10" required>
        <div class="pf-help"><?= h(sprintf(__('At least %d characters.'), (int) config('password_min_length', 10))) ?></div>
      </div>
      <div class="pf-field">
        <label for="f-password2"><?= h(__('Repeat new password')) ?></label>
        <input type="password" id="f-password2" name="password2" autocomplete="new-password" required>
      </div>
      <div class="pf-actions">
        <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Change password')) ?></button>
      </div>
    </form>
<?php endif; ?>
  </div>
</div>
