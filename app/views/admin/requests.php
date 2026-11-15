<?php
/** @var array<int,array<string,mixed>> $requests @var string $status @var string $service @var string[] $slugs */
defined('TCD_APP') || exit;
?>
<h1><?= h(__('Requests')) ?></h1>
<section class="pf-card">
  <form class="pf-row pf-filter" method="get" action="/admin/requests.php">
    <div class="pf-field pf-field--inline"><label for="status"><?= h(__('Status')) ?></label>
      <select id="status" name="status"><option value=""><?= h(__('All')) ?></option>
        <?php foreach (admin_request_statuses() as $s): ?><option value="<?= h($s) ?>"<?= $status === $s ? ' selected' : '' ?>><?= h(status_label($s)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="pf-field pf-field--inline"><label for="service"><?= h(__('Service')) ?></label>
      <select id="service" name="service"><option value=""><?= h(__('All')) ?></option>
        <?php foreach ($slugs as $s): ?><option value="<?= h($s) ?>"<?= $service === $s ? ' selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?>
      </select></div>
    <div class="pf-field pf-field--inline"><button class="pf-btn" type="submit"><?= h(__('Filter')) ?></button>
      <a class="pf-btn pf-btn--ghost" href="/admin/requests.php"><?= h(__('Reset')) ?></a></div>
  </form>
</section>
<section class="pf-card">
<?php if (!$requests): ?>
  <p class="pf-muted"><?= h(__('No requests found.')) ?></p>
<?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th>#</th><th><?= h(__('From')) ?></th><th><?= h(__('Service')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('Received')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($requests as $q): $special = in_array($q['service_slug'], ['shops', 'account-deletion'], true); ?>
      <tr class="<?= $special ? 'pf-row-highlight' : '' ?>">
        <td><?= (int) $q['id'] ?></td>
        <td><a href="/admin/request.php?id=<?= (int) $q['id'] ?>"><?= h($q['name'] !== '' ? $q['name'] : $q['email']) ?></a><br><span class="pf-muted pf-small"><?= h($q['email']) ?></span></td>
        <td><?= h($q['service_slug']) ?>
          <?= $q['service_slug'] === 'shops' ? ' <span class="pf-badge pf-badge--info">' . h(__('shop request')) . '</span>' : '' ?>
          <?= $q['service_slug'] === 'account-deletion' ? ' <span class="pf-badge pf-badge--rejected">' . h(__('deletion request')) . '</span>' : '' ?></td>
        <td><?= status_badge((string) $q['status']) ?></td>
        <td><?= h(fmt_time((int) $q['created_at'])) ?> <span class="pf-muted pf-small">(<?= h(admin_age((int) $q['created_at'])) ?>)</span></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small" href="/admin/request.php?id=<?= (int) $q['id'] ?>"><?= h(__('Open')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="pf-muted pf-small"><?= (int) count($requests) ?> <?= h(__('result(s)')) ?></p>
<?php endif; ?>
</section>
