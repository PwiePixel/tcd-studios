<?php
/**
 * @var array<string,mixed> $rev @var array<string,mixed> $profile @var array<string,mixed>|null $owner @var array<string,mixed>|null $live
 * @var array<int,array<string,string>> $diff @var array<string,string> $privateChanged @var string $preview
 * @var array<int,array<string,mixed>> $history @var string $formError @var string $noteValue @var array<int,string> $reviewers
 */
defined('TCD_APP') || exit;
$self = '/admin/review.php?id=' . (int) $rev['id'];
$priv = $rev['private'];
$pub = $rev['public'];
$privLabels = [
    'legal_name' => __('Legal name'), 'address_line1' => __('Address'), 'postal_code' => __('Postal code'), 'city' => __('City'),
    'country' => __('Country'), 'phone' => __('Phone'), 'vat_id' => __('VAT ID'), 'notes' => __('Notes from the customer'),
];
?>
<p><a href="/admin/reviews.php">&larr; <?= h(__('Review queue')) ?></a></p>
<div class="pf-row pf-row--between">
  <h1><?= h((string) ($pub['display_name'] ?? $profile['slug'])) ?> <span class="pf-muted pf-title-sub">v<?= (int) $rev['number'] ?></span></h1>
  <div><?= status_badge((string) $rev['state']) ?> <?= status_badge((string) $profile['status']) ?></div>
</div>
<?php if ($rev['state'] === 'submitted'): ?><p><a href="#decision"><?= h(__('Jump to the decision')) ?> &darr;</a></p><?php endif; ?>

<?php if ($formError !== ''): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($formError) ?></div><?php endif; ?>
<?php if ($rev['state'] !== 'submitted'): ?>
  <div class="pf-notice pf-notice--info"><?= h(sprintf(__('This revision is %s – it can no longer be approved or rejected.'), mb_strtolower(status_label((string) $rev['state']), 'UTF-8'))) ?></div>
<?php endif; ?>
<?php if (in_array($profile['status'], ['suspended', 'withdrawn'], true) && $rev['state'] === 'submitted'): ?>
  <div class="pf-notice"><?= h(sprintf(__('The imprint is currently %s. Approving this revision puts it online again.'), mb_strtolower(status_label((string) $profile['status']), 'UTF-8'))) ?></div>
<?php endif; ?>

<div class="pf-grid pf-grid--2">
  <section class="pf-card">
    <h2><?= h(__('Imprint and owner')) ?></h2>
    <dl class="pf-kv">
      <dt><?= h(__('Page')) ?></dt><dd><a href="/admin/profile.php?id=<?= (int) $profile['id'] ?>">/legal/<?= h($profile['slug']) ?>/</a></dd>
      <dt><?= h(__('Type')) ?></dt><dd><?= h((string) (imprint_kinds()[$pub['kind'] ?? ''] ?? ($pub['kind'] ?? ''))) ?></dd>
      <dt><?= h(__('Owner')) ?></dt>
      <dd><?php if ($owner): ?><a href="/admin/customer.php?id=<?= (int) $owner['id'] ?>"><?= h($owner['name']) ?></a><br><?= h($owner['email']) ?>
        <?= $owner['email_verified_at'] ? '<span class="pf-badge pf-badge--ok">' . h(__('e-mail verified')) . '</span>' : '<span class="pf-badge pf-badge--pending">' . h(__('e-mail not verified')) . '</span>' ?>
        <?php else: ?><span class="pf-muted"><?= h(__('no owner assigned')) ?></span><?php endif; ?></dd>
<?php if ($owner): ?>
      <dt><?= h(__('Terms and Conditions')) ?></dt><dd><?= terms_status_html($owner) /* escaped HTML from the helper */ ?></dd>
