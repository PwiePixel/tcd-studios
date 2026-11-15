<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Identity vault for ID document photos.
 *
 * Design (the web server can NEVER read an ID photo after the upload request has finished):
 *  1. A key pair is created by the owner in the admin panel (in the browser; the private key is downloaded and kept
 *     offline). Only the PUBLIC key (RSA ≥ 3072 bit) is stored on the server (setting "vault.public_key").
 *  2. Upload: the image is validated, re-encoded (strips metadata) and encrypted immediately in memory with a random
 *     AES-256-GCM key; that key is wrapped with the public key (RSA-OAEP, SHA-1 as PHP's openssl does).
 *     Only the ciphertext is written to the inbox (data/vault-inbox).
 *  3. Review: an admin decrypts it IN THE BROWSER (js/vault.js, WebCrypto) with the private key file; the key never
 *     leaves the admin's browser.
 *  4. After approval the ciphertext is moved to the archive (driver 'directory' or S3-compatible storage) and the
 *     inbox copy is overwritten and deleted. It stays there until the customer ends the service (erase.php).
 *
 * Blob format ("TCDV1"): "TCDV1" | uint16 BE n | wrapped key (n bytes) | iv (12) | AES-GCM ciphertext | tag (16)
 */

const VAULT_MAGIC = 'TCDV1';

/* ----------------------------------------------------------------- keys */

/** PEM of the configured public key ('' if none). */
function vault_public_key_pem(): string
{
    return trim((string) setting_get('vault.public_key', ''));
}

/** Validate a public key. @return string|null error message or null if usable */
function vault_validate_public_key(string $pem): ?string
{
    $key = @openssl_pkey_get_public($pem);
    if ($key === false) {
        return __('This is not a valid PEM public key.');
    }
    $details = openssl_pkey_get_details($key);
    if (!is_array($details) || ($details['type'] ?? -1) !== OPENSSL_KEYTYPE_RSA) {
        return __('Only RSA keys are supported.');
    }
    if ((int) ($details['bits'] ?? 0) < 3072) {
        return __('The RSA key must have at least 3072 bits.');
    }
    return null;
}

/** Store the public key (normalised PEM). @throws InvalidArgumentException when invalid */
function vault_set_public_key(string $pem): void
{
    $err = vault_validate_public_key($pem);
    if ($err !== null) {
        throw new InvalidArgumentException($err);
    }
    $details = openssl_pkey_get_details(openssl_pkey_get_public($pem));
    setting_set('vault.public_key', trim((string) $details['key']));
}

/** SHA-256 fingerprint (hex groups) of a public key, for display and for comparing with the private key file. */
function vault_fingerprint(string $pem): string
{
    $key = @openssl_pkey_get_public($pem);
    $details = $key ? openssl_pkey_get_details($key) : false;
    if (!is_array($details)) {
        return '';
    }
    $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', (string) $details['key']), true);
    return $der === false ? '' : implode(':', str_split(hash('sha256', $der), 4));
}

/** Is the vault ready to accept uploads (public key present and valid)? */
function vault_configured(): bool
{
    $pem = vault_public_key_pem();
    return $pem !== '' && vault_validate_public_key($pem) === null;
}

/** May ID photos be uploaded over this connection? (HTTPS only, unless config vault.require_https is off or a test flag is set.) */
function vault_transport_ok(): bool
{
    return is_https() || !config('vault.require_https', true) || getenv('TCD_ALLOW_INSECURE_UPLOADS') === '1';
}

/* ----------------------------------------------------------- encryption */

/** Encrypt $plain for the holder of the private key belonging to $pem. @throws RuntimeException */
function vault_encrypt(string $plain, string $pem): string
{
    $pub = openssl_pkey_get_public($pem);
    if ($pub === false) {
        throw new RuntimeException('vault: invalid public key');
    }
    $aesKey = random_bytes(32);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    $wrapped = '';
    if ($cipher === false || strlen($tag) !== 16 || !openssl_public_encrypt($aesKey, $wrapped, $pub, OPENSSL_PKCS1_OAEP_PADDING)) {
        throw new RuntimeException('vault: encryption failed');
    }
    sodium_memzero($aesKey);
    return VAULT_MAGIC . pack('n', strlen($wrapped)) . $wrapped . $iv . $cipher . $tag;
}

/**
 * Decrypt a blob with a PRIVATE key. Only for tools/tests: the web application never has the private key.
 *
 * @throws RuntimeException
 */
function vault_decrypt_with_private(string $blob, string $privatePem): string
{
    if (strncmp($blob, VAULT_MAGIC, 5) !== 0 || strlen($blob) < 5 + 2 + 12 + 16) {
        throw new RuntimeException('vault: not a TCDV1 blob');
    }
    $n = (int) unpack('n', substr($blob, 5, 2))[1];
    $wrapped = substr($blob, 7, $n);
    $iv = substr($blob, 7 + $n, 12);
    $rest = substr($blob, 7 + $n + 12);
    $tag = substr($rest, -16);
    $cipher = substr($rest, 0, -16);
    $priv = openssl_pkey_get_private($privatePem);
    $aesKey = '';
    if ($priv === false || !openssl_private_decrypt($wrapped, $aesKey, $priv, OPENSSL_PKCS1_OAEP_PADDING)) {
        throw new RuntimeException('vault: cannot unwrap the key (wrong private key?)');
    }
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        throw new RuntimeException('vault: authentication failed');
    }
    return $plain;
}

