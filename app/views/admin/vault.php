<?php
/**
 * @var array<string,mixed> $status @var string $formError @var string $keyValue
 * @var array<string,array{configured:mixed,effective:mixed,source:string,env:string}> $archiveInfo
 * @var array<string,string> $archiveErrors @var array<string,string> $archiveForm @var string[] $archiveLocked
 * @var array{dir:int,s3:int} $archiveCounts @var bool $offerProbe
 */
defined('TCD_APP') || exit;
$probe = $status['probe'];
$aval = static function (string $key) use ($archiveForm, $archiveInfo): string {
    if (array_key_exists($key, $archiveForm)) {
        return (string) $archiveForm[$key];
    }
    $v = $archiveInfo[$key]['configured'] ?? '';
    return is_bool($v) ? ($v ? 'true' : 'false') : (is_scalar($v) ? (string) $v : '');
};
$aerr = static function (string $key) use ($archiveErrors): string {
    return isset($archiveErrors[$key]) ? (string) $archiveErrors[$key] : '';
};
$asrc = static function (string $key) use ($archiveInfo): string {
    return isset($archiveInfo[$key]) ? admin_setting_source_label($archiveInfo[$key]) : '';
};
$locked = static function (string $key) use ($archiveLocked): bool {
    return in_array($key, $archiveLocked, true);
};
$archDriver = $aval('vault.driver') === 's3' ? 's3' : 'directory';
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
    <?= h(__('It must be OUTSIDE the web root or on a separate storage system. Choose such a directory under "Archive settings" below, or use S3 storage.')) ?></div>
<?php endif; ?>
<?php if ($status['driver'] === 's3' && $status['s3_missing']): ?>
  <div class="pf-notice pf-notice--warn"><strong><?= h(__('The S3 configuration is incomplete.')) ?></strong> <?= h(sprintf(__('Missing: %s (see "Archive settings" below).'), implode(', ', $status['s3_missing']))) ?></div>
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
      <div class="pf-field">
        <label for="admin_password"><?= h(__('Your current password')) ?></label>
        <input type="password" id="admin_password" name="admin_password" autocomplete="current-password">
        <div class="pf-help"><?= h(__('Required to replace the key: a replaced key redirects all new ID uploads.')) ?></div>
      </div>
    </div>
<?php endif; ?>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit" data-vault-save><?= h($status['key_set'] ? __('Replace public key') : __('Save public key')) ?></button></div>
  </form>
</section>

