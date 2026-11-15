<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Encryption at rest for personal data in the database (field level, libsodium secretbox = XSalsa20-Poly1305).
 *
 * The key is created once and stored in data/config.local.php ('data_key', base64) or in the environment variable
 * TCD_DATA_KEY. IMPORTANT: back up data/config.local.php together with the database - without the key the private
 * data cannot be read any more. This protects against leaked database files/backups; it does not protect against an
 * attacker who controls the running server (the server must be able to read the data for the admin review).
 * ID photos are protected differently and much stronger, see vault.php.
 */

const CRYPT_PREFIX = 'enc1:';

/** The 32-byte data key (created on first use). */
function crypt_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $env = getenv('TCD_DATA_KEY');
    $b64 = ($env !== false && $env !== '') ? $env : (string) config_local_get_or_create('data_key', static function () {
        return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    });
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('Invalid data_key (must be 32 random bytes, base64 encoded)');
    }
    $key = $raw;
    return $key;
}

/** Encrypt a string; the result is safe to store in a TEXT column ("enc1:" + base64(nonce + ciphertext)). */
function crypt_encrypt(string $plain): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return CRYPT_PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, crypt_key()));
}

/** Is the value an encrypted blob produced by crypt_encrypt()? */
function crypt_is_encrypted(?string $value): bool
{
    return $value !== null && strncmp($value, CRYPT_PREFIX, strlen(CRYPT_PREFIX)) === 0;
}

/** Decrypt; @throws RuntimeException when the key is wrong or the data was tampered with. */
function crypt_decrypt(string $value): string
{
    $raw = base64_decode(substr($value, strlen(CRYPT_PREFIX)), true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
        throw new RuntimeException('Encrypted value is malformed');
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, crypt_key());
    if ($plain === false) {
        throw new RuntimeException('Cannot decrypt stored data (wrong or missing data_key in data/config.local.php?)');
    }
    return $plain;
}

/** Serialise + encrypt the private (identity/address) data of a revision for storage. @param array<string,mixed> $data */
function private_store(array $data): string
{
    return crypt_encrypt(json_str($data));
}

/**
 * Read private data from a stored value (encrypted, or legacy plain JSON).
 *
 * @return array<string,mixed>
 * @throws RuntimeException if the value is encrypted and cannot be decrypted (never silently returns an empty set)
 */
function private_load(?string $stored): array
{
    if ($stored === null || $stored === '') {
        return [];
    }
    return json_arr(crypt_is_encrypted($stored) ? crypt_decrypt($stored) : $stored);
}
