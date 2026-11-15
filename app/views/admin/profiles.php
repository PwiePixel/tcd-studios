<?php
/** @var array<int,array<string,mixed>> $profiles @var string $status @var string $q */
defined('TCD_APP') || exit;
?>
<h1><?= h(__('Imprints')) ?></h1>
<section class="pf-card">
  <form class="pf-row pf-filter" method="get" action="/admin/profiles.php">
    <div class="pf-field pf-field--inline"><label for="q"><?= h(__('Search')) ?></label>
      <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="<?= h(__('page name, name or owner e-mail')) ?>"></div>
    <div class="pf-field pf-field--inline"><label for="status"><?= h(__('Status')) ?></label>
      <select id="status" name="status"><option value=""><?= h(__('All')) ?></option>
        <?php foreach (admin_profile_statuses() as $s): ?><option value="<?= h($s) ?>"<?= $status === $s ? ' selected' : '' ?>><?= h(status_label($s)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="pf-field pf-field--inline"><button class="pf-btn" type="submit"><?= h(__('Filter')) ?></button>
      <a class="pf-btn pf-btn--ghost" href="/admin/profiles.php"><?= h(__('Reset')) ?></a></div>
  </form>
</section>
<section class="pf-card">
<?php if (!$profiles): ?>
  <p class="pf-muted"><?= h(__('No imprints found.')) ?></p>
<?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Imprint')) ?></th><th><?= h(__('Page')) ?></th><th><?= h(__('Owner')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('Updated')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($profiles as $p): ?>
      <tr>
        <td><a href="/admin/profile.php?id=<?= (int) $p['id'] ?>"><strong><?= h($p['display_name']) ?></strong></a></td>
        <td>/legal/<?= h($p['slug']) ?>/</td>
        <td><?= $p['owner_email'] !== null ? h($p['owner_email']) : '<span class="pf-muted">' . h(__('no owner')) . '</span>' ?></td>
        <td><?= status_badge((string) $p['status']) ?><?= (int) $p['pending_count'] > 0 ? ' <span class="pf-badge pf-badge--pending">' . h(__('review waiting')) . '</span>' : '' ?></td>
        <td><?= h(fmt_time((int) $p['updated_at'])) ?></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small" href="/admin/profile.php?id=<?= (int) $p['id'] ?>"><?= h(__('Open')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="pf-muted pf-small"><?= (int) count($profiles) ?> <?= h(__('result(s)')) ?></p>
<?php endif; ?>
</section>
