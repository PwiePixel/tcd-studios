<?php
/**
 * @var array<string,mixed> $status @var string $formError @var string $keyValue
 */
defined('TCD_APP') || exit;
$probe = $status['probe'];
$jsVersion = (int) @filemtime(TCD_ROOT . '/js/vault.js');
?>
<h1><?= h(__('Identity vault')) ?></h1>
<?= render_partial('admin/_subnav', ['current' => 'vault.php']) ?>
<p class="pf-muted"><?= h(__('Customers confirm their address with a photo of their ID card. The photo is encrypted in the customer\'s upload with the PUBLIC key below; the PRIVATE key is never on any server. The team decrypts the photo in the browser, checks it and approves. Afterwards only the encrypted file stays in the secure archive until the service ends.')) ?></p>

<?php if (!$status['key_ok']): ?>
  <div class="pf-notice pf-notice--warn"><strong><?= h(__('No public key is set.')) ?></strong> <?= h(__('Customers cannot upload ID photos until you generate or paste a key below.')) ?></div>
<?php endif; ?>
<?php if ($status['in_web_root']): ?>
  <div class="pf-notice pf-notice--warn"><strong><?= h(__('The archive directory is inside the web root.')) ?></strong>
    <?= h(__('It must be OUTSIDE the web root or on a separate storage system. Set vault.dir in data/config.local.php (or the environment variable TCD_VAULT_DIR) to such a directory, or use the S3 driver.')) ?></div>
<?php endif; ?>
<?php if ($status['driver'] === 's3' && $status['s3_missing']): ?>
  <div class="pf-notice pf-notice--warn"><strong><?= h(__('The S3 configuration is incomplete.')) ?></strong> <?= h(sprintf(__('Missing: %s (data/config.local.php, section vault.s3).'), implode(', ', $status['s3_missing']))) ?></div>
<?php endif; ?>

<section class="pf-card" id="status">
  <h2><?= h(__('Status')) ?></h2>
  <dl class="pf-kv">
    <dt><?= h(__('Public key')) ?></dt>
    <dd><?php if ($status['key_ok']): ?><span class="pf-badge pf-badge--ok"><?= h(__('set')) ?></span> <span class="pf-muted">RSA <?= (int) $status['key_bits'] ?> bit</span>
      <?php elseif ($status['key_set']): ?><span class="pf-badge pf-badge--rejected"><?= h(__('invalid')) ?></span> <span class="pf-error-text"><?= h($status['key_error']) ?></span>
      <?php else: ?><span class="pf-badge pf-badge--pending"><?= h(__('not set')) ?></span><?php endif; ?></dd>
<?php if ($status['fingerprint'] !== ''): ?>
    <dt><?= h(__('Fingerprint (SHA-256)')) ?></dt><dd><code class="pf-fp" data-vault-current-fp><?= h($status['fingerprint']) ?></code></dd>
<?php endif; ?>
    <dt><?= h(__('Archive driver')) ?></dt>
    <dd><?= h($status['driver'] === 's3' ? __('S3-compatible object storage') : __('Directory')) ?></dd>
    <dt><?= h(__('Archive location')) ?></dt>
    <dd class="pf-break"><code><?= h($status['location']) ?></code>
      <?php if ($status['driver'] === 'directory'): ?>
        <div class="pf-badges">
        <?php if ($status['in_web_root']): ?><span class="pf-badge pf-badge--rejected"><?= h(__('inside the web root')) ?></span>
        <?php else: ?><span class="pf-badge pf-badge--ok"><?= h(__('outside the web root')) ?></span><?php endif; ?>
        <?php if ($status['dir_writable'] === true): ?><span class="pf-badge pf-badge--ok"><?= h(__('writable')) ?></span>
        <?php else: ?><span class="pf-badge pf-badge--rejected"><?= h($status['dir_exists'] ? __('not writable') : __('does not exist yet')) ?></span><?php endif; ?>
        </div>
      <?php endif; ?></dd>
    <dt><?= h(__('Files in the web server inbox')) ?></dt>
    <dd><strong><?= (int) $status['inbox_files'] ?></strong>
      <?php if ($status['inbox_oldest'] !== null): ?> <span class="pf-muted"><?= h(__('oldest:')) ?> <?= h(admin_age($status['inbox_oldest'])) ?></span><?php endif; ?>
      <?php if ($status['inbox_stale'] > 0): ?> <span class="pf-badge pf-badge--pending"><?= h(sprintf(__('%d older than %d days'), (int) $status['inbox_stale'], ADMIN_VAULT_STALE_DAYS)) ?></span><?php endif; ?></dd>
    <dt><?= h(__('Files in the secure archive')) ?></dt><dd><strong><?= (int) $status['archive_files'] ?></strong></dd>
    <dt><?= h(__('Data encryption key')) ?></dt>
    <dd><?php if ($status['data_key']): ?><span class="pf-badge pf-badge--ok"><?= h(__('present in data/config.local.php')) ?></span>
      <?php else: ?><span class="pf-badge pf-badge--pending"><?= h(__('not created yet')) ?></span><?php endif; ?>
      <div class="pf-help"><?= h(__('The address data in the database is encrypted with this key (data_key). Back up data/config.local.php TOGETHER with the database – without the key the address data can never be read again.')) ?></div></dd>
  </dl>
</section>

