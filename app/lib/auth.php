<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Authentication: registration with e-mail confirmation, login with rate limiting, password reset.
 * Customers and admins are both rows of `users` (role). Admin login (incl. optional TOTP) lives in admin/*.php.
 */

/** Password policy; returns an error message or null if acceptable. */
function auth_password_error(string $password, string $email = ''): ?string
{
    $min = (int) config('password_min_length', 10);
    if (strlen($password) < $min || str_len($password) < $min) {
        return sprintf(__('The password must have at least %d characters.'), $min);
    }
    if (strlen($password) > 72) {
        return __('The password must not be longer than 72 bytes.');
    }
    $lower = mb_strtolower($password, 'UTF-8');
    $common = ['password123', '1234567890', 'qwertyuiop', 'password1234', 'iloveyou123', 'letmein1234', 'abcdefghij', 'tcd-studio', 'tcdstudio123'];
    if (in_array($lower, $common, true) || ($email !== '' && $lower === mb_strtolower($email, 'UTF-8'))) {
        return __('This password is too easy to guess.');
    }
    if (preg_match('/^(.)\1+$/u', $password) === 1) {
        return __('This password is too easy to guess.');
    }
    return null;
}

function auth_hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Create an e-mail token and return the plain token (only the SHA-256 hash is stored).
 *
 * @param string $type 'verify' or 'reset'
 */
function auth_create_token(int $userId, string $type, int $ttlSeconds): string
{
    $plain = random_token(32);
    db_exec('DELETE FROM tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
    db_exec(
        'INSERT INTO tokens (user_id, type, token_hash, expires_at, created_at) VALUES (?,?,?,?,?)',
        [$userId, $type, hash('sha256', $plain), now() + $ttlSeconds, now()]
    );
    return $plain;
}

/**
 * Consume a token. @return array|null the token row (with user_id) or null if unknown/expired/used
 */
function auth_consume_token(string $plain, string $type): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/D', $plain) !== 1) {
        return null;
    }
    return db_transaction(static function () use ($plain, $type) {
        $row = db_one('SELECT * FROM tokens WHERE token_hash = ? AND type = ?', [hash('sha256', $plain), $type]);
        if (!$row || $row['used_at'] !== null || (int) $row['expires_at'] < now()) {
            return null;
        }
        db_exec('UPDATE tokens SET used_at = ? WHERE id = ?', [now(), (int) $row['id']]);
        return $row;
    });
}

/**
 * Register a customer. Always behaves the same for existing and new addresses (no account enumeration).
 *
 * @return array{ok:bool,error:string}
 */
function auth_register(string $email, string $name, string $password): array
{
    $email = mb_strtolower(trim($email), 'UTF-8');
    if (!rate_limit_hit('register:ip:' . client_ip(), config('rate.register_ip'))) {
        return ['ok' => false, 'error' => __('Too many attempts. Please try again later.')];
    }
    if (!v_email($email)) {
        return ['ok' => false, 'error' => __('Please enter a valid e-mail address.')];
    }
    if (!rate_limit_hit('register:email:' . hash('sha256', $email), config('rate.register_email'))) {
        return ['ok' => true, 'error' => ''];      // silently stop mailing the same address again and again
    }
    if (!v_len($name, 2, 80) || !v_oneline($name)) {
        return ['ok' => false, 'error' => __('Please enter your name (2–80 characters).')];
    }
    $pwError = auth_password_error($password, $email);
    if ($pwError !== null) {
        return ['ok' => false, 'error' => $pwError];
    }
    $existing = user_get_by_email($email);
    if ($existing === null) {
        $uid = db_insert(
            'INSERT INTO users (email, name, password_hash, role, status, created_at) VALUES (?,?,?,?,?,?)',
            [$email, $name, auth_hash_password($password), 'customer', 'pending', now()]
        );
        $token = auth_create_token($uid, 'verify', 48 * 3600);
        send_mail($email, __('Confirm your e-mail address'), sprintf(
            "Hello %s,\n\nplease confirm your e-mail address to activate your account:\n\n%s\n\nThe link is valid for 48 hours. If you did not register, you can ignore this message.",
            $name,
            site_url('/portal/verify.php?token=' . $token)
        ));
        audit('user.register', 'user', $uid, [], $uid);
    } elseif ($existing['status'] === 'pending') {
        // The newest registration wins: whoever confirms the link owns the password they just chose.
        // (Otherwise someone could register a victim's address first and keep the password.)
        db_exec('UPDATE users SET password_hash = ?, name = ? WHERE id = ? AND status = \'pending\'',
            [auth_hash_password($password), $name, (int) $existing['id']]);
        $token = auth_create_token((int) $existing['id'], 'verify', 48 * 3600);
        send_mail($email, __('Confirm your e-mail address'), "Hello,\n\nplease confirm your e-mail address to activate your account:\n\n"
            . site_url('/portal/verify.php?token=' . $token) . "\n\nThe link is valid for 48 hours.");
    } else {
        send_mail($email, __('Someone tried to register with your address'), "Hello,\n\nsomeone tried to create an account with this e-mail address, but an account already exists.\nIf this was you, please sign in or use \"Forgot password\":\n\n"
            . site_url('/portal/login.php') . "\n\nIf it was not you, you can ignore this message.");
    }
    return ['ok' => true, 'error' => ''];
}

