<?php
/**
 * @var array<string,mixed> $profile @var array<string,mixed>|null $owner @var string $name @var array<string,mixed>|null $latest
 * @var array<int,array<string,mixed>> $history @var array<int,string> $reviewers @var bool $isLive
 * @var array<string,mixed>|null $package @var array<int,array<string,mixed>> $packages
 */
defined('TCD_APP') || exit;
$id = (int) $profile['id'];
$self = '/admin/profile.php?id=' . $id;
$priv = $latest ? $latest['private'] : [];
$privLabels = [
    'legal_name' => __('Legal name'), 'address_line1' => __('Address'), 'postal_code' => __('Postal code'), 'city' => __('City'),
    'country' => __('Country'), 'phone' => __('Phone'), 'vat_id' => __('VAT ID'), 'notes' => __('Notes from the customer'),
];
$submitted = null;
foreach ($history as $r) {
    if ($r['state'] === 'submitted') {
        $submitted = $r;
        break;
    }
}
?>
<p><a href="/admin/profiles.php">&larr; <?= h(__('Imprints')) ?></a></p>
<div class="pf-row pf-row--between">
  <h1><?= h($name) ?></h1>
  <div><?= status_badge((string) $profile['status']) ?></div>
</div>

<?php if ($submitted): ?>
  <div class="pf-notice"><?= h(__('A revision is waiting for review.')) ?> <a class="pf-btn pf-btn--small pf-btn--primary" href="/admin/review.php?id=<?= (int) $submitted['id'] ?>"><?= h(__('Review now')) ?></a></div>
<?php endif; ?>

<div class="pf-grid pf-grid--2">
  <section class="pf-card">
    <h2><?= h(__('Details')) ?></h2>
    <dl class="pf-kv">
      <dt><?= h(__('Page name')) ?></dt><dd><?= h($profile['slug']) ?></dd>
      <dt><?= h(__('Public URL')) ?></dt>
      <dd><?php if ($isLive): ?><a href="<?= h(imprint_public_url((string) $profile['slug'])) ?>" target="_blank" rel="noopener"><?= h(imprint_public_url((string) $profile['slug'])) ?></a>
        <?php else: ?><span class="pf-muted"><?= h(__('not online')) ?> (<?= h(imprint_public_url((string) $profile['slug'])) ?>)</span><?php endif; ?></dd>
      <dt><?= h(__('Owner')) ?></dt>
      <dd><?php if ($owner): ?><a href="/admin/customer.php?id=<?= (int) $owner['id'] ?>"><?= h($owner['name']) ?></a> &lt;<?= h($owner['email']) ?>&gt;
        <?php else: ?><span class="pf-muted"><?= h(__('no owner assigned')) ?></span><?php endif; ?></dd>
      <dt><?= h(__('Identity')) ?></dt><dd><?= !empty($profile['identity_verified']) ? '<span class="pf-badge pf-badge--ok">' . h(__('verified')) . '</span>' : '<span class="pf-badge pf-badge--pending">' . h(__('not verified')) . '</span>' ?></dd>
      <dt><?= h(__('Package')) ?></dt><dd id="profile-package"><?= admin_package_html($profile, $package) /* escaped HTML from the helper */ ?></dd>
      <dt><?= h(__('Created')) ?></dt><dd><?= h(fmt_time((int) $profile['created_at'])) ?></dd>
      <dt><?= h(__('Published')) ?></dt><dd><?= h(fmt_time($profile['published_at'] !== null ? (int) $profile['published_at'] : null)) ?></dd>
    </dl>
  </section>

  <section class="pf-card pf-card--private">
    <h2><?= h(__('Private data')) ?><?= $latest ? ' <span class="pf-muted pf-title-sub">(v' . (int) $latest['number'] . ')</span>' : '' ?></h2>
    <p class="pf-muted pf-small"><?= h(__('Newest revision. Never published.')) ?></p>
    <dl class="pf-kv">
      <?php foreach ($privLabels as $field => $label): $val = (string) ($priv[$field] ?? ''); if ($val === '') { continue; } ?>
        <dt><?= h($label) ?></dt><dd class="pf-pre"><?= h($val) ?></dd>
      <?php endforeach; ?>
      <?php if (!array_filter($priv)): ?><dd class="pf-muted"><?= h(__('No private data entered yet.')) ?></dd><?php endif; ?>
    </dl>
  </section>
</div>

