<?php
/** @var array<int,array<string,mixed>> $rows @var array{page:int,pages:int,offset:int,per:int,total:int} $pager @var array<string,string> $filters */
defined('TCD_APP') || exit;
$pageUrl = static function (int $page) use ($filters): string {
    return '/admin/audit.php' . admin_query(array_merge($filters, ['page' => $page > 1 ? $page : '']));
};
?>
<h1><?= h(__('Audit log')) ?></h1>
<section class="pf-card">
  <form class="pf-row pf-filter" method="get" action="/admin/audit.php">
    <div class="pf-field pf-field--inline"><label for="action"><?= h(__('Action starts with')) ?></label>
      <input type="text" id="action" name="action" value="<?= h($filters['action']) ?>" placeholder="review."></div>
    <div class="pf-field pf-field--inline"><label for="actor"><?= h(__('Actor')) ?></label>
      <input type="text" id="actor" name="actor" value="<?= h($filters['actor']) ?>" placeholder="<?= h(__('e-mail or id')) ?>"></div>
    <div class="pf-field pf-field--inline"><label for="target"><?= h(__('Target')) ?></label>
      <input type="text" id="target" name="target" value="<?= h($filters['target']) ?>" placeholder="profile:12"></div>
    <div class="pf-field pf-field--inline"><button class="pf-btn" type="submit"><?= h(__('Filter')) ?></button>
      <a class="pf-btn pf-btn--ghost" href="/admin/audit.php"><?= h(__('Reset')) ?></a></div>
  </form>
</section>
<section class="pf-card">
<?php if (!$rows): ?>
  <p class="pf-muted"><?= h(__('No entries found.')) ?></p>
<?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table pf-table--audit">
    <thead><tr><th><?= h(__('Time')) ?></th><th><?= h(__('Actor')) ?></th><th><?= h(__('Action')) ?></th><th><?= h(__('Target')) ?></th><th><?= h(__('Details')) ?></th><th><?= h(__('IP')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $meta = (string) $r['meta_json']; ?>
      <tr>
        <td class="pf-nowrap"><?= h(fmt_time((int) $r['at'])) ?></td>
        <td><?= h($r['actor_label']) ?></td>
        <td><code><?= h($r['action']) ?></code></td>
        <td><?= $r['target_type'] !== null ? h($r['target_type'] . ':' . $r['target_id']) : '–' ?></td>
        <td class="pf-small pf-pre"><?= h($meta !== '{}' ? $meta : '') ?></td>
        <td class="pf-small"><?= h($r['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="pf-row pf-row--between pf-pager">
    <span class="pf-muted pf-small"><?= (int) $pager['total'] ?> <?= h(__('entries')) ?> · <?= h(sprintf(__('page %d of %d'), $pager['page'], $pager['pages'])) ?></span>
    <span>
      <?php if ($pager['page'] > 1): ?><a class="pf-btn pf-btn--small" href="<?= h($pageUrl($pager['page'] - 1)) ?>">&larr; <?= h(__('Newer')) ?></a><?php endif; ?>
      <?php if ($pager['page'] < $pager['pages']): ?><a class="pf-btn pf-btn--small" href="<?= h($pageUrl($pager['page'] + 1)) ?>"><?= h(__('Older')) ?> &rarr;</a><?php endif; ?>
    </span>
  </div>
<?php endif; ?>
</section>
