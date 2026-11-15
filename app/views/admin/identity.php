<?php
/**
 * @var array<string,mixed> $req @var array<string,mixed>|null $customer @var array<string,mixed>|null $profile @var array<string,mixed>|null $revision
 * @var array<string,mixed>|null $verifier @var array<int,array<string,mixed>> $files @var int $pending @var bool $vaultReady
 */
defined('TCD_APP') || exit;
$self = '/admin/identity.php?id=' . (int) $req['id'];
$priv = $revision !== null ? $revision['private'] : [];
$privLabels = [
    'legal_name' => __('Legal name'), 'address_line1' => __('Address'), 'postal_code' => __('Postal code'), 'city' => __('City'),
    'country' => __('Country'), 'phone' => __('Phone'), 'vat_id' => __('VAT ID'), 'notes' => __('Notes from the customer'),
];
$jsVersion = (int) @filemtime(TCD_ROOT . '/js/vault.js');
$sideLabels = ['front' => __('Front side'), 'back' => __('Back side')];
$canArchive = in_array($req['status'], ['verified', 'archived'], true) && $files;
?>
<p>
  <?php if ($revision !== null && $revision['state'] === 'submitted'): ?><a href="/admin/review.php?id=<?= (int) $revision['id'] ?>">&larr; <?= h(__('Back to the review')) ?></a>
  <?php elseif ($customer !== null): ?><a href="/admin/customer.php?id=<?= (int) $customer['id'] ?>">&larr; <?= h(__('Customer')) ?></a>
  <?php else: ?><a href="/admin/reviews.php">&larr; <?= h(__('Review queue')) ?></a><?php endif; ?>
</p>
<div class="pf-row pf-row--between">
  <h1><?= h(__('Identity documents')) ?> <span class="pf-muted pf-title-sub">#<?= (int) $req['id'] ?></span></h1>
  <div><?= admin_identity_badge((string) $req['status']) /* escaped badge HTML from the helper */ ?></div>
</div>

<?php if ($pending > 0): ?>
  <div class="pf-notice pf-notice--warn" role="alert"><strong><?= h(sprintf(__('%d file(s) are still on the web server.'), $pending)) ?></strong>
    <?= h(__('The request is verified, but moving the encrypted files to the secure archive failed or was interrupted. Check the archive on the Identity vault page and retry.')) ?></div>
<?php endif; ?>

<div class="pf-grid pf-grid--2">
  <section class="pf-card">
    <h2><?= h(__('Customer and request')) ?></h2>
    <dl class="pf-kv">
      <dt><?= h(__('Customer')) ?></dt>
      <dd><?php if ($customer !== null): ?><a href="/admin/customer.php?id=<?= (int) $customer['id'] ?>"><?= h($customer['name']) ?></a><br><?= h($customer['email']) ?>
        <?php else: ?><span class="pf-muted"><?= h(__('account no longer exists')) ?></span><?php endif; ?></dd>
      <dt><?= h(__('Imprint')) ?></dt>
      <dd><?php if ($profile !== null): ?><a href="/admin/profile.php?id=<?= (int) $profile['id'] ?>">/legal/<?= h($profile['slug']) ?>/</a> <?= status_badge((string) $profile['status']) ?>
        <?php else: ?><span class="pf-muted">–</span><?php endif; ?></dd>
      <dt><?= h(__('Status')) ?></dt><dd><?= admin_identity_badge((string) $req['status']) ?></dd>
      <dt><?= h(__('Opened')) ?></dt><dd><?= h(fmt_time((int) $req['created_at'])) ?></dd>
      <dt><?= h(__('Verified')) ?></dt>
      <dd><?php if ($req['verified_at'] !== null): ?><?= h(fmt_time((int) $req['verified_at'])) ?><?= $verifier !== null ? ' ' . h(__('by')) . ' ' . h($verifier['email']) : '' ?>
        <?php else: ?><span class="pf-muted">–</span><?php endif; ?></dd>
<?php if (trim((string) $req['note']) !== ''): ?>
      <dt><?= h(__('Note')) ?></dt><dd class="pf-pre"><?= h((string) $req['note']) ?></dd>
<?php endif; ?>
      <dt><?= h(__('Revision')) ?></dt>
      <dd><?php if ($revision !== null): ?>v<?= (int) $revision['number'] ?> <?= status_badge((string) $revision['state']) ?>
        <?php if ($revision['state'] === 'submitted'): ?> <a href="/admin/review.php?id=<?= (int) $revision['id'] ?>"><?= h(__('Open the review')) ?></a><?php endif; ?>
        <?php else: ?><span class="pf-muted">–</span><?php endif; ?></dd>
    </dl>
  </section>

  <section class="pf-card pf-card--private">
    <h2><?= h(__('Address entered by the customer')) ?></h2>
    <p class="pf-muted pf-small"><?= h(__('Compare this with the ID card. Never published.')) ?></p>
    <?php if ($revision === null): ?>
      <p class="pf-muted"><?= h(__('There is no imprint revision for this request.')) ?></p>
    <?php else: ?>
    <dl class="pf-kv">
      <?php foreach ($privLabels as $field => $label): $val = (string) ($priv[$field] ?? ''); if ($val === '' && !in_array($field, ['legal_name', 'address_line1', 'postal_code', 'city', 'country'], true)) { continue; } ?>
        <dt><?= h($label) ?></dt>
        <dd class="pf-pre"><?= $val !== '' ? h($val) : '<span class="pf-error-text">' . h(__('missing')) . '</span>' ?></dd>
      <?php endforeach; ?>
    </dl>
    <?php endif; ?>
  </section>