/* --------------------------------------------------------- upload checks */

/**
 * Validate an uploaded image and normalise it: real image (jpeg/png/webp), size limits, re-encoded as JPEG without
 * any metadata (EXIF/GPS, embedded payloads), longest side ≤ 3000 px.
 *
 * @param array<string,mixed> $file one entry of $_FILES
 * @return array{plain:string,mime:string}
 * @throws InvalidArgumentException with a customer-friendly message
 */
function vault_prepare_image(array $file): array
{
    $max = (int) config('vault.max_upload_bytes', 8388608);
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException(__('Please choose an image file.'));
    }
    if (($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE || (int) ($file['size'] ?? 0) > $max) {
        throw new InvalidArgumentException(sprintf(__('The image is too large (maximum %d MB).'), (int) ($max / 1048576)));
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK || $tmp === '' || (!is_uploaded_file($tmp) && getenv('TCD_ALLOW_TEST_UPLOADS') !== '1')) {
        throw new InvalidArgumentException(__('The upload failed. Please try again.'));
    }
    $info = @getimagesize($tmp);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] < 400 || $info[1] < 250) {
        throw new InvalidArgumentException(__('Please upload a clear JPEG, PNG or WebP photo (at least 400 × 250 pixels).'));
    }
    // Decoding needs roughly 5 bytes per pixel: refuse what the server cannot process instead of dying with a fatal error.
    $pixels = (int) $info[0] * (int) $info[1];
    @ini_set('memory_limit', '512M');
    $limit = ini_get('memory_limit');
    $bytes = $limit === '-1' ? PHP_INT_MAX : (int) $limit * (stripos((string) $limit, 'G') !== false ? 1073741824 : (stripos((string) $limit, 'K') !== false ? 1024 : 1048576));
    if ($pixels > 50000000 || $pixels * 5 + 64 * 1048576 > $bytes) {
        throw new InvalidArgumentException(__('The image is too large to process. Please reduce its size (for example to 12 megapixels) and try again.'));
    }
    $raw = file_get_contents($tmp);
    $img = $raw === false ? false : @imagecreatefromstring($raw);
    if ($img === false) {
        throw new InvalidArgumentException(__('The image could not be read. Please upload another photo.'));
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1.0, 3000 / max($w, $h));
    if ($scale < 1.0) {
        $img = imagescale($img, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)), IMG_BICUBIC);
        if ($img === false) {
            throw new InvalidArgumentException(__('The image could not be processed. Please upload another photo.'));
        }
    }
    ob_start();
    $ok = imagejpeg($img, null, 88);
    $jpeg = (string) ob_get_clean();
    if (!$ok || $jpeg === '') {
        throw new InvalidArgumentException(__('The image could not be processed. Please upload another photo.'));
    }
    return ['plain' => $jpeg, 'mime' => 'image/jpeg'];
}

