<?php
/**
 * @var array<string,mixed> $admin @var array<string,int> $stats @var array<int,array<string,mixed>> $pending
 * @var array<int,array<string,mixed>> $requests @var int $problems @var bool $noTotp @var array<int,array{text:string,href:string,link:string}> $vaultNotices
 */
defined('TCD_APP') || exit;
?>
<h1><?= h(__('Dashboard')) ?></h1>
<?= render_partial('admin/_subnav', ['current' => '']) ?>

<?php if ($noTotp): ?>
  <div class="pf-notice"><strong><?= h(__('Your account has no two-factor authentication.')) ?></strong>
    <?= h(__('Admins can approve public imprints, so please protect your account with an authenticator app.')) ?>
    <a href="/admin/account.php"><?= h(__('Set up 2FA')) ?></a></div>
<?php endif; ?>
<?php if ($problems > 0): ?>
  <div class="pf-notice pf-notice--warn"><strong><?= h(sprintf(__('%d system check(s) need attention.'), $problems)) ?></strong>
    <a href="/admin/settings.php#system-checks"><?= h(__('Open the system checks')) ?></a></div>
<?php endif; ?>

<?php foreach ($vaultNotices as $n): ?>
  <div class="pf-notice pf-notice--warn"><strong><?= h($n['text']) ?></strong> <a href="<?= h($n['href']) ?>"><?= h($n['link']) ?></a></div>
<?php endforeach; ?>
<?php if ($stats['deletions_hold'] > 0): ?>
  <div class="pf-notice pf-notice--warn"><strong><?= h(sprintf(__('%d deletion request(s) are on hold because of a legal hold.'), (int) $stats['deletions_hold'])) ?></strong>
    <a href="/admin/requests.php?tab=deletions&amp;dstatus=on_hold"><?= h(__('Show them')) ?></a></div>
<?php endif; ?>
<?php if ($stats['deletions_open'] > 0): ?>
  <div class="pf-notice"><strong><?= h(sprintf(__('%d open deletion request(s).'), (int) $stats['deletions_open'])) ?></strong>
    <a href="/admin/requests.php?tab=deletions"><?= h(__('Show them')) ?></a></div>
<?php endif; ?>

<div class="pf-grid pf-grid--4 pf-tiles">
  <a class="pf-card pf-stat pf-tile" href="/admin/reviews.php"><span class="pf-stat-number"><?= (int) $stats['pending_reviews'] ?></span><span class="pf-stat-label"><?= h(__('Pending reviews')) ?></span></a>
  <a class="pf-card pf-stat pf-tile" href="/admin/profiles.php?status=live"><span class="pf-stat-number"><?= (int) $stats['live_imprints'] ?></span><span class="pf-stat-label"><?= h(__('Live imprints')) ?></span></a>
  <a class="pf-card pf-stat pf-tile" href="/admin/customers.php"><span class="pf-stat-number"><?= (int) $stats['customers'] ?></span><span class="pf-stat-label"><?= h(__('Customers')) ?></span></a>
  <a class="pf-card pf-stat pf-tile" href="/admin/requests.php?status=new"><span class="pf-stat-number"><?= (int) $stats['new_requests'] ?></span><span class="pf-stat-label"><?= h(__('New requests')) ?></span></a>
</div>

<div class="pf-grid pf-grid--4 pf-tiles">
  <a class="pf-card pf-stat pf-tile" href="/admin/requests.php?tab=deletions"><span class="pf-stat-number"><?= (int) $stats['deletions_open'] ?></span><span class="pf-stat-label"><?= h(__('Open deletion requests')) ?></span></a>
  <a class="pf-card pf-stat pf-tile" href="/admin/requests.php?tab=deletions&amp;dstatus=on_hold"><span class="pf-stat-number"><?= (int) $stats['deletions_hold'] ?></span><span class="pf-stat-label"><?= h(__('Deletions on hold')) ?></span></a>
  <a class="pf-card pf-stat pf-tile" href="/admin/customers.php"><span class="pf-stat-number"><?= (int) $stats['legal_holds'] ?></span><span class="pf-stat-label"><?= h(__('Customers under legal hold')) ?></span></a>
  <a class="pf-card pf-stat pf-tile" href="/admin/vault.php"><span class="pf-stat-number"><?= (int) $stats['id_inbox'] ?></span><span class="pf-stat-label"><?= h(__('ID files in the web inbox')) ?></span></a>
</div>

<div class="pf-grid pf-grid--2">
  <section class="pf-card">
    <h2><?= h(__('Oldest pending reviews')) ?></h2>
    <?php if (!$pending): ?>
      <p class="pf-muted"><?= h(__('Nothing to review right now.')) ?></p>
    <?php else: ?>
      <div class="pf-table-wrap"><table class="pf-table">
        <thead><tr><th><?= h(__('Imprint')) ?></th><th><?= h(__('Waiting')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pending as $r): ?>
          <tr>
            <td><?= h((string) ($r['public']['display_name'] ?? $r['profile_slug'])) ?><br><span class="pf-muted pf-small"><?= h((string) ($r['owner_email'] ?? __('no owner'))) ?> · v<?= (int) $r['number'] ?></span></td>
            <td class="<?= h(admin_age_class((int) $r['submitted_at'])) ?>"><?= h(admin_age((int) $r['submitted_at'])) ?></td>
            <td class="pf-num"><a class="pf-btn pf-btn--small" href="/admin/review.php?id=<?= (int) $r['id'] ?>"><?= h(__('Review')) ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
    <p><a href="/admin/reviews.php"><?= h(__('All reviews')) ?></a></p>
  </section>

  <section class="pf-card">
    <h2><?= h(__('Newest requests')) ?></h2>
    <?php if (!$requests): ?>
      <p class="pf-muted"><?= h(__('No requests yet.')) ?></p>
    <?php else: ?>
      <div class="pf-table-wrap"><table class="pf-table">
        <thead><tr><th><?= h(__('From')) ?></th><th><?= h(__('Service')) ?></th><th><?= h(__('Status')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($requests as $q): ?>
          <tr class="<?= in_array($q['service_slug'], ['shops', 'account-deletion'], true) ? 'pf-row-highlight' : '' ?>">
            <td><a href="/admin/request.php?id=<?= (int) $q['id'] ?>"><?= h($q['name'] !== '' ? $q['name'] : $q['email']) ?></a><br><span class="pf-muted pf-small"><?= h(admin_age((int) $q['created_at'])) ?></span></td>
            <td><?= h($q['service_slug']) ?></td>
            <td><?= status_badge((string) $q['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
    <p><a href="/admin/requests.php"><?= h(__('All requests')) ?></a></p>
  </section>
</div>
