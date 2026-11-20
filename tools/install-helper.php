<?php
declare(strict_types=1);

/**
 * Helper of installer.sh (CLI only). It reuses the application's own functions, so the installer and the admin panel always
 * behave identically. installer.sh runs it as the web server user (never as root when a web user is known).
 *
 * Output: one result per line, "STATUS<TAB>label<TAB>detail" (STATUS = OK, WARN, FAIL, INFO). Secrets are NEVER passed
 * as arguments; commands read "key=value" lines from standard input instead.
 *
 *   check                 PHP version/extensions/mail (read-only, works without the app)
 *   status                state of an existing installation (read-only: creates nothing)
 *   init                  create database (migrations), data key, session and inbox directories
 *   config-set            (stdin: key=value lines) merge whitelisted settings into data/config.local.php
 *   password-check        (stdin: email=, password=) is the password acceptable?
 *   admin-create          (stdin: email=, name=, password=) create the first admin account
 *   vault-key status|set|fingerprint     (set/fingerprint: PEM on stdin) identity-vault public key
 *   vault-probe           write/read/delete a probe object in the configured archive
 *   report                the same system checks as admin panel -> Settings
 *   mail-test <address>   send a test e-mail
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Print one result line. */
function ih_out(string $status, string $label, string $detail = ''): void
{
    // no control characters: values may come from files the web server can write and must not drive root's terminal
    echo $status, "\t", preg_replace('/[\x00-\x1f\x7f]/', ' ', $label), "\t", preg_replace('/[\x00-\x1f\x7f]/', ' ', $detail), "\n";
}

/** Fatal error of the helper: message on stdout in the common format, exit code != 0. */
function ih_fail(string $detail, int $code = 1): void
{
    ih_out('FAIL', 'error', $detail);
    exit($code);
}

/** Read "key=value" lines from stdin (first "=" splits; blank lines and "#" comments are skipped). @return array<string,string> */
function ih_read_kv(): array
{
    $raw = stream_get_contents(STDIN);
    $kv = [];
    foreach (preg_split('/\r?\n/', (string) $raw) as $line) {
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false || $pos === 0) {
            ih_fail('Ungültige Eingabezeile (erwartet key=value).', 2);
        }
        $kv[substr($line, 0, $pos)] = substr($line, $pos + 1);
    }
    return $kv;
}

$cmd = $argv[1] ?? '';

/* ------------------------------------------------------------------ check (no app needed) */
if ($cmd === 'check') {
    $ok = static function (bool $good, string $label, string $detailOk, string $detailBad, string $badLevel = 'FAIL'): void {
        ih_out($good ? 'OK' : $badLevel, $label, $good ? $detailOk : $detailBad);
    };
    $ok(version_compare(PHP_VERSION, '7.4.0', '>='), 'PHP-Version', PHP_VERSION, PHP_VERSION . ' ist zu alt (erforderlich: 7.4 oder neuer)');
    foreach (['pdo_sqlite' => 'Datenbank (SQLite)', 'mbstring' => 'Zeichensätze', 'openssl' => 'Verschlüsselung (OpenSSL)', 'sodium' => 'Verschlüsselung (libsodium)', 'session' => 'Sitzungen', 'json' => 'JSON', 'ctype' => 'ctype'] as $ext => $what) {
        $ok(extension_loaded($ext), 'PHP-Erweiterung ' . $ext, 'vorhanden', 'fehlt (' . $what . ')');
    }
    $gd = extension_loaded('gd');
    $jpeg = $gd && function_exists('imagecreatefromjpeg') && function_exists('imagejpeg') && function_exists('imagecreatefromstring');
    $ok($jpeg, 'PHP-Erweiterung gd (mit JPEG)', 'vorhanden', $gd ? 'GD ohne JPEG-Unterstützung – Ausweisfotos können nicht verarbeitet werden' : 'fehlt – ohne GD können keine Ausweisfotos verarbeitet werden');
    $ok(extension_loaded('curl'), 'PHP-Erweiterung curl', 'vorhanden', 'fehlt (nur nötig für ein S3-Archiv)', 'WARN');
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $ok(function_exists('mail') && !in_array('mail', $disabled, true), 'E-Mail-Versand mail()', 'verfügbar', 'mail() ist nicht verfügbar/deaktiviert – es können keine E-Mails versendet werden', 'WARN');
    $sendmail = trim((string) ini_get('sendmail_path'));
    if ($sendmail !== '') {
        $bin = preg_split('/\s+/', $sendmail)[0] ?? '';
        $ok($bin !== '' && is_executable($bin), 'sendmail_path', $sendmail, $sendmail . ' ist nicht ausführbar (gilt für die Kommandozeile; der Webserver kann abweichen)', 'WARN');
    } else {
        ih_out('WARN', 'sendmail_path', 'leer (gilt für die Kommandozeile; das PHP des Webservers kann abweichen)');
    }
    ih_out('INFO', 'PHP-Binary', PHP_BINARY !== '' ? PHP_BINARY : 'unbekannt');
    exit(0);
}