/* -------------------------------------------------------------- storage */

/** Inbox directory for freshly uploaded ciphertext (never web-served; created 0700). */
function vault_inbox_dir(): string
{
    $dir = data_dir() . '/vault-inbox';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

/** Archive directory of the 'directory' driver. */
function vault_archive_dir(): string
{
    $env = getenv('TCD_VAULT_DIR');
    $configured = ($env !== false && $env !== '') ? $env : trim((string) config('vault.dir', ''));
    $dir = $configured !== '' ? rtrim($configured, '/') : data_dir() . '/vault-archive';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

/** Overwrite a file with random bytes, then delete it (best effort; SSD wear levelling cannot be controlled). */
function vault_secure_unlink(string $path): bool
{
    if (!is_file($path)) {
        return true;
    }
    $size = (int) @filesize($path);
    $fh = @fopen($path, 'r+b');
    if ($fh !== false) {
        for ($left = $size; $left > 0; $left -= 65536) {
            fwrite($fh, random_bytes(min(65536, $left)));
        }
        fflush($fh);
        fclose($fh);
    }
    return @unlink($path) || !is_file($path);
}

/** Valid name of a vault object. */
function vault_valid_name(string $name): bool
{
    return preg_match('/^idf-[a-f0-9]{32}\.tcdv$/D', $name) === 1;
}

/** Put ciphertext into the archive. @return string archive reference ("dir:<name>" or "s3:<key>") @throws RuntimeException */
function vault_archive_put(string $name, string $blob): string
{
    if (!vault_valid_name($name)) {
        throw new RuntimeException('vault: bad object name');
    }
    if ((string) config('vault.driver', 'directory') === 's3') {
        $key = (string) config('vault.s3.prefix', 'tcd-id/') . $name;
        vault_s3_request('PUT', $key, $blob);
        return 's3:' . $key;
    }
    $path = vault_archive_dir() . '/' . $name;
    $tmp = $path . '.part';
    if (@file_put_contents($tmp, $blob, LOCK_EX) === false || !@rename($tmp, $path)) {
        throw new RuntimeException('vault: cannot write the archive file');
    }
    @chmod($path, 0600);
    return 'dir:' . $name;
}

/** Read ciphertext from the archive. @throws RuntimeException */
function vault_archive_get(string $ref): string
{
    if (strncmp($ref, 's3:', 3) === 0) {
        return vault_s3_request('GET', substr($ref, 3), '');
    }
    $name = substr($ref, 4);
    $data = strncmp($ref, 'dir:', 4) === 0 && vault_valid_name($name) ? @file_get_contents(vault_archive_dir() . '/' . $name) : false;
    if ($data === false) {
        throw new RuntimeException('vault: archive object not found');
    }
    return $data;
}

/** Delete from the archive (a missing object counts as deleted). @throws RuntimeException if it could not be removed */
function vault_archive_delete(string $ref): void
{
    if (strncmp($ref, 's3:', 3) === 0) {
        vault_s3_request('DELETE', substr($ref, 3), '');
        return;
    }
    $name = substr($ref, 4);
    if (strncmp($ref, 'dir:', 4) !== 0 || !vault_valid_name($name)) {
        throw new RuntimeException('vault: bad archive reference');
    }
    if (!vault_secure_unlink(vault_archive_dir() . '/' . $name)) {
        throw new RuntimeException('vault: cannot delete the archive file');
    }
}

/**
 * Minimal S3-compatible client (AWS Signature V4, path-style or virtual-host-style) for PUT/GET/DELETE of one object.
 *
 * @return string response body (GET) or ''
 * @throws RuntimeException on transport errors or HTTP status ≥ 300 (404 on DELETE counts as success)
 */
function vault_s3_request(string $method, string $key, string $body): string
{
    $c = (array) config('vault.s3', []);
    foreach (['endpoint', 'bucket', 'access_key', 'secret_key', 'region'] as $k) {
        if (empty($c[$k])) {
            throw new RuntimeException('vault: S3 setting "' . $k . '" is missing (data/config.local.php)');
        }
    }
    $endpoint = rtrim((string) $c['endpoint'], '/');
    $parts = parse_url($endpoint);
    if ($parts === false || ($parts['scheme'] ?? '') !== 'https' && !in_array((string) ($parts['host'] ?? ''), ['127.0.0.1', 'localhost'], true)) {
        throw new RuntimeException('vault: the S3 endpoint must use https');
    }
    $host = (string) $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $pathStyle = !empty($c['path_style']);
    $uriPath = ($pathStyle ? '/' . rawurlencode((string) $c['bucket']) : '') . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
    $reqHost = $pathStyle ? $host : (string) $c['bucket'] . '.' . $host;
    $t = gmdate('Ymd\THis\Z');
    $date = substr($t, 0, 8);
    $payloadHash = hash('sha256', $body);
    $headers = ['host' => $reqHost, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $t];
    ksort($headers);
    $canonHeaders = '';
    foreach ($headers as $k => $v) {
        $canonHeaders .= $k . ':' . trim($v) . "\n";
    }
    $signed = implode(';', array_keys($headers));
    $canonical = $method . "\n" . $uriPath . "\n\n" . $canonHeaders . "\n" . $signed . "\n" . $payloadHash;
    $scope = $date . '/' . $c['region'] . '/s3/aws4_request';
    $toSign = "AWS4-HMAC-SHA256\n" . $t . "\n" . $scope . "\n" . hash('sha256', $canonical);
    $kSigning = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', (string) $c['region'],
        hash_hmac('sha256', $date, 'AWS4' . $c['secret_key'], true), true), true), true);
    $auth = 'AWS4-HMAC-SHA256 Credential=' . $c['access_key'] . '/' . $scope . ', SignedHeaders=' . $signed
        . ', Signature=' . hash_hmac('sha256', $toSign, $kSigning);

    $ch = curl_init(($parts['scheme'] ?? 'https') . '://' . $reqHost . $uriPath);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Authorization: ' . $auth, 'x-amz-date: ' . $t, 'x-amz-content-sha256: ' . $payloadHash, 'Content-Type: application/octet-stream'],
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        throw new RuntimeException('vault: S3 transport error: ' . $error);
    }
    if ($status >= 300 && !($method === 'DELETE' && $status === 404)) {
        throw new RuntimeException('vault: S3 answered HTTP ' . $status);
    }
    return (string) $response;
}

