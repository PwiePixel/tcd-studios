<?php
/**
 * @var array<string,mixed> $profile @var string $name @var array<string,mixed> $state @var array<string,string> $errors
 * @var bool $vaultReady @var bool $transportOk @var int $maxMb
 */
defined('TCD_APP') || exit;
$pid = (int) $profile['id'];
$self = '/portal/identity.php?id=' . $pid;
$action = '/portal/identity-upload.php?id=' . $pid;
$step = (string) $state['step'];
$bySide = [];
foreach ($state['files'] as $f) {
    $bySide[(string) $f['side']] = $f;
}
$sideLabels = ['front' => __('Front side'), 'back' => __('Back side')];
$canForm = $state['can_upload'] && $vaultReady;
?>
<p><a href="/portal/profile.php?id=<?= $pid ?>">&larr; <?= h(__('Back to the imprint overview')) ?></a></p>
<h1><?= h(__('Identity check')) ?></h1>
<p class="pf-muted"><?= h(__('For your imprint')) ?> <strong><?= h($name) ?></strong> (<code><?= h($profile['slug']) ?></code>)</p>

<div class="pf-grid pf-grid--identity">
  <div>
    <div class="pf-card pf-card--accent" id="status">
      <div class="pf-row pf-row--between">
        <h2><?= h(__('Status')) ?></h2>
        <?= portal_identity_badge_html($step) ?>
      </div>
      <p><?= h(portal_identity_explanation($state)) ?></p>
<?php if ($step === 'rejected' && trim((string) $state['note']) !== ''): ?>
      <div class="pf-notice">
        <strong><?= h(__('Note from our team')) ?></strong>
        <p><?= nl2br(h($state['note'])) ?></p>
      </div>
<?php endif; ?>
<?php if ($state['files'] !== []): ?>
      <div class="pf-table-wrap">
        <table class="pf-table" id="identity-files">
          <thead><tr><th><?= h(__('Image')) ?></th><th><?= h(__('Uploaded')) ?></th><th><?= h(__('Size')) ?></th><th></th></tr></thead>
          <tbody>
          <?php foreach ($state['files'] as $f): ?>
            <tr>
              <td>
                <strong><?= h($sideLabels[$f['side']] ?? $f['side']) ?></strong>
                <div class="pf-small pf-muted"><?= h($f['archived'] ? __('Secure archive, encrypted') : __('Web server, encrypted (temporary)')) ?></div>
              </td>
              <td class="pf-nowrap"><?= h(fmt_time((int) $f['created_at'])) ?></td>
              <td class="pf-nowrap"><?= h(portal_identity_size((int) $f['size'])) ?></td>
              <td class="pf-num">
              <?php if ($state['can_remove']): ?>
                <form class="pf-inline-form" method="post" action="<?= h($action) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="file_id" value="<?= (int) $f['id'] ?>">
                  <button class="pf-btn pf-btn--small pf-btn--danger" type="submit"><?= h(__('Remove this image')) ?></button>
                </form>
              <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="pf-muted pf-small"><?= h(__('For your security the images are never displayed here again: they are stored encrypted and can only be opened by authorised staff.')) ?></p>
<?php endif; ?>
<?php if ($state['locked']): ?>
      <p><a class="pf-btn" href="/portal/profile.php?id=<?= $pid ?>"><?= h(__('Back to the imprint overview')) ?></a></p>
<?php endif; ?>
    </div>

<?php if ($state['can_upload']): ?>
    <div class="pf-card" id="upload">
      <h2><?= h(__('Upload your ID card')) ?></h2>
<?php if (!$vaultReady): ?>
      <div class="pf-notice" id="vault-unavailable">
        <strong><?= h(__('The identity check is temporarily unavailable.')) ?></strong>
        <?= h(__('We cannot accept ID photos at the moment. Your draft is saved and you can keep editing it. Please try again later or contact us:')) ?>
        <a href="mailto:<?= h((string) config('mail.contact')) ?>"><?= h((string) config('mail.contact')) ?></a>
      </div>