define('IH_ROOT', dirname(__DIR__));

/**
 * Where would the application look for its data - computed WITHOUT creating anything (unlike data_dir()).
 * @return array{dir:string,source:string,problem:string}
 */
function ih_locate_data_dir(): array
{
    $env = getenv('TCD_DATA_DIR');
    if ($env !== false && $env !== '') {
        return ['dir' => rtrim($env, '/'), 'source' => 'env', 'problem' => ''];
    }
    $pointer = IH_ROOT . '/app/data-dir.local';
    if (is_file($pointer)) {
        $raw = @file_get_contents($pointer, false, null, 0, 4096);
        $line = $raw === false ? '' : rtrim($raw, "\r\n");
        if ($line === '' || $line[0] !== '/' || preg_match('/[\x00-\x1f]/', $line) === 1) {
            return ['dir' => '', 'source' => 'pointer', 'problem' => 'pointer_invalid'];
        }
        return ['dir' => rtrim($line, '/'), 'source' => 'pointer', 'problem' => is_dir($line) ? '' : 'pointer_target_missing'];
    }
    $dir = IH_ROOT . '/data';
    return ['dir' => $dir, 'source' => 'default', 'problem' => is_file($dir . '/.tcd-moved') ? 'moved_marker_without_pointer' : ''];
}

if ($cmd === 'status') {
    $loc = ih_locate_data_dir();
    ih_out('INFO', 'data_dir', $loc['dir']);
    ih_out('INFO', 'data_dir_source', $loc['source']);
    ih_out('INFO', 'problem', $loc['problem']);
    $exists = $loc['dir'] !== '' && is_dir($loc['dir']);
    ih_out('INFO', 'data_dir_exists', $exists ? 'yes' : 'no');
    $local = $loc['dir'] . '/config.local.php';
    $hasConfig = $exists && is_file($local);
    ih_out('INFO', 'has_config', $hasConfig ? 'yes' : 'no');
    if ($loc['problem'] !== '' || !$exists || !$hasConfig) {
        ih_out('INFO', 'installed', 'no');
        exit(0);
    }
}

require IH_ROOT . '/app/bootstrap.php';

/** German text for the password rule messages of the application. */
function ih_password_de(string $err): string
{
    $map = ['/at least (\d+) characters/' => 'Das Passwort muss mindestens %s Zeichen lang sein.', '/longer than 72/' => 'Das Passwort darf höchstens 72 Bytes lang sein.', '/too easy/' => 'Dieses Passwort ist zu leicht zu erraten.'];
    foreach ($map as $re => $de) {
        if (preg_match($re, $err, $m) === 1) {
            return sprintf($de, $m[1] ?? '');
        }
    }
    return $err;
}

/** Is this path (absolute, may not exist yet) inside the web root? */
function ih_inside_web_root(string $path): bool
{
    return admin_path_inside_web_root($path);
}

/** Is $path the web root or one of its ancestors? */
function ih_is_ancestor_of_web_root(string $path): bool
{
    $root = realpath(TCD_ROOT);
    $p = realpath($path);
    if ($root === false || $p === false) {
        return false;
    }
    return $p === '/' || $p === $root || strpos($root . '/', rtrim($p, '/') . '/') === 0;
}

/** PEM read from stdin (UTF-8 BOM and surrounding whitespace removed). */
function ih_read_pem(): string
{
    $pem = (string) stream_get_contents(STDIN);
    if (strncmp($pem, "\xEF\xBB\xBF", 3) === 0) {
        $pem = substr($pem, 3);
    }
    return trim($pem);
}

