<?php
/** @var array<string,mixed> $customer @var array<int,array<string,mixed>> $profiles @var array<int,array<string,mixed>> $requests */
defined('TCD_APP') || exit;
$self = '/admin/customer.php?id=' . (int) $customer['id'];
$status = (string) $customer['status'];
?>
<p><a href="/admin/customers.php">&larr; <?= h(__('Customers')) ?></a></p>
<div class="pf-row pf-row--between">
  <h1><?= h($customer['name'] !== '' ? $customer['name'] : $customer['email']) ?></h1>
  <div><?= status_badge($status) ?></div>
</div>

<div class="pf-grid pf-grid--2">
  <section class="pf-card">
    <h2><?= h(__('Account')) ?></h2>
    <dl class="pf-kv">
      <dt><?= h(__('E-mail')) ?></dt><dd><?= h($customer['email']) ?></dd>
      <dt><?= h(__('E-mail confirmed')) ?></dt><dd><?= h(fmt_time($customer['email_verified_at'] !== null ? (int) $customer['email_verified_at'] : null)) ?></dd>
      <dt><?= h(__('Registered')) ?></dt><dd><?= h(fmt_time((int) $customer['created_at'])) ?></dd>
      <dt><?= h(__('Last sign-in')) ?></dt><dd><?= h(fmt_time($customer['last_login_at'] !== null ? (int) $customer['last_login_at'] : null)) ?></dd>
    </dl>
  </section>
  <section class="pf-card">
    <h2><?= h(__('Actions')) ?></h2>
    <div class="pf-stack">
    <?php if ($status !== 'disabled'): ?>
      <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="disable">
        <p class="pf-help"><?= h(__('A disabled customer cannot sign in. Published imprints stay online.')) ?></p>
        <button class="pf-btn pf-btn--danger" type="submit"><?= h(__('Disable account')) ?></button></form>
    <?php else: ?>
      <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="enable">
        <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Enable account')) ?></button></form>
    <?php endif; ?>
    <?php if ($status === 'pending'): ?>
      <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="activate">
        <p class="pf-help"><?= h(__('Skips the e-mail confirmation. Only do this if you have verified the address yourself.')) ?></p>
        <button class="pf-btn" type="submit"><?= h(__('Activate manually')) ?></button></form>
      <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="resend_verification">
        <button class="pf-btn" type="submit"><?= h(__('Resend confirmation e-mail')) ?></button></form>
    <?php endif; ?>
    </div>
  </section>
</div>

<section class="pf-card">
  <h2><?= h(__('Imprints')) ?></h2>
  <?php if (!$profiles): ?><p class="pf-muted"><?= h(__('This customer has no imprints.')) ?></p><?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Page')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('Updated')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($profiles as $p): ?>
      <tr><td>/legal/<?= h($p['slug']) ?>/</td><td><?= status_badge((string) $p['status']) ?></td><td><?= h(fmt_time((int) $p['updated_at'])) ?></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small" href="/admin/profile.php?id=<?= (int) $p['id'] ?>"><?= h(__('Open')) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="pf-card">
  <h2><?= h(__('Requests')) ?></h2>
  <?php if (!$requests): ?><p class="pf-muted"><?= h(__('This customer has not sent any requests.')) ?></p><?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Service')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('Sent')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($requests as $q): ?>
      <tr><td><?= h($q['service_slug']) ?></td><td><?= status_badge((string) $q['status']) ?></td><td><?= h(fmt_time((int) $q['created_at'])) ?></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small" href="/admin/request.php?id=<?= (int) $q['id'] ?>"><?= h(__('Open')) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
