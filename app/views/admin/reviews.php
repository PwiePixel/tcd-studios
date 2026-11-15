<?php
/** @var array<int,array<string,mixed>> $queue */
defined('TCD_APP') || exit;
?>
<h1><?= h(__('Review queue')) ?></h1>
<p class="pf-muted"><?= h(__('Submitted imprints, oldest first. Every change has to be approved before it goes online.')) ?></p>
<section class="pf-card">
<?php if (!$queue): ?>
  <p class="pf-muted"><?= h(__('The queue is empty. Nothing is waiting for review.')) ?></p>
<?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Imprint')) ?></th><th><?= h(__('Owner')) ?></th><th><?= h(__('Kind')) ?></th><th><?= h(__('Submitted')) ?></th><th><?= h(__('Waiting')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($queue as $r): ?>
      <tr>
        <td><strong><?= h((string) ($r['public']['display_name'] ?? $r['profile_slug'])) ?></strong><br><span class="pf-muted pf-small">/legal/<?= h($r['profile_slug']) ?>/ · v<?= (int) $r['number'] ?></span></td>
        <td><?= h((string) ($r['owner_email'] ?? __('no owner'))) ?></td>
        <td><?= $r['profile_status'] === 'live' ? '<span class="pf-badge pf-badge--info">' . h(__('Change')) . '</span>' : '<span class="pf-badge pf-badge--new">' . h(__('First submission')) . '</span>' ?></td>
        <td><?= h(fmt_time((int) $r['submitted_at'])) ?></td>
        <td class="<?= h(admin_age_class((int) $r['submitted_at'])) ?>"><?= h(admin_age((int) $r['submitted_at'])) ?></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small pf-btn--primary" href="/admin/review.php?id=<?= (int) $r['id'] ?>"><?= h(__('Review')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>
