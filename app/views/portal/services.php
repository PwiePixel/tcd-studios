<?php
/** @var array<int,array<string,mixed>> $services @var array<string,array<string,mixed>> $sent */
defined('TCD_APP') || exit;
?>
<h1><?= h(__('Services')) ?></h1>
<p class="pf-muted"><?= h(__('What we offer for creators, streamers and shops.')) ?></p>
<div class="pf-grid pf-grid--2">
<?php foreach ($services as $svc): $slug = (string) $svc['slug']; ?>
  <div class="pf-card">
    <div class="pf-row pf-row--between">
      <h2><?= h($svc['name']) ?></h2>
      <?= status_badge((string) $svc['availability']) ?>
    </div>
    <p><?= h($svc['summary']) ?></p>
    <?php if ($svc['details'] !== ''): ?><p class="pf-muted"><?= h($svc['details']) ?></p><?php endif; ?>
    <?php if ($slug === 'shops'): ?>
      <div class="pf-notice"><?= h(__('In the launch phase the shop partnership (payments, tax settlement and payouts; we retain an agreed share) is available on request and in limited numbers only.')) ?></div>
    <?php endif; ?>
    <?php if (isset($sent[$slug])): ?>
      <p class="pf-small pf-muted"><?= h(sprintf(__('You sent a request on %s.'), portal_date((int) $sent[$slug]['created_at']))) ?> <?= status_badge((string) $sent[$slug]['status']) ?></p>
    <?php endif; ?>
    <div class="pf-actions">
    <?php if ($slug === 'imprint-protection'): ?>
      <a class="pf-btn pf-btn--primary" href="/portal/profile-new.php"><?= h(__('Create imprint profile')) ?></a>
    <?php elseif ((int) $svc['requestable'] === 1): ?>
      <a class="pf-btn pf-btn--primary" href="/portal/request.php?service=<?= h(rawurlencode($slug)) ?>"><?= h(__('Request')) ?></a>
    <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php if ($services === []): ?>
  <div class="pf-card"><p class="pf-muted"><?= h(__('There are no services available at the moment.')) ?></p></div>
<?php endif; ?>