<?php else: ?>
<?php if (!$transportOk): ?>
      <div class="pf-notice" id="insecure-connection">
        <strong><?= h(__('This connection is not secure.')) ?></strong>
        <?= h(__('For your security, ID photos can only be uploaded over a secure HTTPS connection. Please open this page with https://.')) ?>
      </div>
<?php endif; ?>
<?php if (!empty($errors['_form'])): ?>
      <div class="pf-flash pf-flash--error" role="alert" id="err-form"><?= h($errors['_form']) ?></div>
<?php endif; ?>
      <p class="pf-muted"><?= h(sprintf(__('Take a photo or a scan of both sides of your ID card. JPEG, PNG or WebP, at least 400 × 250 pixels, up to %d MB per image. If your document has data on one side only (for example a passport), upload just that side.'), $maxMb)) ?></p>
      <div class="pf-notice pf-notice--info" id="cover-hint">
        <strong><?= h(__('You may cover what we do not need.')) ?></strong>
        <?= h(__('Information we do not need (your photo, the document number and the access number CAN) can be covered. We only need to read your name and address.')) ?>
      </div>
      <form class="pf-form" method="post" action="<?= h($action) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="id" value="<?= $pid ?>">
        <?php foreach ($sideLabels as $side => $label): ?>
        <div class="pf-field">
          <label for="f-<?= h($side) ?>"><?= h(isset($bySide[$side]) ? sprintf(__('Replace the %s'), mb_strtolower($label, 'UTF-8')) : $label) ?></label>
          <input type="file" id="f-<?= h($side) ?>" name="<?= h($side) ?>" accept="image/jpeg,image/png,image/webp"<?= portal_invalid($errors, $side) ?>>
          <?= portal_err($errors, $side) ?>
        </div>
        <?php endforeach; ?>
        <div class="pf-actions">
          <button class="pf-btn pf-btn--primary" type="submit"<?= $transportOk ? '' : ' disabled' ?>><?= h(__('Encrypt and upload')) ?></button>
          <a class="pf-btn pf-btn--ghost" href="/portal/profile.php?id=<?= $pid ?>"><?= h(__('Not now')) ?></a>
        </div>
      </form>
<?php endif; ?>
    </div>
<?php endif; ?>
  </div>

  <div>
    <div class="pf-card" id="privacy">
      <h2><?= h(__('How we protect your ID photo')) ?></h2>
      <p class="pf-muted"><?= h(__('We know how sensitive this is. Here is exactly what happens to your images:')) ?></p>
      <ol class="pf-steps">
        <li><strong><?= h(__('Encrypted when you upload it.')) ?></strong>
          <?= h(__('Your images are encrypted the moment they arrive, with a key that is not stored on any of our servers. Nobody can read them from the web server, not even us.')) ?></li>
        <li><strong><?= h(__('Only authorised staff can open it.')) ?></strong>
          <?= h(__('To check your name and address, authorised team members decrypt the image in their own browser with a private key that they keep offline.')) ?></li>
        <li><strong><?= h(__('Removed after the check.')) ?></strong>
          <?= h(__('Once your identity is verified, the file is deleted from the web server. It is kept only encrypted on a separate, secured storage system until the service ends.')) ?></li>
        <li><strong><?= h(__('Address data only while you use the service.')) ?></strong>
          <?= h(__('We keep your address data only as long as you use Imprint Protection.')) ?></li>
        <li><strong><?= h(__('You can end it at any time.')) ?></strong>
          <?= h(__('If you end the service, everything is deleted irrevocably: imprint pages, address data and ID photos.')) ?>
          <a href="/portal/terminate.php"><?= h(__('How to end the service')) ?></a></li>
      </ol>
      <p class="pf-small"><?= h(__('More details in our')) ?> <a href="/privacy/"><?= h(__('Privacy Policy')) ?></a>.</p>
    </div>
  </div>
</div>