/* ----------------------------------------------- identity requests/files */

/** The newest identity request of a profile (any status except erased), or null. @return array<string,mixed>|null */
function identity_request_for_profile(int $profileId): ?array
{
    return db_one('SELECT * FROM identity_requests WHERE profile_id = ? AND status != \'erased\' ORDER BY id DESC LIMIT 1', [$profileId]);
}

function identity_request_get(int $id): ?array
{
    return db_one('SELECT * FROM identity_requests WHERE id = ?', [$id]);
}

/** @return array<int,array<string,mixed>> files of a request that still exist (not deleted), front before back */
function identity_files(int $requestId): array
{
    return db_all('SELECT * FROM identity_files WHERE request_id = ? AND deleted_at IS NULL ORDER BY side', [$requestId]);
}

function identity_file_get(int $fileId): ?array
{
    return db_one('SELECT * FROM identity_files WHERE id = ?', [$fileId]);
}

/** The open ('uploaded') request of a profile or a new one. @return int request id */
function identity_request_open(int $userId, int $profileId): int
{
    return (int) db_transaction(static function () use ($userId, $profileId) {
        $row = db_one('SELECT id FROM identity_requests WHERE profile_id = ? AND user_id = ? AND status = \'uploaded\' ORDER BY id DESC LIMIT 1', [$profileId, $userId]);
        if ($row !== null) {
            return (int) $row['id'];
        }
        $t = now();
        return db_insert(
            'INSERT INTO identity_requests (user_id, profile_id, status, created_at, updated_at) VALUES (?,?,?,?,?)',
            [$userId, $profileId, 'uploaded', $t, $t]
        );
    });
}

