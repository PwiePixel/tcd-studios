<?php
/** @var string $error @var string $email */
defined('TCD_APP') || exit;
?>
<div class="pf-login-card">
  <img class="pf-login-logo" src="/images/218/16332534/TCD-Studio-LogoohneIcon-wagerecht-x0y5LHULFifqLQb0SZXRUw.png" alt="TCD-Studio/Agency" width="200">
  <h1><?= h(__('Admin sign in')) ?></h1>
  <p class="pf-login-sub"><?= h(__('Imprint Protection · Admin panel')) ?></p>
  <?php if ($error !== ''): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form class="pf-form" method="post" action="/admin/">
    <?= csrf_field() ?>
    <div class="pf-field">
      <label for="email"><?= h(__('E-mail')) ?></label>
      <input type="email" id="email" name="email" required maxlength="254" value="<?= h($email) ?>" autocomplete="username" autofocus>
    </div>
    <div class="pf-field">
      <label for="password"><?= h(__('Password')) ?></label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary pf-login-submit" type="submit"><?= h(__('Sign in')) ?></button></div>
  </form>
  <p class="pf-help pf-login-forgot"><a href="/admin/forgot.php"><?= h(__('Forgot your password?')) ?></a></p>
</div>