</div>

<section class="pf-card pf-card--accent" id="documents">
  <h2><?= h(__('Stored files')) ?></h2>
  <?php if (!$files): ?>
    <p class="pf-muted"><?= h(__('No files are stored for this request (they were deleted, or the identity was verified by other proof).')) ?></p>
  <?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Side')) ?></th><th><?= h(__('Size')) ?></th><th><?= h(__('Uploaded')) ?></th><th><?= h(__('Location')) ?></th><th><?= h(__('Archived')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($files as $f): ?>
      <tr data-file-id="<?= (int) $f['id'] ?>">
        <td><?= h($sideLabels[$f['side']] ?? (string) $f['side']) ?></td>
        <td><?= h(admin_bytes((int) $f['plain_size'])) ?> <span class="pf-muted pf-small">(<?= h(admin_bytes((int) $f['enc_size'])) ?> <?= h(__('encrypted')) ?>)</span></td>
        <td><?= h(fmt_time((int) $f['created_at'])) ?> <span class="pf-muted pf-small">(<?= h(admin_age((int) $f['created_at'])) ?>)</span></td>
        <td><?= h(admin_identity_file_location($f)) ?></td>
        <td><?= $f['archived_at'] !== null ? h(fmt_time((int) $f['archived_at'])) : '–' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($canArchive): ?>
    <form class="pf-form" method="post" action="<?= h($self) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="archive">
      <p class="pf-help"><?= h(__('Moves the encrypted files to the secure archive and deletes the web server copies. Use it to retry when archiving failed; it is harmless if everything is archived already.')) ?></p>
      <button class="pf-btn" type="submit"><?= h(__('Archive now')) ?></button></form>
  <?php elseif ($req['status'] === 'uploaded'): ?>
    <p class="pf-help"><?= h(__('The files are archived automatically when you approve the imprint (they are deleted from the web server then).')) ?></p>
  <?php endif; ?>
  <?php endif; ?>
</section>

<?php if ($files): ?>
<section class="pf-card" id="decrypt" data-vault-decrypt-root>
  <h2><?= h(__('Decrypt and show (in your browser)')) ?></h2>
  <div class="pf-notice pf-notice--info" role="note">
    <?= h(__('The photos are decrypted in your browser with your private key. The key file is read into memory only: it is never sent to the server and never stored. The decrypted image exists only in this browser tab – close the page when you are done.')) ?>
  </div>
  <?php if (!$vaultReady): ?>
    <div class="pf-notice pf-notice--warn"><?= h(__('No valid public key is set at the moment. Existing files can still be decrypted with the private key they were encrypted for.')) ?></div>
  <?php endif; ?>
  <div class="pf-field">
    <label for="vault_key_file"><?= h(__('Private key file')) ?></label>
    <input type="file" id="vault_key_file" accept=".pem,.key,.txt,application/x-pem-file" data-vault-keyfile>
    <div class="pf-help"><?= h(__('Choose id-vault-private-key.pem (PKCS#8 PEM). It is forgotten when you leave this page.')) ?></div>
  </div>
  <div class="pf-actions">
    <button class="pf-btn pf-btn--primary" type="button" data-vault-decrypt disabled><?= h(__('Decrypt and show')) ?></button>
    <button class="pf-btn pf-btn--ghost" type="button" data-vault-forget hidden><?= h(__('Forget key and hide images')) ?></button>
  </div>
  <p class="pf-muted" data-vault-status role="status" aria-live="polite"></p>
  <noscript><p class="pf-error-text"><?= h(__('Decrypting needs JavaScript (WebCrypto). Everything else on this page works without it.')) ?></p></noscript>
  <div class="pf-grid pf-grid--2 pf-idgrid">
    <?php foreach ($files as $f): ?>
      <figure class="pf-idfig" data-vault-file data-url="/admin/identity-file.php?id=<?= (int) $f['id'] ?>" data-label="<?= h($sideLabels[$f['side']] ?? (string) $f['side']) ?>">
        <figcaption><?= h($sideLabels[$f['side']] ?? (string) $f['side']) ?></figcaption>
        <div data-vault-slot><p class="pf-muted pf-small"><?= h(__('Not decrypted yet.')) ?></p></div>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<script src="/js/vault.js?v=<?= $jsVersion ?>" defer></script>
<?php endif; ?>