/**
 * Validate, encrypt and store one uploaded image (replaces an earlier upload of the same side of the same request).
 *
 * @param array<string,mixed> $upload entry of $_FILES
 * @return int identity_files id
 * @throws InvalidArgumentException (customer-facing message) | RuntimeException (technical)
 */
function vault_store_upload(int $requestId, string $side, array $upload): int
{
    if (!in_array($side, ['front', 'back'], true)) {
        throw new InvalidArgumentException('bad side');
    }
    if (!vault_configured()) {
        throw new RuntimeException('vault: not configured (no public key)');
    }
    $req = identity_request_get($requestId);
    if ($req === null || $req['status'] !== 'uploaded') {
        throw new RuntimeException('vault: request is not open for uploads');
    }
    $prepared = vault_prepare_image($upload);
    $blob = vault_encrypt($prepared['plain'], vault_public_key_pem());
    $name = 'idf-' . random_token(16) . '.tcdv';
    $path = vault_inbox_dir() . '/' . $name;
    if (@file_put_contents($path, $blob, LOCK_EX) === false) {
        throw new RuntimeException('vault: cannot write to the inbox');
    }
    @chmod($path, 0600);
    $plainSize = strlen($prepared['plain']);
    unset($prepared);
    $old = db_all('SELECT id FROM identity_files WHERE request_id = ? AND side = ? AND deleted_at IS NULL', [$requestId, $side]);
    $id = db_insert(
        'INSERT INTO identity_files (request_id, side, mime, plain_size, enc_size, enc_sha256, inbox_name, created_at) VALUES (?,?,?,?,?,?,?,?)',
        [$requestId, $side, 'image/jpeg', $plainSize, strlen($blob), hash('sha256', $blob), $name, now()]
    );
    foreach ($old as $o) {
        vault_delete_file((int) $o['id']);
    }
    db_exec('UPDATE identity_requests SET updated_at = ? WHERE id = ?', [now(), $requestId]);
    return $id;
}

/** Ciphertext of a file (inbox or archive). @throws RuntimeException */
function vault_file_blob(int $fileId): string
{
    $f = identity_file_get($fileId);
    if ($f === null || $f['deleted_at'] !== null) {
        throw new RuntimeException('vault: file not found');
    }
    if ($f['archive_ref'] !== null && $f['archive_ref'] !== '') {
        $blob = vault_archive_get((string) $f['archive_ref']);
    } elseif ($f['inbox_name'] !== null && vault_valid_name((string) $f['inbox_name'])) {
        $blob = @file_get_contents(vault_inbox_dir() . '/' . $f['inbox_name']);
        if ($blob === false) {
            throw new RuntimeException('vault: inbox file missing');
        }
    } else {
        throw new RuntimeException('vault: file has no storage location');
    }
    if (!hash_equals((string) $f['enc_sha256'], hash('sha256', $blob))) {
        throw new RuntimeException('vault: integrity check failed');
    }
    return $blob;
}

/**
 * Move a file from the web server's inbox to the archive and delete the inbox copy (verified by reading it back).
 * Idempotent. @throws RuntimeException if the archive does not hold an identical copy afterwards (inbox copy stays)
 */