/** Confirm an e-mail address from the link token. */
function auth_verify_email(string $token): bool
{
    $row = auth_consume_token($token, 'verify');
    if ($row === null) {
        return false;
    }
    db_exec(
        'UPDATE users SET status = \'active\', email_verified_at = ? WHERE id = ? AND status = \'pending\'',
        [now(), (int) $row['user_id']]
    );
    audit('user.verify_email', 'user', (int) $row['user_id'], [], (int) $row['user_id']);
    return true;
}

/**
 * Check credentials. Does NOT start the session; call auth_login_complete() on success.
 *
 * @param string $role 'customer' or 'admin'
 * @return array{ok:bool,error:string,user:?array}
 */
function auth_login(string $email, string $password, string $role): array
{
    $email = mb_strtolower(trim($email), 'UTF-8');
    $generic = ['ok' => false, 'error' => __('E-mail or password is wrong.'), 'user' => null];
    $acct = hash('sha256', $role . $email);
    $emailIp = hash('sha256', $role . $email . '|' . client_ip());
    if (!rate_limit_hit('login:ip:' . client_ip(), config('rate.login_ip'))
        || !rate_limit_hit('login:ei:' . $emailIp, config('rate.login_email'))
        || !rate_limit_hit('login:acct:' . $acct, config('rate.login_account'))) {
        return ['ok' => false, 'error' => __('Too many attempts. Please try again in a few minutes.'), 'user' => null];
    }
    $user = $email !== '' && strlen($email) <= 254 ? user_get_by_email($email) : null;
    // Always run one password verification so timing does not reveal whether the account exists.
    // The dummy hash is created with the same algorithm/cost as real hashes (and renewed when PHP's default changes).
    $dummy = (string) setting_get('auth.dummy_hash', '');
    if ($dummy === '' || password_needs_rehash($dummy, PASSWORD_DEFAULT)) {
        $dummy = password_hash(random_token(12), PASSWORD_DEFAULT);
        setting_set('auth.dummy_hash', $dummy);
    }
    $valid = password_verify($password, $user ? (string) $user['password_hash'] : $dummy);
    if (!$user || !$valid || $user['role'] !== $role) {
        return $generic;
    }
    if ($user['status'] === 'pending') {
        return ['ok' => false, 'error' => __('Please confirm your e-mail address first (see the link we sent you).'), 'user' => null];
    }
    if ($user['status'] !== 'active') {
        return $generic;
    }
    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [auth_hash_password($password), (int) $user['id']]);
    }
    rate_limit_clear('login:ei:' . $emailIp);
    return ['ok' => true, 'error' => '', 'user' => $user];
}

