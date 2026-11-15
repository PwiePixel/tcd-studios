<?php
/**
 * @var string $tab @var array<int,array<string,mixed>> $requests @var string $status @var string $service @var string[] $slugs
 * @var array<int,array<string,mixed>> $deletions @var string $dStatus @var array<string,int> $deletionCounts
 */
defined('TCD_APP') || exit;
$openDeletions = $deletionCounts['requested'] + $deletionCounts['confirmed'] + $deletionCounts['on_hold'];
?>
<h1><?= h(__('Requests')) ?></h1>
<nav class="pf-subnav pf-tabs" aria-label="<?= h(__('Request types')) ?>">
  <a href="/admin/requests.php"<?= $tab === 'requests' ? ' class="is-active" aria-current="page"' : '' ?>><?= h(__('Service requests')) ?></a>
  <a href="/admin/requests.php?tab=deletions"<?= $tab === 'deletions' ? ' class="is-active" aria-current="page"' : '' ?>><?= h(__('Deletion requests')) ?><?= $openDeletions > 0 ? ' <span class="pf-badge pf-badge--pending">' . (int) $openDeletions . '</span>' : '' ?></a>
</nav>

<?php if ($tab === 'deletions'): ?>
<p class="pf-muted"><?= h(__('Customers who ended the service. The data is erased automatically after their confirmation, unless a legal hold is set for the customer. Completed rows carry no personal data.')) ?></p>
<section class="pf-card">
  <form class="pf-row pf-filter" method="get" action="/admin/requests.php">
    <input type="hidden" name="tab" value="deletions">
    <div class="pf-field pf-field--inline"><label for="dstatus"><?= h(__('Status')) ?></label>
      <select id="dstatus" name="dstatus"><option value=""><?= h(__('All')) ?></option>
        <?php foreach (admin_deletion_statuses() as $s): ?><option value="<?= h($s) ?>"<?= $dStatus === $s ? ' selected' : '' ?>><?= h(admin_deletion_labels()[$s]) ?> (<?= (int) $deletionCounts[$s] ?>)</option><?php endforeach; ?>
      </select></div>
    <div class="pf-field pf-field--inline"><button class="pf-btn" type="submit"><?= h(__('Filter')) ?></button>
      <a class="pf-btn pf-btn--ghost" href="/admin/requests.php?tab=deletions"><?= h(__('Reset')) ?></a></div>
  </form>
</section>
<section class="pf-card">
<?php if (!$deletions): ?>
  <p class="pf-muted"><?= h(__('No deletion requests found.')) ?></p>
<?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table pf-table--deletions">
    <thead><tr><th>#</th><th><?= h(__('Customer')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('Requested')) ?></th><th><?= h(__('Confirmed')) ?></th><th><?= h(__('Completed')) ?></th><th><?= h(__('Result / hold')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($deletions as $d): $done = $d['status'] === 'completed'; $c = $d['counts']; ?>
      <tr class="<?= $d['status'] === 'on_hold' ? 'pf-row-highlight' : '' ?>">
        <td><?= (int) $d['id'] ?></td>
        <td><?php if ($d['user_id'] !== null && $d['user_email'] !== null): ?><a href="/admin/customer.php?id=<?= (int) $d['user_id'] ?>"><?= h($d['user_email']) ?></a>
          <?php else: ?><span class="pf-muted"><?= h($done ? sprintf(__('erased customer #%d'), (int) $d['user_ref']) : __('account no longer exists')) ?></span><?php endif; ?></td>
        <td><?= admin_deletion_badge((string) $d['status']) /* escaped badge HTML from the helper */ ?></td>
        <td><?= h(fmt_time((int) $d['created_at'])) ?></td>
        <td><?= h(fmt_time($d['confirmed_at'] !== null ? (int) $d['confirmed_at'] : null)) ?></td>
        <td><?= h(fmt_time($d['completed_at'] !== null ? (int) $d['completed_at'] : null)) ?></td>
        <td class="pf-small"><?php if ($done): ?>
            <?= (int) ($c['profiles'] ?? 0) ?> <?= h(__('imprint(s)')) ?>, <?= (int) ($c['revisions'] ?? 0) ?> <?= h(__('revision(s)')) ?>,
            <?= (int) ($c['identity_files'] ?? 0) ?> <?= h(__('ID file(s)')) ?>, <?= (int) ($c['requests'] ?? 0) ?> <?= h(__('request(s)')) ?>
          <?php elseif ($d['status'] === 'on_hold' || !empty($d['user_hold'])): ?>
            <span class="pf-badge pf-badge--suspended"><?= h(__('legal hold')) ?></span> <span class="pf-pre"><?= h((string) ($d['user_hold_note'] ?? '')) ?></span>
          <?php else: ?>–<?php endif; ?></td>
        <td class="pf-num"><?php if ($d['user_id'] !== null): ?><a class="pf-btn pf-btn--small" href="/admin/customer.php?id=<?= (int) $d['user_id'] ?>#erase"><?= h(__('Open customer')) ?></a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="pf-muted pf-small"><?= (int) count($deletions) ?> <?= h(__('result(s)')) ?></p>
<?php endif; ?>
</section>
<?php else: ?>
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
<?php endif; ?>
