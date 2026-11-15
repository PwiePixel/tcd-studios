<?php
/** @var array<int,array<string,mixed>> $customers @var string $q @var string $status */
defined('TCD_APP') || exit;
?>
<h1><?= h(__('Customers')) ?></h1>
<section class="pf-card">
  <form class="pf-row pf-filter" method="get" action="/admin/customers.php">
    <div class="pf-field pf-field--inline"><label for="q"><?= h(__('Search')) ?></label>
      <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="<?= h(__('name or e-mail')) ?>"></div>
    <div class="pf-field pf-field--inline"><label for="status"><?= h(__('Status')) ?></label>
      <select id="status" name="status"><option value=""><?= h(__('All')) ?></option>
        <?php foreach (['pending', 'active', 'disabled'] as $s): ?><option value="<?= h($s) ?>"<?= $status === $s ? ' selected' : '' ?>><?= h(status_label($s)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="pf-field pf-field--inline"><button class="pf-btn" type="submit"><?= h(__('Filter')) ?></button>
      <a class="pf-btn pf-btn--ghost" href="/admin/customers.php"><?= h(__('Reset')) ?></a></div>
  </form>
</section>
<section class="pf-card">
<?php if (!$customers): ?>
  <p class="pf-muted"><?= h(__('No customers found.')) ?></p>
<?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Name')) ?></th><th><?= h(__('E-mail')) ?></th><th><?= h(__('Status')) ?></th><th class="pf-num"><?= h(__('Imprints')) ?></th><th><?= h(__('Registered')) ?></th><th><?= h(__('Last sign-in')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($customers as $u): ?>
      <tr>
        <td><a href="/admin/customer.php?id=<?= (int) $u['id'] ?>"><?= h($u['name']) ?></a></td>
        <td><?= h($u['email']) ?></td>
        <td><?= status_badge((string) $u['status']) ?></td>
        <td class="pf-num"><?= (int) $u['profile_count'] ?></td>
        <td><?= h(fmt_time((int) $u['created_at'])) ?></td>
        <td><?= h(fmt_time($u['last_login_at'] !== null ? (int) $u['last_login_at'] : null)) ?></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small" href="/admin/customer.php?id=<?= (int) $u['id'] ?>"><?= h(__('Open')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="pf-muted pf-small"><?= (int) count($customers) ?> <?= h(__('result(s)')) ?></p>
<?php endif; ?>
</section>
