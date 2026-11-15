<?php
/** @var bool $ok */
defined('TCD_APP') || exit;
?>
<div class="pf-narrow">
  <div class="pf-card pf-card--accent">
<?php if ($ok): ?>
    <h1><?= h(__('E-mail confirmed')) ?></h1>
    <p><?= h(__('Thank you! Your e-mail address is confirmed and your account is active. You can sign in now.')) ?></p>
    <p><a class="pf-btn pf-btn--primary" href="/portal/login.php"><?= h(__('Sign in')) ?></a></p>
<?php else: ?>
    <h1><?= h(__('Link not valid')) ?></h1>
    <p><?= h(__('This confirmation link is invalid, has expired or was already used. If you have not confirmed your address yet, please register again and we will send you a new link. If you already confirmed it, simply sign in.')) ?></p>
    <p class="pf-row">
      <a class="pf-btn" href="/portal/login.php"><?= h(__('Sign in')) ?></a>
      <a class="pf-btn" href="/portal/register.php"><?= h(__('Register again')) ?></a>
    </p>
<?php endif; ?>
  </div>
</div>