<section class="pf-card">
  <h2><?= h(__('Revision history')) ?></h2>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th>#</th><th><?= h(__('State')) ?></th><th><?= h(__('Submitted')) ?></th><th><?= h(__('Reviewed')) ?></th><th><?= h(__('By')) ?></th><th><?= h(__('Note')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($history as $r): ?>
      <tr>
        <td>v<?= (int) $r['number'] ?><?= (int) $r['id'] === (int) ($profile['live_revision_id'] ?? 0) ? ' <span class="pf-badge pf-badge--live">' . h(__('live')) . '</span>' : '' ?></td>
        <td><?= status_badge((string) $r['state']) ?></td>
        <td><?= h(fmt_time($r['submitted_at'] !== null ? (int) $r['submitted_at'] : null)) ?></td>
        <td><?= h(fmt_time($r['reviewed_at'] !== null ? (int) $r['reviewed_at'] : null)) ?></td>
        <td><?= h($r['reviewed_by'] !== null ? ($reviewers[(int) $r['reviewed_by']] ?? '') : '–') ?></td>
        <td class="pf-pre"><?= h((string) $r['review_note']) ?></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small" href="/admin/review.php?id=<?= (int) $r['id'] ?>"><?= h(__('Details')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="pf-card">
  <h2><?= h(__('Actions')) ?></h2>
  <div class="pf-grid pf-grid--2">
    <?php if ($profile['status'] === 'live'): ?>
    <form class="pf-form pf-action" method="post" action="<?= h($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="suspend">
      <h3><?= h(__('Suspend')) ?></h3>
      <p class="pf-help"><?= h(__('Takes the page offline immediately. The owner is informed by e-mail.')) ?></p>
      <div class="pf-field"><label for="reason"><?= h(__('Reason (sent to the owner, optional)')) ?></label><textarea id="reason" name="reason" maxlength="1000"></textarea></div>
      <button class="pf-btn pf-btn--danger" type="submit"><?= h(__('Suspend imprint')) ?></button>
    </form>
    <form class="pf-form pf-action" method="post" action="<?= h($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="republish">
      <h3><?= h(__('Re-publish')) ?></h3>
      <p class="pf-help"><?= h(__('Writes the static page again from the live revision (e.g. after a template change).')) ?></p>
      <button class="pf-btn" type="submit"><?= h(__('Re-publish page')) ?></button>
    </form>
    <?php endif; ?>
    <?php if ($profile['status'] === 'suspended' && $profile['live_revision_id'] !== null): ?>
    <form class="pf-form pf-action" method="post" action="<?= h($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="reactivate">
      <h3><?= h(__('Reactivate')) ?></h3>
      <p class="pf-help"><?= h(__('Puts the last approved version online again.')) ?></p>
      <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Reactivate imprint')) ?></button>
    </form>
    <?php endif; ?>
    <form class="pf-form pf-action" method="post" action="<?= h($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="assign_owner">
      <h3><?= h(__('Assign owner')) ?></h3>
      <div class="pf-field"><label for="owner_email"><?= h(__('E-mail of the customer account')) ?></label>
        <input type="email" id="owner_email" name="owner_email" maxlength="254" value="<?= h($owner ? (string) $owner['email'] : '') ?>">
        <div class="pf-help"><?= h(__('Leave empty to remove the owner.')) ?></div></div>
      <button class="pf-btn" type="submit"><?= h(__('Save owner')) ?></button>
    </form>
<?php if ($packages !== []): ?>
    <form class="pf-form pf-action" method="post" action="<?= h($self) ?>#package" id="package">
      <?= csrf_field() ?><input type="hidden" name="action" value="change_package">
      <h3><?= h(__('Change package')) ?></h3>
      <div class="pf-field"><label for="package_id"><?= h(__('Package')) ?></label>
        <select id="package_id" name="package_id">
<?php foreach ($packages as $pk): ?>
          <option value="<?= (int) $pk['id'] ?>"<?= $package !== null && $package['id'] === $pk['id'] ? ' selected' : '' ?>><?= h($pk['name']) ?><?= $pk['active'] === 1 ? '' : ' (' . h(__('inactive')) . ')' ?> – <?= h(sprintf(__('%d profiles'), $pk['max_profiles'])) ?></option>
<?php endforeach; ?>
        </select>
        <div class="pf-help"><?= h(__('Takes effect immediately, no review needed. The owner is not notified.')) ?></div></div>
      <button class="pf-btn" type="submit"><?= h(__('Save package')) ?></button>
    </form>
<?php endif; ?>
    <form class="pf-form pf-action" method="post" action="<?= h($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="change_slug">
      <h3><?= h(__('Change page name')) ?></h3>
      <div class="pf-field"><label for="slug"><?= h(__('New page name')) ?></label>
        <input type="text" id="slug" name="slug" maxlength="40" value="<?= h($profile['slug']) ?>" pattern="[a-z0-9-]{3,40}" spellcheck="false">
        <div class="pf-help"><?= h(__('The old address stops working. A live page is moved right away.')) ?></div></div>
      <button class="pf-btn" type="submit"><?= h(__('Change page name')) ?></button>
    </form>
    <form class="pf-form pf-action pf-action--danger" method="post" action="<?= h($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <h3><?= h(__('Delete imprint')) ?></h3>
      <p class="pf-help"><?= h(__('Removes the page and ALL revisions permanently.')) ?></p>
      <div class="pf-field"><label for="confirm_slug"><?= h(sprintf(__('Type "%s" to confirm'), (string) $profile['slug'])) ?></label>
        <input type="text" id="confirm_slug" name="confirm_slug" maxlength="60" autocomplete="off" spellcheck="false"></div>
      <button class="pf-btn pf-btn--danger" type="submit"><?= h(__('Delete permanently')) ?></button>
    </form>
  </div>
</section>
