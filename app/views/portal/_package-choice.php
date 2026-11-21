<?php
/**
 * Package choice (radio cards) of the imprint profile forms (profile-new, profile-edit). Works without JavaScript.
 *
 * @var array<int,array<string,mixed>> $packages choosable packages
 * @var string $selected slug of the selected package ('' = none)
 * @var array<string,string> $errors
 * @var string $help optional help text under the cards
 */
defined('TCD_APP') || exit;
?>
<fieldset class="pf-fieldset pf-packages-field" id="package">
  <legend><?= h(__('Package')) ?></legend>
  <div class="pf-packages">
<?php foreach ($packages as $p): $slug = (string) $p['slug']; [$price, $price2] = package_price_lines($p); $isSel = $selected === $slug; ?>
    <label class="pf-package<?= $isSel ? ' is-selected' : '' ?><?= $p['badge'] !== '' ? ' pf-package--badge' : '' ?>" for="pkg-<?= h($slug) ?>">
      <span class="pf-package-head">
        <input type="radio" id="pkg-<?= h($slug) ?>" name="package" value="<?= h($slug) ?>"<?= $isSel ? ' checked' : '' ?> required>
        <span class="pf-package-name"><?= h($p['name']) ?></span>
<?php if ($p['badge'] !== ''): ?>
        <span class="pf-badge pf-badge--info pf-package-badge"><?= h($p['badge']) ?></span>
<?php endif; ?>
<?php if ($p['active'] !== 1): ?>
        <span class="pf-badge pf-badge--draft"><?= h(__('no longer offered')) ?></span>
<?php endif; ?>
      </span>
      <span class="pf-package-price"><?= h($price) ?></span>
<?php if ($price2 !== ''): ?>
      <span class="pf-package-price2"><?= h($price2) ?></span>
<?php endif; ?>
<?php if ($p['tagline'] !== ''): ?>
      <span class="pf-package-tagline"><?= h($p['tagline']) ?></span>
<?php endif; ?>
      <span class="pf-package-limit"><?= h(sprintf($p['max_profiles'] === 1 ? __('%d imprint profile') : __('Up to %d imprint profiles'), min($p['max_profiles'], package_global_limit()))) ?></span>
<?php foreach ($p['features'] as $f): ?>
      <span class="pf-package-feature"><?= h($f) ?></span>
<?php endforeach; ?>
<?php if ($p['extras'] !== []): ?>
      <span class="pf-package-extras-title"><?= h(__('Additional, depending on use')) ?></span>
<?php foreach ($p['extras'] as $f): ?>
      <span class="pf-package-extra"><?= h($f) ?></span>
<?php endforeach; ?>
<?php endif; ?>
    </label>
<?php endforeach; ?>
  </div>
  <div class="pf-help"><?= h(packages_note()) ?> <a href="/imprint-protection/#packages"><?= h(__('Compare the packages')) ?></a></div>
<?php if (!empty($help)): ?>
  <div class="pf-help"><?= h($help) ?></div>
<?php endif; ?>
  <?= portal_err($errors, 'package') ?>
</fieldset>
