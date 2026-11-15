<?php
/** @var array<string,mixed> $profile @var array<string,mixed> $s */
defined('TCD_APP') || exit;
?>
<p><a href="/portal/profile.php?id=<?= (int) $profile['id'] ?>">&larr; <?= h($s['name']) ?></a></p>
<div class="pf-card pf-card--accent pf-wide-form">
  <h1><?= h(__('Take imprint offline')) ?></h1>
  <p><?= h(sprintf(__('Your imprint page %s will be removed from the website immediately.'), imprint_public_url((string) $profile['slug']))) ?></p>
  <div class="pf-notice">
    <?= h(__('Please make sure that you are not legally required to have an imprint online for your channels. To publish the imprint again later, you need to create a new draft, submit it and wait for the approval of our team.')) ?>
  </div>
  <form class="pf-form" method="post" action="/portal/profile-offline.php">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $profile['id'] ?>">
    <div class="pf-check">
      <input type="checkbox" id="f-confirm" name="confirm" value="1" required>
      <label for="f-confirm"><?= h(__('Yes, take this imprint offline.')) ?></label>
    </div>
    <div class="pf-actions">
      <button class="pf-btn pf-btn--danger" type="submit"><?= h(__('Take offline')) ?></button>
      <a class="pf-btn pf-btn--ghost" href="/portal/profile.php?id=<?= (int) $profile['id'] ?>"><?= h(__('Cancel')) ?></a>
    </div>
  </form>
</div>
