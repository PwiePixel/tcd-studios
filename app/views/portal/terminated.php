<?php
defined('TCD_APP') || exit;
?>
<div class="pf-narrow">
  <div class="pf-card pf-card--accent" id="terminated">
    <h1><?= h(__('The service has ended')) ?></h1>
    <div class="pf-notice pf-notice--ok">
      <strong><?= h(__('Your data has been deleted.')) ?></strong>
      <?= h(__('As you confirmed, Imprint Protection has ended and everything was deleted automatically and irrevocably.')) ?>
    </div>
    <ul class="pf-list">
      <li><?= h(__('Your imprint pages are offline.')) ?></li>
      <li><?= h(__('Your account, imprint profiles and address data are deleted.')) ?></li>
      <li><?= h(__('Your ID photos are deleted from our web server and from the secure storage system.')) ?></li>
      <li><?= h(__('You have been signed out. You may no longer use the service.')) ?></li>
    </ul>
    <p class="pf-muted"><?= h(__('We sent a short confirmation to your e-mail address. Thank you for using TCD-Studio/Agency.')) ?></p>
    <p><a class="pf-btn pf-btn--primary" href="/"><?= h(__('Back to the website')) ?></a></p>
  </div>
</div>
