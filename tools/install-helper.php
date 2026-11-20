<?php
declare(strict_types=1);

/**
 * Helper of installer.sh (CLI only). It reuses the application's own functions, so the installer and the admin panel always
 * behave identically. Not meant to be called by hand, but harmless: every command is idempotent or read-only, except
 * those that say otherwise below.
 *
 * Output: one result per line, "STATUS<TAB>label<TAB>detail" (STATUS = OK, WARN, FAIL, INFO). Secrets are NEVER passed
 * as arguments; commands read "key=value" lines from standard input instead.
 *
 *   check                 PHP version/extensions/mail (read-only, works without the app)
 *   status                state of an existing installation (read-only)
 *   init                  create data directories, database (migrations) and the data key
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
    echo $status, "\t", str_replace(["\t", "\r", "\n"], ' ', $label), "\t", str_replace(["\t", "\r", "\n"], ' ', $detail), "\n";
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
            ih_fail('Ungueltige Eingabezeile (erwartet key=value).', 2);
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
    foreach (['pdo_sqlite' => 'Datenbank (SQLite)', 'mbstring' => 'Zeichensaetze', 'openssl' => 'Verschluesselung (OpenSSL)', 'sodium' => 'Verschluesselung (libsodium)', 'session' => 'Sitzungen', 'json' => 'JSON', 'ctype' => 'ctype'] as $ext => $what) {
        $ok(extension_loaded($ext), 'PHP-Erweiterung ' . $ext, 'vorhanden', 'fehlt (' . $what . ')');
    }
    $gd = extension_loaded('gd');
    $jpeg = $gd && function_exists('imagecreatefromjpeg') && function_exists('imagejpeg') && function_exists('imagecreatefromstring');
    $ok($jpeg, 'PHP-Erweiterung gd (mit JPEG)', 'vorhanden', $gd ? 'GD ohne JPEG-Unterstuetzung - Ausweisfotos koennen nicht verarbeitet werden' : 'fehlt - ohne GD koennen keine Ausweisfotos verarbeitet werden');
    $ok(extension_loaded('curl'), 'PHP-Erweiterung curl', 'vorhanden', 'fehlt (nur noetig fuer ein S3-Archiv)', 'WARN');
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $ok(function_exists('mail') && !in_array('mail', $disabled, true), 'E-Mail-Versand mail()', 'verfuegbar', 'mail() ist nicht verfuegbar/deaktiviert - es koennen keine E-Mails versendet werden', 'WARN');
    $sendmail = trim((string) ini_get('sendmail_path'));
    if ($sendmail !== '') {
        $bin = preg_split('/\s+/', $sendmail)[0] ?? '';
        $ok($bin !== '' && is_executable($bin), 'sendmail_path', $sendmail, $sendmail . ' ist nicht ausfuehrbar (gilt fuer die Kommandozeile; der Webserver kann abweichen)', 'WARN');
    } else {
        ih_out('WARN', 'sendmail_path', 'leer (fuer die Kommandozeile; Webserver-PHP kann abweichen)');
    }
    ih_out('INFO', 'PHP-Binary', PHP_BINARY !== '' ? PHP_BINARY : 'unbekannt');
    exit(0);
}

require dirname(__DIR__) . '/app/bootstrap.php';

/** Atomically write data/config.local.php under the same lock the application uses. */
function ih_config_write(array $patch): void
{
    $dir = data_dir();
    $local = $dir . '/config.local.php';
    $lock = @fopen($dir . '/config.local.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        ih_fail('data/config.local.lock ist nicht beschreibbar (Rechte des Datenverzeichnisses?).');
    }
    try {
        $cfg = config_local_load();                       // fail-closed: an unreadable file throws instead of being overwritten
        foreach ($patch as $dotted => $value) {
            $node = &$cfg;
            $parts = explode('.', (string) $dotted);
            $last = array_pop($parts);
            foreach ($parts as $part) {
                if (!isset($node[$part]) || !is_array($node[$part])) {
                    $node[$part] = [];
                }
                $node = &$node[$part];
            }
            $node[$last] = $value;
            unset($node);
        }
        $code = "<?php\ndefined('TCD_APP') || exit;\nreturn " . var_export($cfg, true) . ";\n";
        $tmp = $local . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $code, LOCK_EX) === false || !@rename($tmp, $local)) {
            @unlink($tmp);
            ih_fail('data/config.local.php konnte nicht geschrieben werden.');
        }
        @chmod($local, 0640);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($local, true);
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Is this directory/file path (absolute, may not exist yet) inside the web root? */
function ih_inside_web_root(string $path): bool
{
    return admin_path_inside_web_root($path);
}

