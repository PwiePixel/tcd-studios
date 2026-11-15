<?php
/**
 * @var bool $accepted @var array{version:?string,at:?int,current:bool} $info @var string $version @var array<string,string> $errors
 */
defined('TCD_APP') || exit;
?>
<div class="pf-narrow">
  <div class="pf-card pf-card--accent">
    <h1><?= h(__('Terms and Conditions')) ?></h1>
<?php if ($accepted): ?>
    <div class="pf-notice pf-notice--ok">
      <?= h(sprintf(__('You have accepted the current Terms and Conditions (version %s) on %s.'), $version, fmt_time($info['at']))) ?>
    </div>
    <p>
      <a href="/agb/" target="_blank" rel="noopener"><?= h(__('Terms and Conditions (AGB)')) ?></a> ·
      <a href="/terms/" target="_blank" rel="noopener"><?= h(__('English convenience translation')) ?></a>
    </p>
    <p><a class="pf-btn pf-btn--primary" href="/portal/dashboard.php"><?= h(__('Back to the dashboard')) ?></a></p>
<?php else: ?>
    <div class="pf-notice">
      <?php if ($info['version'] !== null): ?>
        <strong><?= h(__('We updated our Terms and Conditions.')) ?></strong>
        <?= h(sprintf(__('You last accepted version %s on %s.'), $info['version'], fmt_time($info['at']))) ?>
        <?= h(sprintf(__('The current version is %s.'), $version)) ?>
      <?php else: ?>
        <strong><?= h(__('Please accept our Terms and Conditions.')) ?></strong>
        <?= h(__('Imprint Protection is available to content creators and streamers. Before you create or submit an imprint, please read and accept the Terms and Conditions.')) ?>
      <?php endif; ?>
    </div>
    <p><?= h(__('Please read the current Terms and Conditions:')) ?></p>
    <ul class="pf-list">
      <li><a href="/agb/" target="_blank" rel="noopener"><?= h(__('Terms and Conditions (AGB)')) ?></a></li>
      <li><a href="/terms/" target="_blank" rel="noopener"><?= h(__('English convenience translation')) ?></a></li>
    </ul>
    <p class="pf-muted pf-small"><?= h(__('Your existing drafts and published imprints stay as they are, and you can keep editing drafts. Creating a new imprint profile and submitting an imprint for review requires the current acceptance. We record the time and the version of your acceptance.')) ?></p>
    <form class="pf-form" method="post" action="/portal/terms.php">
      <?= csrf_field() ?>
      <div class="pf-check">
        <input type="checkbox" id="f-accept_terms" name="accept_terms" value="1" required<?= portal_invalid($errors, 'accept_terms') ?>>
        <label for="f-accept_terms"><?= h(__('I am a content creator and I accept the current Terms and Conditions.')) ?></label>
      </div>
      <?= portal_err($errors, 'accept_terms') ?>
      <div class="pf-actions">
        <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Accept and continue')) ?></button>
        <a class="pf-btn pf-btn--ghost" href="/portal/dashboard.php"><?= h(__('Not now')) ?></a>
      </div>
    </form>
<?php endif; ?>
  </div>
</div>