<section class="pf-card pf-card--accent" id="generator" data-vault-generator>
  <h2><?= h(__('Step 1: generate a key pair (in this browser)')) ?></h2>
  <div class="pf-notice pf-notice--danger" role="note">
    <strong><?= h(__('Read this before you generate a key:')) ?></strong>
    <ul class="pf-list">
      <li><?= h(__('The private key is the ONLY way to read the stored ID photos. Keep the file OFFLINE in a safe place (for example on an encrypted USB stick in a safe) and make at least one backup.')) ?></li>
      <li><?= h(__('Never upload the private key anywhere, never send it by e-mail or chat, never store it on the web server.')) ?></li>
      <li><strong><?= h(__('If the private key is lost, the stored ID photos can never be read again.')) ?></strong> <?= h(__('Nobody can recover them, not even the server operator.')) ?></li>
      <li><?= h(__('The key pair is created inside this browser. The server never receives the private key – only the public key is saved.')) ?></li>
    </ul>
  </div>
  <p class="pf-muted pf-small" data-vault-nojs><?= h(__('Generating keys in the browser needs JavaScript with WebCrypto (an https:// page). Without it, create the key pair on a trusted computer instead:')) ?></p>
  <pre class="pf-code" data-vault-nojs>openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out id-vault-private-key.pem
openssl pkey -in id-vault-private-key.pem -pubout -out id-vault-public-key.pem</pre>
  <div class="pf-actions"><button class="pf-btn" type="button" data-vault-generate disabled><?= h(__('Generate 4096-bit key pair')) ?></button></div>
  <p class="pf-muted pf-small" data-vault-status role="status" aria-live="polite"></p>
  <div data-vault-result hidden>
    <div class="pf-notice pf-notice--ok"><?= h(__('The key pair was generated. The public key was filled into the form below.')) ?></div>
    <dl class="pf-kv"><dt><?= h(__('Fingerprint (SHA-256)')) ?></dt><dd><code class="pf-fp" data-vault-fp></code></dd></dl>
    <div class="pf-actions">
      <a class="pf-btn pf-btn--primary" href="#generator" download="id-vault-private-key.pem" data-vault-download><?= h(__('Download the private key (id-vault-private-key.pem)')) ?></a>
    </div>
    <p class="pf-error-text"><?= h(__('Download it NOW. It exists only in this browser tab and cannot be shown or downloaded again once you leave this page.')) ?></p>
    <div class="pf-check">
      <input type="checkbox" id="vault_stored" data-vault-stored>
      <label for="vault_stored"><?= h(__('I downloaded the private key, stored it offline in a safe place and made a backup. I know that the ID photos are lost forever if it is lost.')) ?></label>
    </div>
  </div>
</section>

<section class="pf-card" id="public-key">
  <h2><?= h(__('Step 2: save the public key')) ?></h2>
  <?php if ($formError !== ''): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($formError) ?></div><?php endif; ?>
  <form class="pf-form pf-wide-form" method="post" action="/admin/vault.php">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_key">
    <div class="pf-field">
      <label for="public_key"><?= h(__('Public key (PEM)')) ?></label>
      <textarea id="public_key" name="public_key" rows="9" spellcheck="false" autocomplete="off" autocapitalize="off" maxlength="8000" class="pf-mono" placeholder="-----BEGIN PUBLIC KEY-----&#10;…&#10;-----END PUBLIC KEY-----" data-vault-public-key><?= h($keyValue) ?></textarea>
      <div class="pf-help"><?= h(__('RSA, at least 3072 bit (the generator above creates 4096 bit). Only the PUBLIC key goes here – never paste a private key.')) ?></div>
    </div>
<?php if ($status['key_set']): ?>
    <div class="pf-notice pf-notice--danger">
      <strong><?= h(__('A public key is already set. Replacing it is dangerous.')) ?></strong>
      <?= h(__('Files that were encrypted for the current key (fingerprint above) can ONLY be read with the OLD private key. If you replace the key and lose or destroy the old private key, those ID photos are gone for good. Keep BOTH private keys.')) ?>
      <?php if ($status['inbox_files'] + $status['archive_files'] > 0): ?>
        <strong><?= h(sprintf(__('%d stored file(s) are affected.'), (int) ($status['inbox_files'] + $status['archive_files']))) ?></strong>
      <?php endif; ?>
      <div class="pf-check">
        <input type="checkbox" id="confirm_replace" name="confirm_replace" value="1">
        <label for="confirm_replace"><?= h(__('I understand: files encrypted for the old key need the OLD private key. I still have it and will keep it.')) ?></label>
      </div>
    </div>
<?php endif; ?>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit" data-vault-save><?= h($status['key_set'] ? __('Replace public key') : __('Save public key')) ?></button></div>
  </form>
</section>

<section class="pf-card" id="archive-test">
  <h2><?= h(__('Test the archive')) ?></h2>
  <p class="pf-muted"><?= h(__('Writes a small probe object into the archive, reads it back and deletes it again. It contains no personal data.')) ?></p>
  <?php if ($probe !== null): ?>
    <p><span class="pf-badge pf-badge--<?= $probe['ok'] ? 'ok' : 'rejected' ?>"><?= h($probe['ok'] ? __('last test OK') : __('last test FAILED')) ?></span>
      <span class="pf-muted"><?= h(fmt_time($probe['at'])) ?> (<?= h(admin_age($probe['at'])) ?>)</span><br>
      <span class="pf-small"><?= h($probe['detail']) ?></span></p>
  <?php endif; ?>
  <form class="pf-form" method="post" action="/admin/vault.php"><?= csrf_field() ?><input type="hidden" name="action" value="probe">
    <button class="pf-btn" type="submit"><?= h(__('Test archive')) ?></button></form>
</section>

<script src="/js/vault.js?v=<?= $jsVersion ?>" defer></script>
