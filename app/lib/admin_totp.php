<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Two-factor authentication of admin accounts: enabling/disabling, code check (with replay protection)
 * and one-time recovery codes. The secret lives in users.totp_secret (sensitive: never log/audit it);
 * recovery codes are stored hashed (JSON list in settings key "totp_recovery:<userId>").
 */

const ADMIN_RECOVERY_CODES = 8;

function admin_totp_active(array $user): bool
{
    return !empty($user['totp_enabled']) && (string) ($user['totp_secret'] ?? '') !== '';
}

/** Normalise a typed recovery code ("ABCDE-12345" → "abcde12345"). */
function admin_recovery_normalize(string $code): string
{
    return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $code));
}

function admin_recovery_hash(string $normalized): string
{
    return hash('sha256', 'tcd-recovery|' . $normalized);
}

/** @return string[] stored hashes */
function admin_recovery_hashes(int $userId): array
{
    $raw = setting_get('totp_recovery:' . $userId);
    $list = $raw === null ? [] : json_decode((string) $raw, true);
    return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
}

function admin_recovery_count(int $userId): int
{
    return count(admin_recovery_hashes($userId));
}

/**
 * Create a fresh set of recovery codes (replaces the old set). The plain codes are returned ONCE.
 *
 * @return string[] e.g. ["k3m9x-pq2ra", ...]
 */
function admin_recovery_generate(int $userId): array
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    $codes = [];
    $hashes = [];
    for ($i = 0; $i < ADMIN_RECOVERY_CODES; $i++) {
        $raw = '';
        for ($j = 0; $j < 10; $j++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
        $hashes[] = admin_recovery_hash($raw);
    }
    setting_set('totp_recovery:' . $userId, json_encode($hashes));
    return $codes;
}

/** Consume a recovery code (one-time). */
function admin_recovery_use(int $userId, string $input): bool
{
    $normalized = admin_recovery_normalize($input);
    if (strlen($normalized) !== 10) {
        return false;
    }
    $hash = admin_recovery_hash($normalized);
    return (bool) db_transaction(static function () use ($userId, $hash) {
        $hashes = admin_recovery_hashes($userId);
        $left = [];
        $matched = false;
        foreach ($hashes as $h) {
            if (!$matched && hash_equals($h, $hash)) {
                $matched = true;      // drop it
                continue;
            }
            $left[] = $h;
        }
        if ($matched) {
            setting_set('totp_recovery:' . $userId, json_encode($left));
        }
        return $matched;
    });
}

/**
 * Check a 6-digit authenticator code (±1 step, each time step usable once) or, if $allowRecovery,
 * a one-time recovery code.
 *
 * @param array<string,mixed> $user row of users
 * @return string '' = wrong, 'totp' or 'recovery' = accepted
 */
function admin_totp_check(array $user, string $input, bool $allowRecovery = true): string
{
    if (!admin_totp_active($user)) {
        return '';
    }
    $uid = (int) $user['id'];
    $digits = (string) preg_replace('/\s+/', '', $input);
    if (preg_match('/^\d{6}$/D', $digits) === 1) {
        $step = totp_verify((string) $user['totp_secret'], $digits);
        if ($step === null) {
            return '';
        }
        $ok = (bool) db_transaction(static function () use ($uid, $step) {
            $last = (int) setting_get('totp_last:' . $uid, '0');
            if ($step <= $last) {
                return false;          // replay of an already used code
            }
            setting_set('totp_last:' . $uid, (string) $step);
            return true;
        });
        return $ok ? 'totp' : '';
    }
    if ($allowRecovery && admin_recovery_use($uid, $input)) {
        return 'recovery';
    }
    return '';
}

/**
 * Turn 2FA on (after the user proved possession with a valid code) and create recovery codes.
 *
 * @param int $usedStep step counter of the confirming code (it must not be reusable at the next login)
 * @return string[] plain recovery codes (show once)
 */
function admin_totp_enable(int $userId, string $secret, int $usedStep): array
{
    return db_transaction(static function () use ($userId, $secret, $usedStep) {
        db_exec('UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?', [$secret, $userId]);
        setting_set('totp_last:' . $userId, (string) $usedStep);
        return admin_recovery_generate($userId);
    });
}

function admin_totp_disable(int $userId): void
{
    db_transaction(static function () use ($userId) {
        db_exec('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?', [$userId]);
        db_exec('DELETE FROM settings WHERE k IN (?, ?)', ['totp_recovery:' . $userId, 'totp_last:' . $userId]);
    });
}

/** Rate limiting for the second login step: per IP and per account. */
function admin_totp_rate_ok(int $userId): bool
{
    $limit = config('rate.totp');
    return rate_limit_hit('totp:ip:' . client_ip(), $limit) && rate_limit_hit('totp:user:' . $userId, $limit);
}
