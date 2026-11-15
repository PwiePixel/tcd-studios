<?php
declare(strict_types=1);

/**
 * Create an admin account from the command line (alternative to /admin/setup.php, or to add the first admin
 * when the web setup is not wanted).
 *
 *   php tools/create-admin.php admin@example.com "Jane Doe"
 *
 * The password is read without echo from the terminal, or from the environment variable TCD_ADMIN_PASSWORD
 * (for scripts). The same environment variables as the web application apply (TCD_DATA_DIR, ...).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$email = $argv[1] ?? '';
$name = $argv[2] ?? '';
if ($email === '' || $name === '' || isset($argv[3])) {
    fwrite(STDERR, "Usage: php tools/create-admin.php <email> \"<Name>\"\n       (password from the prompt or from TCD_ADMIN_PASSWORD)\n");
    exit(2);
}

$password = getenv('TCD_ADMIN_PASSWORD');
if ($password === false || $password === '') {
    $tty = function_exists('posix_isatty') && @posix_isatty(STDIN);
    $read = static function (string $prompt) use ($tty): string {
        fwrite(STDERR, $prompt);
        if ($tty) {
            @shell_exec('stty -echo');
        }
        $line = fgets(STDIN);
        if ($tty) {
            @shell_exec('stty echo');
            fwrite(STDERR, "\n");
        }
        return $line === false ? '' : rtrim($line, "\r\n");
    };
    $password = $read('Password: ');
    if ($tty) {
        $again = $read('Repeat password: ');
        if ($password !== $again) {
            fwrite(STDERR, "The passwords are not identical.\n");
            exit(1);
        }
    }
}

try {
    $uid = auth_create_admin($email, $name, (string) $password);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: the account could not be created (' . get_class($e) . ").\n");
    error_log('create-admin failed: ' . $e->getMessage());
    exit(1);
}
// The setup token is no longer needed once an admin exists.
@unlink(admin_setup_token_file());
echo 'Admin account created (id ' . $uid . "). You can sign in at /admin/login.php\n";