/** Start the logged-in session (fixation-safe). */
function auth_login_complete(array $user): void
{
    session_regenerate_id(true);
    $keep = $_SESSION['last_seen'] ?? now();
    $fresh = user_get((int) $user['id']);
    $_SESSION = ['uid' => (int) $user['id'], 'role' => (string) $user['role'], 'login_at' => now(), 'last_seen' => $keep,
                 'epoch' => (int) ($fresh['session_epoch'] ?? 0)];
    db_exec('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), (int) $user['id']]);
    audit('user.login', 'user', (int) $user['id'], ['area' => current_area()], (int) $user['id']);
}

/** The logged-in user (fresh from the database, so disabling an account takes effect immediately) or null. */
function auth_current_user(): ?array
{
    if (empty($_SESSION['uid']) || session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }
    $user = user_get((int) $_SESSION['uid']);
    if (!$user || $user['status'] !== 'active' || $user['role'] !== ($_SESSION['role'] ?? '')
        || (int) $user['session_epoch'] !== (int) ($_SESSION['epoch'] ?? -1)) {
        $_SESSION = [];     // disabled, role changed, or the password was changed/reset since this login
        return null;
    }
    $area = current_area();
    if (($area === 'admin') !== ($user['role'] === 'admin')) {
        return null;     // wrong area for this role
    }
    return $user;
}

/**
 * Require a logged-in user of a role; redirects to the area's login page otherwise.
 *
 * @param string $role 'customer' or 'admin'
 */
function auth_require(string $role): array
{
    $user = auth_current_user();
    if ($user === null || $user['role'] !== $role) {
        redirect($role === 'admin' ? '/admin/login.php' : '/portal/login.php');
    }
    return $user;
}

function auth_logout(): void
{
    if (!empty($_SESSION['uid'])) {
        audit('user.logout', 'user', (int) $_SESSION['uid']);
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/** Invalidate every session of a user (called after a password change/reset). */
function auth_bump_session_epoch(int $userId): void
{
    db_exec('UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?', [$userId]);
}

/** Send a password reset link. Silent for unknown addresses (no enumeration). */
function auth_request_reset(string $email, string $role): array
{
    $email = mb_strtolower(trim($email), 'UTF-8');
    if (!rate_limit_hit('reset:ip:' . client_ip(), config('rate.reset_ip'))) {
        return ['ok' => false, 'error' => __('Too many attempts. Please try again later.')];
    }
    if (!v_email($email)) {
        return ['ok' => false, 'error' => __('Please enter a valid e-mail address.')];
    }
    if (rate_limit_hit('reset:account:' . hash('sha256', $email), config('rate.reset_account'))
        && rate_limit_hit('reset:ei:' . hash('sha256', $email . '|' . client_ip()), config('rate.reset_email'))) {
        $user = user_get_by_email($email);
        if ($user && $user['status'] === 'active' && $user['role'] === $role) {
            $token = auth_create_token((int) $user['id'], 'reset', 3600);
            $path = $role === 'admin' ? '/admin/reset.php?token=' : '/portal/reset.php?token=';
            send_mail($email, __('Reset your password'), "Hello,\n\nyou asked to reset your password. Use this link within one hour:\n\n"
                . site_url($path . $token) . "\n\nIf you did not ask for this, you can ignore this message.");
            audit('user.reset_requested', 'user', (int) $user['id'], [], (int) $user['id']);
        }
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * Set a new password from a reset token. @return string|null error message or null on success
 */
function auth_reset_password(string $token, string $newPassword, string $role): ?string
{
    $row = auth_consume_token($token, 'reset');
    if ($row === null) {
        return __('This link is invalid or has expired. Please request a new one.');
    }
    $user = user_get((int) $row['user_id']);
    if (!$user || $user['role'] !== $role || $user['status'] !== 'active') {
        return __('This link is invalid or has expired. Please request a new one.');
    }
    $err = auth_password_error($newPassword, (string) $user['email']);
    if ($err !== null) {
        // give the token back so the user can try again with a better password
        db_exec('UPDATE tokens SET used_at = NULL WHERE id = ?', [(int) $row['id']]);
        return $err;
    }
    db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [auth_hash_password($newPassword), (int) $user['id']]);
    auth_bump_session_epoch((int) $user['id']);
    audit('user.password_reset', 'user', (int) $user['id'], [], (int) $user['id']);
    return null;
}

/** Change the password of a logged-in user. @return string|null error or null on success */
function auth_change_password(int $userId, string $current, string $new): ?string
{
    $user = user_get($userId);
    if (!rate_limit_hit('pwchange:' . $userId, config('rate.pwchange'))) {
        return __('Too many attempts. Please try again in a few minutes.');
    }
    if (!$user || !password_verify($current, (string) $user['password_hash'])) {
        return __('The current password is wrong.');
    }
    $err = auth_password_error($new, (string) $user['email']);
    if ($err !== null) {
        return $err;
    }
    db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [auth_hash_password($new), $userId]);
    auth_bump_session_epoch($userId);
    if (!empty($_SESSION['uid']) && (int) $_SESSION['uid'] === $userId) {
        $fresh = user_get($userId);
        $_SESSION['epoch'] = (int) ($fresh['session_epoch'] ?? 0);      // this session stays valid, all others end
    }
    audit('user.password_changed', 'user', $userId, [], $userId);
    return null;
}

/** Create an admin account (CLI / first-run setup). @return int user id */
function auth_create_admin(string $email, string $name, string $password): int
{
    $email = mb_strtolower(trim($email), 'UTF-8');
    if (!v_email($email)) {
        throw new InvalidArgumentException('invalid e-mail');
    }
    $err = auth_password_error($password, $email);
    if ($err !== null) {
        throw new InvalidArgumentException($err);
    }
    if (user_get_by_email($email) !== null) {
        throw new InvalidArgumentException('e-mail already in use');
    }
    $uid = db_insert(
        'INSERT INTO users (email, name, password_hash, role, status, email_verified_at, created_at) VALUES (?,?,?,?,?,?,?)',
        [$email, $name, auth_hash_password($password), 'admin', 'active', now(), now()]
    );
    audit('admin.created', 'user', $uid, [], $uid);
    return $uid;
}
