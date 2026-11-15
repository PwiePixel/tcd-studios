<?php
/** @var string $error @var string $token */
defined('TCD_APP') || exit;
?>
<div class="pf-card pf-card--accent pf-narrow">
  <h1><?= h(__('Choose a new password')) ?></h1>
  <?php if ($error !== ''): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form class="pf-form" method="post" action="/admin/reset.php">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= h($token) ?>">
    <div class="pf-field">
      <label for="password"><?= h(__('New password')) ?></label>
      <input type="password" id="password" name="password" required autocomplete="new-password" autofocus>
      <div class="pf-help"><?= h(sprintf(__('At least %d characters.'), (int) config('password_min_length', 10))) ?></div>
    </div>
    <div class="pf-field">
      <label for="password2"><?= h(__('Repeat password')) ?></label>
      <input type="password" id="password2" name="password2" required autocomplete="new-password">
    </div>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Save password')) ?></button></div>
  </form>
</div>