function vault_archive_file(int $fileId): void
{
    $f = identity_file_get($fileId);
    if ($f === null || $f['deleted_at'] !== null || ($f['archive_ref'] !== null && $f['archive_ref'] !== '' && ($f['inbox_name'] === null || $f['inbox_name'] === ''))) {
        return;
    }
    $inbox = vault_inbox_dir() . '/' . $f['inbox_name'];
    $blob = vault_valid_name((string) $f['inbox_name']) ? @file_get_contents($inbox) : false;
    if ($blob === false || !hash_equals((string) $f['enc_sha256'], hash('sha256', $blob))) {
        throw new RuntimeException('vault: inbox file missing or corrupt');
    }
    $ref = ($f['archive_ref'] !== null && $f['archive_ref'] !== '') ? (string) $f['archive_ref'] : vault_archive_put((string) $f['inbox_name'], $blob);
    if (!hash_equals((string) $f['enc_sha256'], hash('sha256', vault_archive_get($ref)))) {
        throw new RuntimeException('vault: archive verification failed');
    }
    db_exec('UPDATE identity_files SET archive_ref = ?, archived_at = ? WHERE id = ?', [$ref, now(), $fileId]);
    if (!vault_secure_unlink($inbox)) {
        throw new RuntimeException('vault: archived, but the inbox copy could not be deleted');
    }
    db_exec('UPDATE identity_files SET inbox_name = NULL WHERE id = ?', [$fileId]);
}

/**
 * Archive every file of a request and mark it archived.
 *
 * @return array{archived:int,failed:int,errors:string[]}
 */
function identity_archive_request(int $requestId): array
{
    $out = ['archived' => 0, 'failed' => 0, 'errors' => []];
    foreach (identity_files($requestId) as $f) {
        try {
            vault_archive_file((int) $f['id']);
            $out['archived']++;
        } catch (Throwable $e) {
            $out['failed']++;
            $out['errors'][] = $e->getMessage();
            error_log('identity_archive_request: ' . $e->getMessage());
        }
    }
    if ($out['failed'] === 0 && $out['archived'] > 0) {
        db_exec('UPDATE identity_requests SET status = \'archived\', updated_at = ? WHERE id = ? AND status IN (\'uploaded\',\'verified\')', [now(), $requestId]);
    }
    return $out;
}

/** Delete one file everywhere (inbox + archive) and mark it deleted. @throws RuntimeException if a copy could not be removed */
function vault_delete_file(int $fileId): void
{
    $f = identity_file_get($fileId);
    if ($f === null || $f['deleted_at'] !== null) {
        return;
    }
    if ($f['inbox_name'] !== null && vault_valid_name((string) $f['inbox_name'])) {
        if (!vault_secure_unlink(vault_inbox_dir() . '/' . $f['inbox_name'])) {
            throw new RuntimeException('vault: cannot delete the inbox file');
        }
    }
    if ($f['archive_ref'] !== null && $f['archive_ref'] !== '') {
        vault_archive_delete((string) $f['archive_ref']);
    }
    db_exec('UPDATE identity_files SET deleted_at = ?, inbox_name = NULL, archive_ref = NULL WHERE id = ?', [now(), $fileId]);
}

/**
 * Does submitting this revision need a (new) identity check? True for the first submission of a profile and when the
 * identity/address data differ from the live revision.
 *
 * @param array<string,mixed> $profile
 * @param array<string,mixed> $revision decoded revision (with 'private')
 */
function identity_required_for(array $profile, array $revision): bool
{
    $live = $profile['live_revision_id'] !== null ? revision_live((int) $profile['id']) : null;
    if ($live === null || empty($profile['identity_verified'])) {
        return true;
    }
    foreach (['legal_name', 'address_line1', 'postal_code', 'city', 'country'] as $f) {
        if (trim((string) ($live['private'][$f] ?? '')) !== trim((string) ($revision['private'][$f] ?? ''))) {
            return true;
        }
    }
    return false;
}

/** Has the customer uploaded at least one document image for the open request of this profile? */
function identity_upload_present(int $profileId): bool
{
    $req = db_one('SELECT id FROM identity_requests WHERE profile_id = ? AND status IN (\'uploaded\',\'verified\') ORDER BY id DESC LIMIT 1', [$profileId]);
    return $req !== null && count(identity_files((int) $req['id'])) > 0;
}
