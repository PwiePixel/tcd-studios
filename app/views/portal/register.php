<?php
/** @var array<string,string> $values @var array<string,string> $errors @var bool $done @var array<string,mixed>|null $package */
defined('TCD_APP') || exit;
?>
<div class="pf-narrow">
<?php if ($done): ?>
  <div class="pf-card pf-card--accent">
    <h1><?= h(__('Check your inbox')) ?></h1>
    <p><?= h(__('If this e-mail address can be registered, we have just sent a confirmation link to it. Please click the link to activate your account.')) ?></p>
    <p class="pf-muted"><?= h(__('The link is valid for 48 hours. If nothing arrives within a few minutes, please look into your spam folder.')) ?></p>
    <p><a class="pf-btn" href="/portal/login.php"><?= h(__('Go to sign in')) ?></a></p>
  </div>
<?php else: ?>
  <div class="pf-card pf-card--accent">
    <h1><?= h(__('Create your account')) ?></h1>
    <p class="pf-muted"><?= h(__('With a free account, content creators and streamers can set up their protected imprint and follow its status.')) ?></p>
    <?php if ($package !== null): ?>
      <div class="pf-notice pf-notice--info" id="package-choice">
        <?= h(sprintf(__('Your package: %s.'), (string) $package['name'])) ?>
        <?= h(__('After you have confirmed your e-mail address and signed in, you create your imprint profile with this package.')) ?>
        <a href="/imprint-protection/#packages"><?= h(__('Compare the packages')) ?></a>
      </div>
    <?php endif; ?>
    <?php if (!empty($errors['form'])): ?>
      <div class="pf-flash pf-flash--error" role="alert"><?= h($errors['form']) ?></div>
    <?php endif; ?>
    <form class="pf-form" method="post" action="/portal/register.php">
      <?= csrf_field() ?>
      <div class="pf-field">
        <label for="f-name"><?= h(__('Your name')) ?></label>
        <input type="text" id="f-name" name="name" value="<?= h($values['name']) ?>" maxlength="80" autocomplete="name" required>
      </div>
      <div class="pf-field">
        <label for="f-email"><?= h(__('E-mail address')) ?></label>
        <input type="email" id="f-email" name="email" value="<?= h($values['email']) ?>" maxlength="254" autocomplete="email" required>
      </div>
      <div class="pf-field">
        <label for="f-password"><?= h(__('Password')) ?></label>
        <input type="password" id="f-password" name="password" autocomplete="new-password" minlength="10" required>
        <div class="pf-help"><?= h(sprintf(__('At least %d characters.'), (int) config('password_min_length', 10))) ?></div>
      </div>
      <div class="pf-field">
        <label for="f-password2"><?= h(__('Repeat password')) ?></label>
        <input type="password" id="f-password2" name="password2" autocomplete="new-password" required<?= portal_invalid($errors, 'password2') ?>>
        <?= portal_err($errors, 'password2') ?>
      </div>
      <div class="pf-hp" aria-hidden="true">
        <label for="f-website-url"><?= h(__('Please leave this field empty')) ?></label>
        <input type="text" id="f-website-url" name="website_url" value="" tabindex="-1" autocomplete="off">
      </div>
      <div class="pf-check">
        <input type="checkbox" id="f-privacy" name="privacy" value="1"<?= $values['privacy'] !== '' ? ' checked' : '' ?> required<?= portal_invalid($errors, 'privacy') ?>>
        <label for="f-privacy"><?= h(__('I have read the')) ?> <a href="/privacy/" target="_blank" rel="noopener"><?= h(__('privacy policy')) ?></a>.</label>
      </div>
      <?= portal_err($errors, 'privacy') ?>
      <div class="pf-check">
        <input type="checkbox" id="f-accept_terms" name="accept_terms" value="1"<?= $values['accept_terms'] !== '' ? ' checked' : '' ?> required<?= portal_invalid($errors, 'accept_terms') ?>>
        <label for="f-accept_terms"><?= h(__('I am a content creator and I accept the')) ?>
          <a href="/agb/" target="_blank" rel="noopener"><?= h(__('Terms and Conditions (AGB)')) ?></a>
          (<a href="/terms/" target="_blank" rel="noopener"><?= h(__('English convenience translation')) ?></a>).</label>
      </div>
      <?= portal_err($errors, 'accept_terms') ?>
      <div class="pf-actions">
        <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Create account')) ?></button>
      </div>
    </form>
    <p class="pf-muted pf-small"><?= h(__('Already have an account?')) ?> <a href="/portal/login.php"><?= h(__('Sign in')) ?></a></p>
  </div>
<?php endif; ?>
</div>