try {
    switch ($cmd) {
        /* ------------------------------------------------------------- status (read-only) */
        case 'status':
            $dir = data_dir();
            ih_out('INFO', 'data_dir', $dir);
            ih_out('INFO', 'data_dir_in_web_root', ih_inside_web_root($dir) ? 'yes' : 'no');
            $local = $dir . '/config.local.php';
            $cfg = is_file($local) ? config_local_load() : [];
            $dbName = (string) ($cfg['db_file'] ?? '');
            $dbExists = $dbName !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $dbName) === 1 && is_file($dir . '/' . $dbName);
            ih_out('INFO', 'installed', $dbExists ? 'yes' : 'no');
            ih_out('INFO', 'data_key', !empty($cfg['data_key']) ? 'yes' : 'no');
            if ($dbExists) {
                ih_out('INFO', 'admin', admin_exists() ? 'yes' : 'no');
                $pem = vault_public_key_pem();
                ih_out('INFO', 'vault_key', $pem === '' ? 'none' : (vault_validate_public_key($pem) === null ? 'ok' : 'invalid'));
                ih_out('INFO', 'vault_fingerprint', $pem === '' ? '' : vault_fingerprint($pem));
            } else {
                ih_out('INFO', 'admin', 'no');
                ih_out('INFO', 'vault_key', 'none');
                ih_out('INFO', 'vault_fingerprint', '');
            }
            ih_out('INFO', 'site_url', (string) config('site_url'));
            ih_out('INFO', 'vault_driver', (string) config('vault.driver', 'directory'));
            ih_out('INFO', 'vault_dir', (string) config('vault.driver', 'directory') === 's3' ? '' : admin_vault_archive_path());
            ih_out('INFO', 'vault_dir_configured', trim((string) config('vault.dir', '')) !== '' ? 'yes' : 'no');
            ih_out('INFO', 'mail_domain', (string) substr((string) strrchr((string) config('mail.from'), '@'), 1));
            ih_out('INFO', 'legal_dir', legal_dir());
            ih_out('INFO', 'sitemap_file', imprint_sitemap_path());
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
            ih_out('OK', 'init', basename(db_file()));
            break;

        /* ------------------------------------------------------------- config-set */
        case 'config-set':
            $kv = ih_read_kv();
            $email = static function (string $v): bool {
                return v_email($v);
            };
            $url = static function (string $v): bool {
                $p = parse_url($v);
                return is_array($p) && in_array($p['scheme'] ?? '', ['http', 'https'], true) && !empty($p['host']) && !isset($p['user']) && !isset($p['query']) && !isset($p['fragment']) && trim((string) ($p['path'] ?? ''), '/') === '' && preg_match('/^[\x21-\x7e]+$/', $v) === 1;
            };
            $token = static function (string $v): bool {
                return preg_match('/^[A-Za-z0-9._-]{1,128}$/', $v) === 1;
            };
            $bool = static function (string $v): bool {
                return $v === 'true' || $v === 'false';
            };
            $rules = [
                'site_url' => $url,
                'site_name' => static function (string $v): bool {
                    return $v !== '' && mb_strlen($v, 'UTF-8') <= 80 && preg_match('/[<>\x00-\x1f]/', $v) !== 1;
                },
                'mail.from' => $email, 'mail.contact' => $email, 'mail.info' => $email, 'mail.imprint' => $email,
                'mail.privacy' => $email, 'mail.admin_notify' => $email, 'mail.requests' => $email,
                'vault.driver' => static function (string $v): bool {
                    return $v === 'directory' || $v === 's3';
                },
                'vault.dir' => static function (string $v): bool {
                    return $v !== '' && $v[0] === '/' && strlen($v) <= 1024 && preg_match('/[\x00-\x1f]/', $v) !== 1 && !ih_inside_web_root($v);
                },
                'vault.s3.endpoint' => $url,
                'vault.s3.region' => $token,
                'vault.s3.bucket' => static function (string $v): bool {
                    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,254}$/', $v) === 1;
                },
                'vault.s3.access_key' => static function (string $v): bool {
                    return preg_match('/^[\x21-\x7e]{1,256}$/', $v) === 1;
                },
                'vault.s3.secret_key' => static function (string $v): bool {
                    return preg_match('/^[\x21-\x7e]{1,256}$/', $v) === 1;
                },
                'vault.s3.path_style' => $bool,
                'vault.s3.prefix' => static function (string $v): bool {
                    return $v === '' || preg_match('~^[A-Za-z0-9._/-]{1,128}$~', $v) === 1;
                },
                'trust_forwarded_proto' => $bool,
            ];
            $patch = [];
            foreach ($kv as $key => $value) {
                if (!isset($rules[$key])) {
                    ih_fail('Unbekannte oder nicht erlaubte Einstellung: ' . $key, 2);
                }
                if (!$rules[$key]($value)) {
                    ih_fail('Ungueltiger Wert fuer ' . $key . ($key === 'vault.dir' ? ' (muss ein absoluter Pfad AUSSERHALB des Webroots sein)' : ''), 2);
                }
                $isBool = in_array($key, ['vault.s3.path_style', 'trust_forwarded_proto'], true);
                $patch[$key] = $isBool ? $value === 'true' : ($key === 'site_url' || $key === 'vault.s3.endpoint' ? rtrim($value, '/') : $value);
            }
            if ($patch !== []) {
                ih_config_write($patch);
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
                $map = ['/at least (\d+) characters/' => 'Das Passwort muss mindestens $1 Zeichen lang sein.', '/longer than 72/' => 'Das Passwort darf höchstens 72 Bytes lang sein.', '/too easy/' => 'Dieses Passwort ist zu leicht zu erraten.'];
                foreach ($map as $re => $de) {
                    if (preg_match($re, $err, $m) === 1) {
                        $err = (string) preg_replace('/\$1/', $m[1] ?? '', $de);
                        break;
                    }
                }
                ih_out('FAIL', 'password', $err);
                exit(1);
            }
            break;

        /* ------------------------------------------------------------- admin-create */
        case 'admin-create':
            $kv = ih_read_kv();
            $email = mb_strtolower(trim((string) ($kv['email'] ?? '')), 'UTF-8');
            $name = trim((string) ($kv['name'] ?? ''));
            if (!v_email($email)) {
                ih_fail('Die E-Mail-Adresse ist ungueltig.');
            }
            if (!v_len($name, 2, 80) || !v_oneline($name)) {
                ih_fail('Der Name muss 2 bis 80 Zeichen lang sein (eine Zeile).');
            }
            if (admin_exists()) {
                ih_out('WARN', 'admin-create', 'Es existiert bereits ein Admin-Konto - nichts geaendert.');
                exit(3);
            }
            try {
                $uid = auth_create_admin($email, $name, (string) ($kv['password'] ?? ''));
            } catch (InvalidArgumentException $e) {
                ih_fail($e->getMessage());
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
            $pem = trim((string) stream_get_contents(STDIN));
            $err = vault_validate_public_key($pem);
            if ($err !== null) {
                ih_fail('Kein gueltiger oeffentlicher RSA-Schluessel (PEM "BEGIN PUBLIC KEY", mindestens 3072 Bit).' . ($pem !== '' && strpos($pem, 'PRIVATE KEY') !== false ? ' ACHTUNG: Das ist ein PRIVATER Schluessel - den darf man nie weitergeben.' : ''));
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
                    ih_out('OK', 'vault-key', 'unchanged ' . $fp);
                    break;
                }
                ih_out('FAIL', 'vault-key', 'Es ist bereits ein anderer Schluessel gespeichert (' . vault_fingerprint($existing) . '). Ein Austausch ist nur im Admin-Bereich moeglich (Passwort noetig; aeltere Fotos brauchen weiterhin den alten privaten Schluessel).');
                exit(3);
            }
            vault_set_public_key($pem);
            audit('vault.public_key_set', 'vault', 'public_key', ['fingerprint' => $fp, 'replaced' => false, 'previous_fingerprint' => '', 'via' => 'installer']);
            ih_out('OK', 'vault-key', 'set ' . $fp);
            break;

        /* ------------------------------------------------------------- vault-probe */
        case 'vault-probe':
            $driver = (string) config('vault.driver', 'directory') === 's3' ? 's3' : 'directory';
            if ($driver === 'directory') {
                $dir = admin_vault_archive_path();
                if (!is_dir($dir)) {
                    ih_out('FAIL', 'vault-probe', 'Das Archiv-Verzeichnis ' . $dir . ' existiert nicht (Speichermedium eingehaengt?).');
                    exit(1);
                }
                if (ih_inside_web_root($dir)) {
                    ih_out('FAIL', 'vault-probe', 'Das Archiv-Verzeichnis liegt im Webroot: ' . $dir);
                    exit(1);
                }
            } elseif (admin_vault_s3_missing() !== []) {
                ih_out('FAIL', 'vault-probe', 'S3-Einstellungen unvollstaendig: ' . implode(', ', admin_vault_s3_missing()));
                exit(1);
            }
            $r = admin_vault_probe();
            ih_out($r['ok'] ? 'OK' : 'FAIL', 'vault-probe', ($driver === 's3' ? 'S3: ' : 'Verzeichnis: ') . $r['detail']);
            exit($r['ok'] ? 0 : 1);

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
                ih_fail('Ungueltige Empfaengeradresse.', 2);
            }
            $sent = send_mail($to, 'Installer test', "This is a test message from the installer of " . (string) config('site_name') . ".\n\nIf you can read this, outgoing mail works. (Check the spam folder too.)");
            ih_out($sent ? 'OK' : 'FAIL', 'mail-test', $sent ? 'Von PHP angenommen (Zustellung bitte im Postfach pruefen)' : 'mail() hat die Nachricht abgelehnt - siehe Server-Log');
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
