<?php
/**
 * @var array<string,mixed> $profile @var array<string,mixed> $s @var array<int,array<string,mixed>> $revisions
 * @var array<string,mixed>|null $open @var array<string,mixed>|null $previewRev @var string $preview
 * @var array<string,mixed>|null $rejection @var string[] $missing @var bool $readOnly
 */
defined('TCD_APP') || exit;
$pid = (int) $profile['id'];
$status = (string) $profile['status'];
$hasLive = $profile['live_revision_id'] !== null;
?>
<p><a href="/portal/dashboard.php">&larr; <?= h(__('Dashboard')) ?></a></p>
<div class="pf-row pf-row--between">
  <h1><?= h($s['name']) ?></h1>
  <div class="pf-row">
    <?= status_badge($status) ?>
    <?php if ($s['update_pending']): ?><span class="pf-badge pf-badge--pending"><?= h(__('Update in review')) ?></span><?php endif; ?>
  </div>
</div>

<?php if ($rejection !== null): ?>
  <div class="pf-notice" role="note">
    <strong><?= h(__('Our team asked for changes')) ?></strong>
    <?php if (trim((string) $rejection['review_note']) !== ''): ?>
      <p><?= nl2br(h($rejection['review_note'])) ?></p>
    <?php else: ?>
      <p><?= h(__('The last version was not approved. No note was left.')) ?></p>
    <?php endif; ?>
    <span class="pf-small pf-muted"><?= h(sprintf(__('Version %d, reviewed %s'), (int) $rejection['number'], portal_date($rejection['reviewed_at'] !== null ? (int) $rejection['reviewed_at'] : null))) ?></span>
  </div>
<?php endif; ?>

<div class="pf-grid pf-grid--2">
  <div class="pf-card">
    <h2><?= h(__('Status')) ?></h2>
    <p><?= h(portal_status_explanation($status, $hasLive)) ?></p>
    <dl class="pf-kv">
      <dt><?= h(__('Page name')) ?></dt><dd><code><?= h($profile['slug']) ?></code></dd>
      <dt><?= h(__('Live page')) ?></dt>
      <dd>
        <?php if ($s['url'] !== ''): ?>
          <a href="<?= h($s['url']) ?>" target="_blank" rel="noopener"><?= h($s['url']) ?></a>
        <?php else: ?>
          <span class="pf-muted"><?= h(__('not online')) ?></span>
        <?php endif; ?>
      </dd>
      <dt><?= h(__('Open version')) ?></dt>
      <dd>
        <?php if ($open !== null): ?>
          <?= status_badge((string) $open['state']) ?> <span class="pf-muted pf-small"><?= h(sprintf(__('Version %d'), (int) $open['number'])) ?></span>
        <?php else: ?>
          <span class="pf-muted"><?= h(__('none')) ?></span>
        <?php endif; ?>
      </dd>
      <?php if ($open !== null && $open['state'] === 'draft'): ?>
        <dt><?= h(__('Private details')) ?></dt>
        <dd>
          <?php if ($missing === []): ?>
            <span class="pf-badge pf-badge--ok"><?= h(__('complete')) ?></span>
          <?php else: ?>
            <span class="pf-badge pf-badge--pending"><?= h(__('incomplete')) ?></span>
            <div class="pf-small pf-muted"><?= h(__('Still missing:')) ?> <?= h(implode(', ', $missing)) ?></div>
          <?php endif; ?>
        </dd>
      <?php endif; ?>
    </dl>
  </div>

  <div class="pf-card">
    <h2><?= h(__('What you can do')) ?></h2>
<?php if ($readOnly): ?>
    <p class="pf-muted"><?= h(__('This imprint is suspended. Please contact us to have it reactivated.')) ?></p>
<?php elseif ($open !== null && $open['state'] === 'draft'): ?>
    <p class="pf-muted"><?= h(__('You are working on a draft. It is only visible to you until you submit it and our team approves it.')) ?></p>
    <div class="pf-actions">
      <a class="pf-btn" href="/portal/profile-edit.php?id=<?= $pid ?>"><?= h(__('Edit')) ?></a>
      <form class="pf-inline-form" method="post" action="/portal/profile-submit.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $pid ?>">
        <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Submit for review')) ?></button>
      </form>
    </div>
<?php elseif ($open !== null && $open['state'] === 'submitted'): ?>
    <p class="pf-muted"><?= h(__('Your submission is waiting for review and cannot be edited. If you want to change something, withdraw it – it becomes a draft again.')) ?></p>
    <div class="pf-actions">
      <form class="pf-inline-form" method="post" action="/portal/profile-action.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $pid ?>">
        <input type="hidden" name="action" value="withdraw_submission">
        <button class="pf-btn" type="submit"><?= h(__('Withdraw submission')) ?></button>
      </form>
    </div>
<?php else: ?>
    <p class="pf-muted"><?= h($hasLive ? __('To change your imprint, start a new draft. The current version stays unchanged until the new one is approved.') : __('Start a new draft based on your last version.')) ?></p>
    <div class="pf-actions">
      <form class="pf-inline-form" method="post" action="/portal/profile-action.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $pid ?>">
        <input type="hidden" name="action" value="request_changes">
        <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Request changes')) ?></button>
      </form>
    </div>
<?php endif; ?>
<?php if ($status === 'live'): ?>
    <hr class="pf-rule">
    <p class="pf-muted pf-small"><?= h(__('You can take your imprint offline at any time. Publishing it again needs a new approval.')) ?></p>
    <a class="pf-btn pf-btn--danger pf-btn--small" href="/portal/profile-offline.php?id=<?= $pid ?>"><?= h(__('Take offline')) ?></a>
<?php endif; ?>
  </div>
</div>

<?php if ($previewRev !== null):
    $prevState = (string) $previewRev['state'];
    if ($prevState === 'draft') {
        $prevTitle = __('Preview of your draft (not published)');
    } elseif ($prevState === 'submitted') {
        $prevTitle = __('Preview of your submission (waiting for review)');
    } elseif ($status === 'live') {
        $prevTitle = __('Preview of the live version');
    } else {
        $prevTitle = __('Preview of the last version');
    }
?>
<div class="pf-card">
  <h2><?= h($prevTitle) ?></h2>
  <p class="pf-muted pf-small"><?= h(__('Only your public information is shown here. Your private details are never part of the imprint.')) ?></p>
  <?php if ($preview !== ''): ?>
    <div class="pf-preview-box"><?= $preview /* trusted HTML from imprint_render_fragment(), which escapes all customer data */ ?></div>
  <?php else: ?>
    <p class="pf-muted"><?= h(__('The preview is not available right now.')) ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="pf-card">
  <h2><?= h(__('Version history')) ?></h2>
  <div class="pf-table-wrap">
    <table class="pf-table">
      <thead><tr><th><?= h(__('Version')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('Submitted')) ?></th><th><?= h(__('Reviewed')) ?></th><th><?= h(__('Note from our team')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($revisions as $r): ?>
        <tr>
          <td>#<?= (int) $r['number'] ?></td>
          <td><?= status_badge((string) $r['state']) ?></td>
          <td><?= h(portal_date($r['submitted_at'] !== null ? (int) $r['submitted_at'] : null)) ?></td>
          <td><?= h(portal_date($r['reviewed_at'] !== null ? (int) $r['reviewed_at'] : null)) ?></td>
          <td><?= trim((string) $r['review_note']) !== '' ? nl2br(h($r['review_note'])) : '<span class="pf-muted">–</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
