<?php
/** @var array<string,string> $errors @var array<string,string> $values @var bool $tokenReady */
defined('TCD_APP') || exit;
?>
<div class="pf-card pf-card--accent pf-narrow">
  <h1><?= h(__('First-run setup')) ?></h1>
  <?php if (!$tokenReady): ?>
    <div class="pf-notice"><?= h(__('The setup token could not be created. Please make sure the data directory is writable by the web server and reload this page.')) ?></div>
  <?php else: ?>
  <p><?= h(__('Create the first admin account. To prove that you own this server, enter the setup token. You find it in the file setup-token.txt in the data directory of the installation (read it via FTP or SSH). The file is deleted automatically once the account exists.')) ?></p>
  <?php if (!empty($errors['form'])): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($errors['form']) ?></div><?php endif; ?>
  <form class="pf-form" method="post" action="/admin/setup.php" autocomplete="off">
    <?= csrf_field() ?>
    <div class="pf-field">
      <label for="token"><?= h(__('Setup token')) ?></label>
      <input type="text" id="token" name="token" required autocomplete="off" spellcheck="false"<?= !empty($errors['token']) ? ' aria-invalid="true"' : '' ?>>
      <?php if (!empty($errors['token'])): ?><div class="pf-error-text"><?= h($errors['token']) ?></div><?php endif; ?>
    </div>
    <div class="pf-field">
      <label for="name"><?= h(__('Your name')) ?></label>
      <input type="text" id="name" name="name" required maxlength="80" value="<?= h($values['name']) ?>" autocomplete="name">
      <?php if (!empty($errors['name'])): ?><div class="pf-error-text"><?= h($errors['name']) ?></div><?php endif; ?>
    </div>
    <div class="pf-field">
      <label for="email"><?= h(__('E-mail')) ?></label>
      <input type="email" id="email" name="email" required maxlength="254" value="<?= h($values['email']) ?>" autocomplete="username">
      <?php if (!empty($errors['email'])): ?><div class="pf-error-text"><?= h($errors['email']) ?></div><?php endif; ?>
    </div>
    <div class="pf-field">
      <label for="password"><?= h(__('Password')) ?></label>
      <input type="password" id="password" name="password" required autocomplete="new-password">
      <div class="pf-help"><?= h(sprintf(__('At least %d characters.'), (int) config('password_min_length', 10))) ?></div>
      <?php if (!empty($errors['password'])): ?><div class="pf-error-text"><?= h($errors['password']) ?></div><?php endif; ?>
    </div>
    <div class="pf-field">
      <label for="password2"><?= h(__('Repeat password')) ?></label>
      <input type="password" id="password2" name="password2" required autocomplete="new-password">
      <?php if (!empty($errors['password2'])): ?><div class="pf-error-text"><?= h($errors['password2']) ?></div><?php endif; ?>
    </div>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Create admin account')) ?></button></div>
  </form>
  <?php endif; ?>
</div>
