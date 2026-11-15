<?php
/**
 * @var array<string,mixed> $customer @var array<int,array<string,mixed>> $profiles @var array<int,array<string,mixed>> $requests
 * @var array<int,array<string,mixed>> $identityRequests @var array<string,mixed>|null $deletion
 */
defined('TCD_APP') || exit;
$self = '/admin/customer.php?id=' . (int) $customer['id'];
$status = (string) $customer['status'];
?>
<p><a href="/admin/customers.php">&larr; <?= h(__('Customers')) ?></a></p>
<div class="pf-row pf-row--between">
  <h1><?= h($customer['name'] !== '' ? $customer['name'] : $customer['email']) ?></h1>
  <div><?= status_badge($status) ?> <?= !empty($customer['legal_hold']) ? '<span class="pf-badge pf-badge--suspended">' . h(__('legal hold')) . '</span>' : '' ?></div>
</div>

<div class="pf-grid pf-grid--2">
  <section class="pf-card">
    <h2><?= h(__('Account')) ?></h2>
    <dl class="pf-kv">
      <dt><?= h(__('E-mail')) ?></dt><dd><?= h($customer['email']) ?></dd>
      <dt><?= h(__('E-mail confirmed')) ?></dt><dd><?= h(fmt_time($customer['email_verified_at'] !== null ? (int) $customer['email_verified_at'] : null)) ?></dd>
      <dt><?= h(__('Registered')) ?></dt><dd><?= h(fmt_time((int) $customer['created_at'])) ?></dd>
      <dt><?= h(__('Last sign-in')) ?></dt><dd><?= h(fmt_time($customer['last_login_at'] !== null ? (int) $customer['last_login_at'] : null)) ?></dd>
      <dt><?= h(__('Terms and Conditions')) ?></dt><dd><?= terms_status_html($customer) /* escaped HTML from the helper */ ?></dd>
    </dl>
  </section>
  <section class="pf-card">
    <h2><?= h(__('Actions')) ?></h2>
    <div class="pf-stack">
    <?php if ($status !== 'disabled'): ?>
      <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="disable">
        <p class="pf-help"><?= h(__('A disabled customer cannot sign in. Published imprints stay online.')) ?></p>
        <button class="pf-btn pf-btn--danger" type="submit"><?= h(__('Disable account')) ?></button></form>
    <?php else: ?>
      <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="enable">
        <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Enable account')) ?></button></form>
    <?php endif; ?>
    <?php if ($status === 'pending'): ?>
      <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="activate">
        <p class="pf-help"><?= h(__('Skips the e-mail confirmation. Only do this if you have verified the address yourself.')) ?></p>
        <button class="pf-btn" type="submit"><?= h(__('Activate manually')) ?></button></form>
      <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="resend_verification">
        <button class="pf-btn" type="submit"><?= h(__('Resend confirmation e-mail')) ?></button></form>
    <?php endif; ?>
    </div>
  </section>
</div>

<?php $hold = !empty($customer['legal_hold']); ?>
<?php if ($deletion !== null): ?>
  <div class="pf-notice<?= $hold ? ' pf-notice--warn' : '' ?>"><strong><?= h(__('The customer asked to end the service.')) ?></strong>
    <?= h(sprintf(__('Deletion request #%d, status: %s, requested %s.'), (int) $deletion['id'], admin_deletion_labels()[(string) $deletion['status']] ?? (string) $deletion['status'], fmt_time((int) $deletion['created_at']))) ?>
    <?= h($hold ? __('The automatic erasure is blocked by the legal hold below.') : __('Use "Erase now" below to complete it.')) ?></div>
<?php endif; ?>