<section class="pf-card" id="archive-settings">
  <h2><?= h(__('Archive settings')) ?></h2>
  <p class="pf-muted"><?= h(__('Where the encrypted ID photos are kept after approval: a directory OUTSIDE the web root (ideally on a separate volume or storage server) or an S3-compatible object storage. Saved in data/config.local.php.')) ?></p>
  <?php if ($archiveErrors): ?><div class="pf-flash pf-flash--error" role="alert"><?= h(isset($archiveErrors['_location']) ? $archiveErrors['_location'] : __('Nothing was saved. Please correct the marked fields.')) ?></div><?php endif; ?>
  <?php if ($archiveLocked): ?>
    <div class="pf-notice pf-notice--info"><strong><?= h(sprintf(__('%d archived ID file(s) exist: the archive location is locked.'), $archiveCounts['dir'] + $archiveCounts['s3'])) ?></strong>
      <?= h(__('Each archived file is found by its name inside the current location. Moving the location would make these files unreadable and impossible to erase when the service ends, so the archive type, directory, endpoint and bucket cannot be changed while they exist. Access keys, region, path style and key prefix can still be changed (for example to rotate the keys).')) ?></div>
  <?php endif; ?>
  <form class="pf-form pf-wide-form" method="post" action="/admin/vault.php#archive-settings">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_archive">
    <div class="pf-field">
      <label for="vault_driver"><?= h(__('Archive type')) ?></label>
      <?php if ($locked('vault.driver')): ?><input type="hidden" name="vault_driver" value="<?= h($archDriver) ?>"><?php endif; ?>
      <select id="vault_driver"<?= $locked('vault.driver') ? ' disabled' : ' name="vault_driver"' ?><?= $aerr('vault.driver') !== '' ? ' aria-invalid="true"' : '' ?>>
        <option value="directory"<?= $archDriver === 'directory' ? ' selected' : '' ?>><?= h(__('Directory on this server (outside the web root)')) ?></option>
        <option value="s3"<?= $archDriver === 's3' ? ' selected' : '' ?>><?= h(__('S3-compatible object storage')) ?></option>
      </select>
      <div class="pf-help"><?= h(__('Source:')) ?> <?= h($asrc('vault.driver')) ?></div>
      <?php if ($aerr('vault.driver') !== ''): ?><div class="pf-error-text"><?= h($aerr('vault.driver')) ?></div><?php endif; ?>
    </div>
    <fieldset class="pf-fieldset">
      <legend><?= h(__('Directory')) ?></legend>
      <div class="pf-field">
        <label for="vault_dir"><?= h(__('Archive directory')) ?></label>
        <input type="text" id="vault_dir" name="vault_dir" maxlength="1024" placeholder="/srv/tcd-vault-archive" autocomplete="off" spellcheck="false" value="<?= h($aval('vault.dir')) ?>"<?= $locked('vault.dir') ? ' readonly' : '' ?><?= $aerr('vault.dir') !== '' ? ' aria-invalid="true"' : '' ?>>
        <div class="pf-help"><?= h(__('Absolute path OUTSIDE the web root. Only the last folder is created if it is missing (a missing mount point is never replaced by a local folder). Empty = data/vault-archive in the data directory.')) ?>
          <?= h(__('Source:')) ?> <?= h($asrc('vault.dir')) ?><?php if (($archiveInfo['vault.dir']['source'] ?? '') === 'env'): ?> – <?= h(__('in effect:')) ?> <code><?= h((string) $archiveInfo['vault.dir']['effective']) ?></code><?php endif; ?></div>
        <?php if ($aerr('vault.dir') !== ''): ?><div class="pf-error-text"><?= h($aerr('vault.dir')) ?></div><?php endif; ?>
      </div>
    </fieldset>
    <fieldset class="pf-fieldset">
      <legend><?= h(__('S3-compatible object storage')) ?></legend>
      <?php foreach (['vault.s3.endpoint' => [__('Endpoint'), 'https://s3.eu-central-1.amazonaws.com', __('Full https:// address of the storage service, without bucket and path.')],
                      'vault.s3.region' => [__('Region'), 'eu-central-1', __('As shown by your provider (used for the request signature).')],
                      'vault.s3.bucket' => [__('Bucket'), 'tcd-id-archive', __('Create a PRIVATE bucket (no public access), ideally with versioning off and server-side encryption on.')],
                      'vault.s3.prefix' => [__('Key prefix'), 'tcd-id/', __('Folder inside the bucket for new files. Changing it only affects files archived from now on.')]] as $key => [$label, $ph, $help]):
          $f = str_replace('.', '_', $key); $e = $aerr($key); ?>
        <div class="pf-field">
          <label for="<?= h($f) ?>"><?= h($label) ?></label>
          <input type="text" id="<?= h($f) ?>" name="<?= h($f) ?>" maxlength="300" placeholder="<?= h($ph) ?>" autocomplete="off" spellcheck="false" value="<?= h($aval($key)) ?>"<?= $locked($key) ? ' readonly' : '' ?><?= $e !== '' ? ' aria-invalid="true"' : '' ?>>
          <div class="pf-help"><?= h($help) ?> <?= h(__('Source:')) ?> <?= h($asrc($key)) ?></div>
          <?php if ($e !== ''): ?><div class="pf-error-text"><?= h($e) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="pf-check">
        <input type="checkbox" id="vault_s3_path_style" name="vault_s3_path_style" value="1"<?= $aval('vault.s3.path_style') === 'true' ? ' checked' : '' ?>>
        <label for="vault_s3_path_style"><?= h(__('Path-style requests (endpoint/bucket/key) – needed by MinIO and many S3-compatible providers; untick for virtual-hosted style (bucket.endpoint)')) ?></label>
      </div>
      <?php foreach (['vault.s3.access_key' => __('Access key'), 'vault.s3.secret_key' => __('Secret key')] as $key => $label):
          $f = str_replace('.', '_', $key); $e = $aerr($key); $isSet = (string) ($archiveInfo[$key]['configured'] ?? '') !== ''; ?>
        <div class="pf-field">
          <label for="<?= h($f) ?>"><?= h($label) ?>
            <?php if ($isSet): ?><span class="pf-badge pf-badge--ok"><?= h(__('set')) ?></span><?php else: ?><span class="pf-badge pf-badge--draft"><?= h(__('not set')) ?></span><?php endif; ?></label>
          <input type="password" id="<?= h($f) ?>" name="<?= h($f) ?>" maxlength="256" autocomplete="new-password" spellcheck="false" value=""<?= $e !== '' ? ' aria-invalid="true"' : '' ?>>
          <div class="pf-help"><?= h(__('Write-only: the stored value is never shown. Leave empty to keep it. Use a key that may only read, write and delete objects in this bucket.')) ?></div>
          <?php if ($e !== ''): ?><div class="pf-error-text"><?= h($e) ?></div><?php endif; ?>
        </div>
        <?php if ($isSet): ?>
        <div class="pf-check">
          <input type="checkbox" id="<?= h($f) ?>_clear" name="<?= h($f) ?>_clear" value="1">
          <label for="<?= h($f) ?>_clear"><?= h(sprintf(__('Remove the stored %s'), mb_strtolower($label, 'UTF-8'))) ?></label>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </fieldset>
    <div class="pf-field">
      <label for="archive_admin_password"><?= h(__('Your current password')) ?></label>
      <input type="password" id="archive_admin_password" name="admin_password" autocomplete="current-password"<?= $aerr('admin_password') !== '' ? ' aria-invalid="true"' : '' ?>>
      <div class="pf-help"><?= h(__('Required for every change of the archive settings.')) ?></div>
      <?php if ($aerr('admin_password') !== ''): ?><div class="pf-error-text"><?= h($aerr('admin_password')) ?></div><?php endif; ?>
    </div>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Save archive settings')) ?></button></div>
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
  <?php if ($offerProbe): ?><div class="pf-notice pf-notice--info"><?= h(__('The archive settings were changed. Test them now:')) ?></div><?php endif; ?>
  <form class="pf-form" method="post" action="/admin/vault.php"><?= csrf_field() ?><input type="hidden" name="action" value="probe">
    <button class="pf-btn<?= $offerProbe ? ' pf-btn--primary' : '' ?>" type="submit"><?= h(__('Test archive')) ?></button></form>
</section>

<script src="/js/vault.js?v=<?= $jsVersion ?>" defer></script>
