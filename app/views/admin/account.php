<?php
/**
 * @var array<string,mixed> $admin @var array<string,string> $errors @var bool $totpActive @var string[] $recoveryCodes
 * @var int $recoveryLeft @var string $setupSecret @var string $setupUri
 */
defined('TCD_APP') || exit;
?>
<h1><?= h(__('My account')) ?></h1>
<?= render_partial('admin/_subnav', ['current' => 'account.php']) ?>

<?php if ($recoveryCodes): ?>
<section class="pf-card pf-card--accent" id="recovery">
  <h2><?= h(__('Your recovery codes')) ?></h2>
  <div class="pf-notice"><strong><?= h(__('Save these codes now.')) ?></strong>
    <?= h(__('They are shown only once. Each code works one time if you lose access to your authenticator app. Store them in a password manager or print them.')) ?></div>
  <ul class="pf-codes">
    <?php foreach ($recoveryCodes as $c): ?><li><code class="pf-hint-code"><?= h($c) ?></code></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<div class="pf-grid pf-grid--2">
  <section class="pf-card">
    <h2><?= h(__('Account')) ?></h2>
    <dl class="pf-kv">
      <dt><?= h(__('Name')) ?></dt><dd><?= h($admin['name']) ?></dd>
      <dt><?= h(__('E-mail')) ?></dt><dd><?= h($admin['email']) ?></dd>
      <dt><?= h(__('Last sign-in')) ?></dt><dd><?= h(fmt_time($admin['last_login_at'] !== null ? (int) $admin['last_login_at'] : null)) ?></dd>
      <dt><?= h(__('2FA')) ?></dt><dd><?= $totpActive ? '<span class="pf-badge pf-badge--ok">' . h(__('on')) . '</span>' : '<span class="pf-badge pf-badge--pending">' . h(__('off')) . '</span>' ?></dd>
    </dl>
  </section>

  <section class="pf-card">
    <h2><?= h(__('Change password')) ?></h2>
    <?php if (!empty($errors['password'])): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($errors['password']) ?></div><?php endif; ?>
    <form class="pf-form" method="post" action="/admin/account.php">
      <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
      <div class="pf-field"><label for="current_password"><?= h(__('Current password')) ?></label><input type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
      <div class="pf-field"><label for="new_password"><?= h(__('New password')) ?></label><input type="password" id="new_password" name="new_password" required autocomplete="new-password">
        <div class="pf-help"><?= h(sprintf(__('At least %d characters.'), (int) config('password_min_length', 10))) ?></div></div>
      <div class="pf-field"><label for="new_password2"><?= h(__('Repeat new password')) ?></label><input type="password" id="new_password2" name="new_password2" required autocomplete="new-password"></div>
      <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Change password')) ?></button></div>
    </form>
  </section>
</div>

<section class="pf-card" id="totp">
  <h2><?= h(__('Two-factor authentication (2FA)')) ?></h2>
  <?php if ($totpActive): ?>
    <p><?= h(__('Sign-in requires a 6-digit code from your authenticator app.')) ?>
      <?= h(sprintf(__('You have %d unused recovery code(s).'), $recoveryLeft)) ?></p>
    <?php if (!empty($errors['totp_manage'])): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($errors['totp_manage']) ?></div><?php endif; ?>
    <form class="pf-form pf-wide-form" method="post" action="/admin/account.php#totp">
      <?= csrf_field() ?>
      <p class="pf-help"><?= h(__('To switch 2FA off or to create new recovery codes, confirm with your password and a current code.')) ?></p>
      <div class="pf-grid pf-grid--2">
        <div class="pf-field"><label for="m_password"><?= h(__('Password')) ?></label><input type="password" id="m_password" name="password" required autocomplete="current-password"></div>
        <div class="pf-field"><label for="m_code"><?= h(__('Code')) ?></label><input type="text" id="m_code" name="code" required maxlength="20" inputmode="numeric" autocomplete="one-time-code"></div>
      </div>
      <div class="pf-actions">
        <button class="pf-btn" type="submit" name="action" value="totp_regen"><?= h(__('Create new recovery codes')) ?></button>
        <button class="pf-btn pf-btn--danger" type="submit" name="action" value="totp_disable"><?= h(__('Switch 2FA off')) ?></button>
      </div>
    </form>
  <?php elseif ($setupSecret !== ''): ?>
    <ol class="pf-steps">
      <li><?= h(__('Open an authenticator app (e.g. Aegis, Google Authenticator, 1Password, Microsoft Authenticator) and add a new account with "Enter a setup key".')) ?></li>
      <li><?= h(__('Enter this key (time based, 6 digits, 30 seconds):')) ?><br><code class="pf-hint-code pf-secret"><?= h(totp_secret_pretty($setupSecret)) ?></code>
        <br><span class="pf-help"><?= h(__('Or, if your app can import a link, use this otpauth:// address:')) ?></span><br><code class="pf-qr"><?= h($setupUri) ?></code></li>
      <li><?= h(__('Enter the code your app shows to finish the setup.')) ?></li>
    </ol>
    <?php if (!empty($errors['totp'])): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($errors['totp']) ?></div><?php endif; ?>
    <form class="pf-form pf-wide-form" method="post" action="/admin/account.php#totp">
      <?= csrf_field() ?>
      <div class="pf-field"><label for="code"><?= h(__('Code from the app')) ?></label><input type="text" id="code" name="code" required maxlength="20" inputmode="numeric" autocomplete="one-time-code" autofocus></div>
      <div class="pf-actions">
        <button class="pf-btn pf-btn--primary" type="submit" name="action" value="totp_enable"><?= h(__('Turn on 2FA')) ?></button>
        <button class="pf-btn pf-btn--ghost" type="submit" name="action" value="totp_cancel" formnovalidate><?= h(__('Cancel')) ?></button>
      </div>
    </form>
  <?php else: ?>
    <p><?= h(__('Protect your admin account with a second factor: an authenticator app that creates a new 6-digit code every 30 seconds. Recommended for every admin.')) ?></p>
    <form class="pf-form" method="post" action="/admin/account.php#totp"><?= csrf_field() ?><input type="hidden" name="action" value="totp_start">
      <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Set up 2FA')) ?></button></form>
  <?php endif; ?>
</section>