<div class="pf-grid pf-grid--2">
  <section class="pf-card<?= $hold ? ' pf-card--warn' : '' ?>" id="legal-hold">
    <h2><?= h(__('Legal hold')) ?> <?= $hold ? '<span class="pf-badge pf-badge--suspended">' . h(__('active')) . '</span>' : '' ?></h2>
    <p class="pf-muted"><?= h(__('A legal hold blocks the automatic erasure of all data of this customer (account, address data, ID documents), also when the customer ends the service. Use it for an open claim or an authority request: we keep the data so that we can still name the responsible person. If the customer had already confirmed the deletion, removing the hold runs it immediately.')) ?></p>
    <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="legal_hold">
      <div class="pf-check">
        <input type="checkbox" id="hold" name="hold" value="1"<?= $hold ? ' checked' : '' ?>>
        <label for="hold"><?= h(__('Legal hold: keep the data of this customer')) ?></label>
      </div>
      <div class="pf-field">
        <label for="hold_note"><?= h(__('Reason (required while the hold is set)')) ?></label>
        <textarea id="hold_note" name="hold_note" maxlength="500" rows="3"><?= h((string) $customer['legal_hold_note']) ?></textarea>
        <div class="pf-help"><?= h(__('For example "open claim, file no. …" or "request by the authority on …". Visible to admins only; deleted together with the account.')) ?></div>
      </div>
      <button class="pf-btn<?= $hold ? '' : ' pf-btn--primary' ?>" type="submit"><?= h(__('Save legal hold')) ?></button>
    </form>
  </section>

  <section class="pf-card pf-card--danger" id="erase">
    <h2><?= h(__('Erase now')) ?></h2>
    <p class="pf-muted"><?= h(__('Deletes this customer irrevocably: published imprint pages go offline, ID documents are deleted from the web server and from the secure archive, imprint profiles, address data, requests and the account are erased. The audit log keeps only a pseudonymised record. The customer receives a confirmation e-mail.')) ?></p>
    <?php if ($hold): ?>
      <div class="pf-notice pf-notice--warn"><?= h(__('A legal hold is set: erasing is blocked. Remove the hold first.')) ?></div>
    <?php endif; ?>
    <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="erase">
      <div class="pf-field">
        <label for="confirm_email"><?= h(__('Type the customer\'s e-mail address to confirm')) ?></label>
        <input type="text" id="confirm_email" name="confirm_email" autocomplete="off" placeholder="<?= h((string) $customer['email']) ?>">
      </div>
      <button class="pf-btn pf-btn--danger" type="submit"><?= h(__('Erase now – irrevocable')) ?></button>
    </form>
  </section>
</div>

<section class="pf-card" id="identity">
  <h2><?= h(__('Identity documents')) ?></h2>
  <?php if (!$identityRequests): ?><p class="pf-muted"><?= h(__('This customer has not uploaded any ID documents.')) ?></p><?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th>#</th><th><?= h(__('Imprint')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('Files')) ?></th><th><?= h(__('Opened')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($identityRequests as $q): ?>
      <tr><td><?= (int) $q['id'] ?></td><td><?= $q['profile_slug'] !== null ? '/legal/' . h($q['profile_slug']) . '/' : '–' ?></td>
        <td><?= admin_identity_badge((string) $q['status']) /* escaped badge HTML from the helper */ ?></td><td><?= (int) $q['file_count'] ?></td><td><?= h(fmt_time((int) $q['created_at'])) ?></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small" href="/admin/identity.php?id=<?= (int) $q['id'] ?>"><?= h(__('Open')) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="pf-card">
  <h2><?= h(__('Imprints')) ?></h2>
  <?php if (!$profiles): ?><p class="pf-muted"><?= h(__('This customer has no imprints.')) ?></p><?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Page')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('Updated')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($profiles as $p): ?>
      <tr><td>/legal/<?= h($p['slug']) ?>/</td><td><?= status_badge((string) $p['status']) ?></td><td><?= h(fmt_time((int) $p['updated_at'])) ?></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small" href="/admin/profile.php?id=<?= (int) $p['id'] ?>"><?= h(__('Open')) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="pf-card">
  <h2><?= h(__('Requests')) ?></h2>
  <?php if (!$requests): ?><p class="pf-muted"><?= h(__('This customer has not sent any requests.')) ?></p><?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Service')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('Sent')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($requests as $q): ?>
      <tr><td><?= h($q['service_slug']) ?></td><td><?= status_badge((string) $q['status']) ?></td><td><?= h(fmt_time((int) $q['created_at'])) ?></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small" href="/admin/request.php?id=<?= (int) $q['id'] ?>"><?= h(__('Open')) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