<?php endif; ?>
      <dt><?= h(__('Submitted')) ?></dt><dd><?= h(fmt_time($rev['submitted_at'] !== null ? (int) $rev['submitted_at'] : null)) ?> (<?= h(admin_age($rev['submitted_at'] !== null ? (int) $rev['submitted_at'] : null)) ?>)</dd>
      <dt><?= h(__('Identity')) ?></dt><dd><?= !empty($profile['identity_verified']) ? '<span class="pf-badge pf-badge--ok">' . h(__('verified before')) . '</span>' : '<span class="pf-badge pf-badge--pending">' . h(__('not verified yet')) . '</span>' ?></dd>
    </dl>
  </section>

  <section class="pf-card pf-card--private">
    <h2><?= h(__('Private data – identity check')) ?></h2>
    <p class="pf-muted pf-small"><?= h(__('Never published. Compare with the identity documents or the registration.')) ?></p>
    <dl class="pf-kv">
      <?php foreach ($privLabels as $field => $label): $val = (string) ($priv[$field] ?? ''); if ($val === '' && !in_array($field, ['legal_name', 'address_line1', 'postal_code', 'city', 'country'], true)) { continue; } ?>
        <dt><?= h($label) ?></dt>
        <dd class="pf-pre"><?= $val !== '' ? h($val) : '<span class="pf-error-text">' . h(__('missing')) . '</span>' ?>
          <?php if (isset($privateChanged[$field])): ?><span class="pf-badge pf-badge--info"><?= h(__('changed')) ?></span>
            <span class="pf-muted pf-small"><?= h(__('before:')) ?> <?= h($privateChanged[$field] !== '' ? $privateChanged[$field] : '–') ?></span><?php endif; ?></dd>
      <?php endforeach; ?>
    </dl>
  </section>
</div>

<section class="pf-card">
  <h2><?= h($live ? __('Changes compared with the live version') : __('First submission')) ?></h2>
  <?php if (!$live): ?>
    <p class="pf-muted"><?= h(__('There is no live version yet. All values below are new.')) ?></p>
  <?php endif; ?>
  <?php if (!$diff): ?>
    <p class="pf-muted"><?= h(__('No difference in the public data.')) ?></p>
  <?php else: ?>
  <div class="pf-table-wrap"><table class="pf-diff">
    <thead><tr><th><?= h(__('Field')) ?></th><th><?= h(__('Live version')) ?></th><th><?= h(__('Submitted version')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($diff as $d): ?>
      <tr><th><?= h($d['label']) ?></th><td class="pf-diff-old"><?= h($d['old'] !== '' ? $d['old'] : '–') ?></td><td class="pf-diff-new"><?= h($d['new'] !== '' ? $d['new'] : '–') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="pf-card">
  <h2><?= h(__('Preview of the public page')) ?></h2>
  <?= $preview /* HTML fragment generated by imprint_render_fragment(), which escapes all values */ ?>
</section>

<?php if ($rev['state'] === 'submitted'): ?>
<section class="pf-card pf-card--accent" id="decision">
  <h2><?= h(__('Decision')) ?></h2>
  <form class="pf-form pf-wide-form" method="post" action="<?= h($self) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="fp" value="<?= h(revision_fingerprint($rev)) ?>">
    <div class="pf-check">
      <input type="checkbox" id="verified" name="verified" value="1">
      <label for="verified"><?= h(__('I verified the identity and address')) ?></label>
    </div>
    <div class="pf-field">
      <label for="note"><?= h(__('Note to the customer')) ?></label>
      <textarea id="note" name="note" maxlength="2000"><?= h($noteValue) ?></textarea>
      <div class="pf-help"><?= h(__('Optional when approving. Required when rejecting – the customer receives it by e-mail. The note is stored in the review history.')) ?></div>
    </div>
    <?php if (!$owner): ?><div class="pf-help"><?= h(__('This imprint has no owner, so nobody will be notified by e-mail.')) ?></div><?php endif; ?>
    <div class="pf-actions">
      <button class="pf-btn pf-btn--primary" type="submit" name="action" value="approve"><?= h(__('Approve and publish')) ?></button>
      <button class="pf-btn pf-btn--danger" type="submit" name="action" value="reject"><?= h(__('Reject')) ?></button>
    </div>
  </form>
</section>
<?php endif; ?>

<section class="pf-card">
  <h2><?= h(__('Review history')) ?></h2>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th>#</th><th><?= h(__('State')) ?></th><th><?= h(__('Submitted')) ?></th><th><?= h(__('Reviewed')) ?></th><th><?= h(__('By')) ?></th><th><?= h(__('Note')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($history as $r): ?>
      <tr<?= (int) $r['id'] === (int) $rev['id'] ? ' class="pf-row-current"' : '' ?>>
        <td><a href="/admin/review.php?id=<?= (int) $r['id'] ?>">v<?= (int) $r['number'] ?></a></td>
        <td><?= status_badge((string) $r['state']) ?></td>
        <td><?= h(fmt_time($r['submitted_at'] !== null ? (int) $r['submitted_at'] : null)) ?></td>
        <td><?= h(fmt_time($r['reviewed_at'] !== null ? (int) $r['reviewed_at'] : null)) ?></td>
        <td><?= h($r['reviewed_by'] !== null ? ($reviewers[(int) $r['reviewed_by']] ?? '') : '–') ?></td>
        <td class="pf-pre"><?= h((string) $r['review_note']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
