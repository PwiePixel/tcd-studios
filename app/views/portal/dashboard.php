<?php
/**
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $rows @var bool $canCreate @var int $maxProfiles @var array<int,array<string,mixed>> $requests
 */
defined('TCD_APP') || exit;
?>
<h1><?= h(__('Dashboard')) ?></h1>
<p class="pf-muted"><?= h(sprintf(__('Welcome, %s!'), (string) $user['name'])) ?></p>

<div class="pf-notice pf-notice--info">
  <strong><?= h(__('How Imprint Protection works')) ?></strong>
  <ul class="pf-list">
    <li><?= h(__('Your imprint is published under tcd-studio.de/legal/<page name>/ with the management address of TCD-Studio/Agency instead of your private address.')) ?></li>
    <li><?= h(__('Your private details (legal name and address) stay private: they are never published and only you and our team can see them.')) ?></li>
    <li><?= h(__('You choose what is shown publicly: your purpose text, your links and, if you want, a contact e-mail address.')) ?></li>
    <li><?= h(__('Nothing goes live without approval: every new version is checked by our team first. Until then, the current version stays online.')) ?></li>
  </ul>
</div>

<div class="pf-card">
  <div class="pf-row pf-row--between">
    <h2><?= h(__('Your imprints')) ?></h2>
    <?php if ($canCreate): ?>
      <a class="pf-btn pf-btn--primary" href="/portal/profile-new.php"><?= h(__('Create imprint profile')) ?></a>
    <?php endif; ?>
  </div>
<?php if ($rows === []): ?>
  <p class="pf-muted"><?= h(__('You have no imprint profile yet. Create one to get started – it takes about five minutes.')) ?></p>
<?php else: ?>
  <div class="pf-table-wrap">
    <table class="pf-table">
      <thead>
        <tr>
          <th><?= h(__('Public name')) ?></th>
          <th><?= h(__('Page name')) ?></th>
          <th><?= h(__('Status')) ?></th>
          <th><?= h(__('Live page')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): $p = $row['profile']; ?>
        <tr>
          <td><strong><?= h($row['name']) ?></strong></td>
          <td><code><?= h($p['slug']) ?></code></td>
          <td>
            <?= status_badge((string) $p['status']) ?>
            <?php if ($row['update_pending']): ?>
              <span class="pf-badge pf-badge--pending"><?= h(__('Update in review')) ?></span>
              <div class="pf-muted pf-small"><?= h(__('An update is waiting for approval. The current version stays online until then.')) ?></div>
            <?php elseif ($row['open'] !== null && $row['open']['state'] === 'draft' && $p['status'] !== 'draft'): ?>
              <div class="pf-muted pf-small"><?= h(__('You have an unsubmitted draft.')) ?></div>
            <?php elseif ($p['status'] === 'draft' && $row['open'] === null): ?>
              <div class="pf-muted pf-small"><?= h(__('Changes were requested – see the details.')) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($row['url'] !== ''): ?>
              <a href="<?= h($row['url']) ?>" target="_blank" rel="noopener">/legal/<?= h($p['slug']) ?>/</a>
            <?php else: ?>
              <span class="pf-muted">–</span>
            <?php endif; ?>
          </td>
          <td class="pf-num"><a class="pf-btn pf-btn--small" href="/portal/profile.php?id=<?= (int) $p['id'] ?>"><?= h(__('Open')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php if (!$canCreate): ?>
  <p class="pf-muted pf-small"><?= h(sprintf(__('You have reached the maximum of %d imprint profiles. Please contact us if you need more.'), $maxProfiles)) ?></p>
<?php endif; ?>
</div>

<div class="pf-card">
  <div class="pf-row pf-row--between">
    <h2><?= h(__('Your service requests')) ?></h2>
    <a class="pf-btn pf-btn--small" href="/portal/services.php"><?= h(__('All services')) ?></a>
  </div>
<?php if ($requests === []): ?>
  <p class="pf-muted"><?= h(__('You have not requested anything yet. Have a look at our other services.')) ?></p>
<?php else: ?>
  <div class="pf-table-wrap">
    <table class="pf-table">
      <thead><tr><th><?= h(__('Service')) ?></th><th><?= h(__('Sent')) ?></th><th><?= h(__('Status')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($requests as $r): ?>
        <tr>
          <td><?= h($r['service_label']) ?></td>
          <td><?= h(portal_date((int) $r['created_at'])) ?></td>
          <td><?= status_badge((string) $r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>
