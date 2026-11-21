<?php
/**
 * @var array<string,string> $values @var array<string,string> $errors @var bool $limitReached @var int $maxProfiles @var string $baseUrl
 * @var array<int,array<string,mixed>> $packages
 */
defined('TCD_APP') || exit;
?>
<p><a href="/portal/dashboard.php">&larr; <?= h(__('Dashboard')) ?></a></p>
<div class="pf-card pf-card--accent pf-wide-form">
  <h1><?= h(__('Create imprint profile')) ?></h1>
<?php if ($limitReached): ?>
  <div class="pf-notice"><?= h(sprintf(__('You have reached the maximum of %d imprint profiles. Please contact us if you need more.'), $maxProfiles)) ?></div>
  <p><a class="pf-btn" href="/portal/dashboard.php"><?= h(__('Back to the dashboard')) ?></a></p>
<?php else: ?>
  <div class="pf-notice pf-notice--info">
    <?= h(__('Imprint Protection is for content creators and streamers.')) ?>
    <?= h(__('Do you run an online shop? Imprint protection for shops is available on request only.')) ?>
    <a href="/shops/"><?= h(__('Request it for your shop')) ?></a>.
  </div>
  <p class="pf-muted"><?= h(__('First choose how your imprint is called and where it will be published. In the next step you add your details, links and private information.')) ?></p>
  <div class="pf-notice pf-notice--info" id="identity-hint">
    <strong><?= h(__('Good to know: you will need a photo of your ID card.')) ?></strong>
    <?= h(__('Before your first submission we ask for a photo of your ID card (front and back) to verify your name and address. It is encrypted when you upload it, only authorised staff can open it for the check, and afterwards it is stored only encrypted on a separate secure storage until you end the service.')) ?>
    <a href="/privacy/"><?= h(__('Privacy Policy')) ?></a>
  </div>
  <form class="pf-form" method="post" action="/portal/profile-new.php">
    <?= csrf_field() ?>
    <div class="pf-field">
      <label for="f-display_name"><?= h(__('Public name')) ?></label>
      <input type="text" id="f-display_name" name="display_name" value="<?= h($values['display_name']) ?>" maxlength="60" required<?= portal_invalid($errors, 'display_name') ?>>
      <div class="pf-help"><?= h(__('The name shown in your imprint, e.g. your channel or brand name (2–60 characters).')) ?></div>
      <?= portal_err($errors, 'display_name') ?>
    </div>
    <div class="pf-field">
      <label for="f-kind"><?= h(__('Type')) ?></label>
      <select id="f-kind" name="kind"<?= portal_invalid($errors, 'kind') ?>>
        <?php foreach (imprint_creator_kinds() as $key => $label): ?>
          <option value="<?= h($key) ?>"<?= $values['kind'] === $key ? ' selected' : '' ?>><?= h(__($label)) ?></option>
        <?php endforeach; ?>
      </select>
      <?= portal_err($errors, 'kind') ?>
    </div>
    <div class="pf-field">
      <label for="f-slug"><?= h(__('Page name')) ?></label>
      <input type="text" id="f-slug" name="slug" value="<?= h($values['slug']) ?>" maxlength="60" autocapitalize="none" spellcheck="false"<?= portal_invalid($errors, 'slug') ?>>
      <div class="pf-help"><?= h(__('Your imprint will be available at:')) ?> <code><?= h($baseUrl) ?><?= h($values['slug'] !== '' ? $values['slug'] : __('your-page-name')) ?>/</code>.
        <?= h(__('Leave empty to use a suggestion based on your public name. Lower-case letters, digits and hyphens only.')) ?></div>
      <?= portal_err($errors, 'slug') ?>
    </div>
<?php if ($packages !== []): ?>
    <?= render_partial('portal/_package-choice', ['packages' => $packages, 'selected' => $values['package'], 'errors' => $errors,
        'help' => __('You can change the package of this imprint later.')]) ?>
<?php endif; ?>
    <div class="pf-actions">
      <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Create and continue')) ?></button>
      <a class="pf-btn pf-btn--ghost" href="/portal/dashboard.php"><?= h(__('Cancel')) ?></a>
    </div>
  </form>
<?php endif; ?>
</div>