try {
    switch ($cmd) {
        /* ------------------------------------------------------------- status (read-only) */
        case 'status':
            $dir = data_dir();
            ih_out('INFO', 'data_dir_in_web_root', ih_inside_web_root($dir) ? 'yes' : 'no');
            $local = $dir . '/config.local.php';
            $cfg = is_file($local) ? config_local_load() : [];
            $dbName = (string) ($cfg['db_file'] ?? '');
            $dbExists = $dbName !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $dbName) === 1 && is_file($dir . '/' . $dbName);
            ih_out('INFO', 'installed', $dbExists ? 'yes' : 'no');
            ih_out('INFO', 'data_key', !empty($cfg['data_key']) ? 'yes' : 'no');
            $admin = 'no';
            $vkey = 'none';
            $vfp = '';
            $archived = 0;
            if ($dbExists) {
                $admin = admin_exists() ? 'yes' : 'no';
                $pem = vault_public_key_pem();
                $vkey = $pem === '' ? 'none' : (vault_validate_public_key($pem) === null ? 'ok' : 'invalid');
                $vfp = $pem === '' ? '' : vault_fingerprint($pem);
                $archived = (int) (db_one('SELECT COUNT(*) AS n FROM identity_files WHERE deleted_at IS NULL AND archive_ref IS NOT NULL AND archive_ref != \'\'')['n'] ?? 0);
            }
            ih_out('INFO', 'admin', $admin);
            ih_out('INFO', 'vault_key', $vkey);
            ih_out('INFO', 'vault_fingerprint', $vfp);
            ih_out('INFO', 'archive_files', (string) $archived);
            ih_out('INFO', 'site_url', (string) config('site_url'));
            ih_out('INFO', 'trust_forwarded_proto', config('trust_forwarded_proto', false) ? 'true' : 'false');
            $driver = (string) config('vault.driver', 'directory') === 's3' ? 's3' : 'directory';
            ih_out('INFO', 'vault_driver', $driver);
            ih_out('INFO', 'vault_dir', $driver === 's3' ? '' : admin_vault_archive_path());
            ih_out('INFO', 'vault_dir_configured', trim((string) config('vault.dir', '')) !== '' ? 'yes' : 'no');
            $s3 = (array) config('vault.s3', []);
            ih_out('INFO', 's3_endpoint', (string) ($s3['endpoint'] ?? ''));
            ih_out('INFO', 's3_bucket', (string) ($s3['bucket'] ?? ''));
            ih_out('INFO', 's3_region', (string) ($s3['region'] ?? ''));
            ih_out('INFO', 's3_prefix', (string) ($s3['prefix'] ?? ''));
            ih_out('INFO', 's3_path_style', !empty($s3['path_style']) ? 'true' : 'false');
            ih_out('INFO', 's3_keys', !empty($s3['access_key']) && !empty($s3['secret_key']) ? 'yes' : 'no');
            ih_out('INFO', 'mail_domain', (string) substr((string) strrchr((string) config('mail.from'), '@'), 1));
            break;

        /* ------------------------------------------------------------- init */
        case 'init':
            $dir = data_dir();
            if (!is_dir($dir) || !is_writable($dir)) {
                ih_fail('Das Datenverzeichnis ' . $dir . ' existiert nicht oder ist nicht beschreibbar.');
            }
            db();                                          // creates the database file (random name) and runs the migrations
            crypt_key();                                   // creates the data key once (never replaces an existing one)
            session_dir();                                 // creates data/sessions
            vault_inbox_dir();                             // creates data/vault-inbox
            ih_out('OK', 'Datenbank', basename(db_file()));
            break;

        /* ------------------------------------------------------------- config-set */
        case 'config-set':
            // Same whitelist and validation as the admin panel (app/lib/config_settings.php).
            $kv = ih_read_kv();
            $patch = [];
            foreach ($kv as $key => $value) {
                $err = config_setting_error($key, $value);
                if ($err !== null) {
                    ih_fail('Ungültiger Wert für ' . $key . ': ' . $err, 2);
                }
                $patch[$key] = config_setting_cast($key, $value);
            }
            if ($patch !== []) {
                config_local_set($patch);
            }
            ih_out('OK', 'config-set', implode(',', array_keys($patch)));
            break;

        /* ------------------------------------------------------------- password-check */
        case 'password-check':
            $kv = ih_read_kv();
            $err = auth_password_error((string) ($kv['password'] ?? ''), (string) ($kv['email'] ?? ''));
            if ($err === null) {
                ih_out('OK', 'password', '');
            } else {
                ih_out('FAIL', 'password', ih_password_de($err));
                exit(1);
            }
            break;

        /* ------------------------------------------------------------- admin-create */
        case 'admin-create':
            $kv = ih_read_kv();
            $email = mb_strtolower(trim((string) ($kv['email'] ?? '')), 'UTF-8');
            $name = trim((string) ($kv['name'] ?? ''));
            if (!v_email($email)) {
                ih_fail('Die E-Mail-Adresse ist ungültig.');
            }
            if (!v_len($name, 2, 80) || !v_oneline($name)) {
                ih_fail('Der Name muss 2 bis 80 Zeichen lang sein (eine Zeile).');
            }
            try {
                $uid = db_transaction(static function () use ($email, $name, $kv) {
                    if (admin_exists()) {
                        return 0;                          // checked inside the write lock: parallel runs cannot create two admins
                    }
                    return auth_create_admin($email, $name, (string) ($kv['password'] ?? ''));
                });
            } catch (InvalidArgumentException $e) {
                ih_fail(ih_password_de($e->getMessage()));
            }
            if ($uid === 0) {
                ih_out('WARN', 'admin-create', 'Es existiert bereits ein Admin-Konto – nichts geändert.');
                exit(3);
            }
            @unlink(admin_setup_token_file());             // the web setup page is not needed any more
            ih_out('OK', 'admin-create', (string) $uid);
            break;

        /* ------------------------------------------------------------- vault-key */
        case 'vault-key':
            $sub = $argv[2] ?? '';
            if ($sub === 'status') {
                $pem = vault_public_key_pem();
                if ($pem === '') {
                    ih_out('INFO', 'vault-key', 'none');
                } elseif (vault_validate_public_key($pem) !== null) {
                    ih_out('WARN', 'vault-key', 'invalid');
                } else {
                    ih_out('OK', 'vault-key', vault_fingerprint($pem));
                }
                break;
            }
            $pem = ih_read_pem();
            $err = vault_validate_public_key($pem);
            if ($err !== null) {
                $why = 'Kein gültiger öffentlicher RSA-Schlüssel (erwartet: PEM mit "-----BEGIN PUBLIC KEY-----", mindestens 3072 Bit).';
                if (strpos($pem, 'PRIVATE KEY') !== false) {
                    $why .= ' ACHTUNG: Das ist ein PRIVATER Schlüssel – den nie auf den Server legen oder weitergeben!';
                } elseif (strpos($pem, 'BEGIN RSA PUBLIC KEY') !== false) {
                    $why .= ' Die Datei hat das ältere PKCS#1-Format; umwandeln mit: openssl rsa -RSAPublicKey_in -in DATEI -pubout -out id-vault-public-key.pem';
                } elseif (strpos($pem, 'BEGIN PUBLIC KEY') !== false) {
                    $why .= ' (' . $err . ')';
                }
                ih_fail($why);
            }
            $fp = vault_fingerprint($pem);
            if ($sub === 'fingerprint') {
                ih_out('OK', 'fingerprint', $fp);
                break;
            }
            if ($sub !== 'set') {
                ih_fail('Unbekannter Unterbefehl.', 2);
            }
            $existing = vault_public_key_pem();
            if ($existing !== '') {
                if (admin_vault_same_key($existing, $pem)) {
                    ih_out('OK', 'Tresor-Schlüssel', 'unverändert (' . $fp . ')');
                    break;
                }
                ih_out('FAIL', 'Tresor-Schlüssel', 'Es ist bereits ein anderer Schlüssel gespeichert (' . vault_fingerprint($existing) . '). Ein Austausch ist nur im Admin-Bereich möglich (Passwort nötig; ältere Fotos brauchen weiterhin den alten privaten Schlüssel).');
                exit(3);
            }
            vault_set_public_key($pem);
            audit('vault.public_key_set', 'vault', 'public_key', ['fingerprint' => $fp, 'replaced' => false, 'previous_fingerprint' => '', 'via' => 'installer']);
            ih_out('OK', 'Tresor-Schlüssel', 'gespeichert, Fingerabdruck ' . $fp);
            break;

        /* ------------------------------------------------------------- vault-probe */
        case 'vault-probe':
            $driver = (string) config('vault.driver', 'directory') === 's3' ? 's3' : 'directory';
            if ($driver === 'directory') {
                $dir = admin_vault_archive_path();
                if (!is_dir($dir)) {
                    ih_out('FAIL', 'Archiv-Test', 'Das Archiv-Verzeichnis ' . $dir . ' existiert nicht (Speichermedium eingehängt?).');
                    exit(1);
                }
                if (ih_inside_web_root($dir)) {
                    ih_out('FAIL', 'Archiv-Test', 'Das Archiv-Verzeichnis liegt im Webroot: ' . $dir);
                    exit(1);
                }
            } elseif (admin_vault_s3_missing() !== []) {
                ih_out('FAIL', 'Archiv-Test', 'S3-Einstellungen unvollständig: ' . implode(', ', admin_vault_s3_missing()));
                exit(1);
            }
            $r = admin_vault_probe();
            $detail = $r['detail'];
            if (!$r['ok'] && $driver === 's3' && preg_match('/\b(403|401)\b/', $detail) === 1) {
                $detail .= ' – Zugangsdaten, Region oder Bucket-Rechte prüfen (Installer erneut starten und die S3-Angaben neu eingeben).';
            } elseif (!$r['ok'] && $driver === 's3' && preg_match('/\b404\b/', $detail) === 1) {
                $detail .= ' – gibt es den Bucket? Stimmt die Path-Style-Einstellung?';
            }
            ih_out($r['ok'] ? 'OK' : 'FAIL', 'Archiv-Test', ($driver === 's3' ? 'S3: ' : 'Verzeichnis: ') . $detail);
            exit($r['ok'] ? 0 : 1);

        /* ------------------------------------------------------------- setup-token */
        case 'setup-token':
            if (admin_exists()) {
                ih_out('INFO', 'admin', 'yes');
                break;
            }
            $t = admin_setup_token();
            if ($t === '') {
                ih_fail('Der Setup-Token konnte nicht angelegt werden (Rechte des Datenverzeichnisses?).');
            }
            ih_out('INFO', 'admin', 'no');
            ih_out('INFO', 'setup_token', $t);
            break;

        /* ------------------------------------------------------------- report */
        case 'report':
            $worst = 0;
            foreach (admin_system_checks() as $c) {
                if ($c['label'] === __('HTTPS')) {
                    continue;                              // meaningless on the command line
                }
                $st = $c['status'] === 'ok' ? 'OK' : ($c['status'] === 'fail' ? 'FAIL' : 'WARN');
                $worst = max($worst, $st === 'FAIL' ? 2 : ($st === 'WARN' ? 1 : 0));
                ih_out($st, $c['label'], $c['detail']);
            }
            exit($worst === 2 ? 1 : 0);

        /* ------------------------------------------------------------- mail-test */
        case 'mail-test':
            $to = (string) ($argv[2] ?? '');
            if (!v_email($to)) {
                ih_fail('Ungültige Empfängeradresse.', 2);
            }
            $sent = send_mail($to, 'Installer test', "This is a test message from the installer of " . (string) config('site_name') . ".\n\nIf you can read this, outgoing mail works. (Check the spam folder too.)");
            ih_out($sent ? 'OK' : 'FAIL', 'Test-E-Mail', $sent ? 'von PHP angenommen – Zustellung bitte im Postfach prüfen (auch Spam)' : 'mail() hat die Nachricht abgelehnt – siehe Server-Log');
            exit($sent ? 0 : 1);

        default:
            fwrite(STDERR, "Usage: php tools/install-helper.php <check|status|init|config-set|password-check|admin-create|vault-key|vault-probe|report|mail-test>\n");
            exit(2);
    }
} catch (Throwable $e) {
    error_log('install-helper ' . $cmd . ': ' . $e->getMessage());
    ih_out('FAIL', 'error', get_class($e) . ': ' . $e->getMessage());
    exit(1);
}
