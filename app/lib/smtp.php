<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Dependency-free SMTP client for system mails sent through the external mail provider (config 'mail.transport' = 'smtp').
 *
 *   smtp_send($from, [$rcpt, ...], $rawMessage)   deliver one complete RFC 5322 message (headers + CRLF CRLF + body);
 *                                                 throws RuntimeException (message is safe to log: never contains secrets)
 *   smtp_check()                                  connect, EHLO, STARTTLS, AUTH and QUIT - sends NO mail ("test connection")
 *
 * Settings (config('mail.smtp.*'), editable in the admin panel): host, port, encryption ('tls' = STARTTLS, usually 587;
 * 'ssl' = implicit TLS, usually 465; 'none' = plaintext, only for 127.0.0.1/localhost/::1), username, password, timeout.
 *
 * Security: TLS 1.2+ only, the server certificate is always verified (peer name = configured host, system CA store).
 * With 'tls' the connection is aborted when the server does not offer STARTTLS - there is never a plaintext fallback.
 * The password is only sent after TLS is up (or on a loopback 'none' connection) and is redacted from every error text.
 *
 * Test hook: the environment variable TCD_SMTP_CAFILE (only honoured when set and naming a readable file) replaces the
 * system CA store with that PEM file, so tests can verify a self-signed test certificate. Never needed in production.
 */

/** Effective SMTP settings: config('mail.smtp.*'), optionally overridden by $override (same keys). @return array<string,mixed> */
function smtp_settings(?array $override = null): array
{
    $cfg = config('mail.smtp', []);
    $cfg = is_array($cfg) ? $cfg : [];
    if ($override !== null) {
        $cfg = array_replace($cfg, $override);
    }
    $timeout = (int) ($cfg['timeout'] ?? 15);
    return [
        'host'       => trim((string) ($cfg['host'] ?? '')),
        'port'       => (int) ($cfg['port'] ?? 587),
        'encryption' => (string) ($cfg['encryption'] ?? 'tls'),
        'username'   => (string) ($cfg['username'] ?? ''),
        'password'   => (string) ($cfg['password'] ?? ''),
        'timeout'    => max(1, min(120, $timeout > 0 ? $timeout : 15)),
    ];
}

/** Loopback hosts that may be used with encryption 'none'. */
function smtp_is_loopback(string $host): bool
{
    return in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
}

/** Name we announce in EHLO: the site's host name, else this machine's FQDN, else an address literal. */
function smtp_helo_name(): string
{
    $re = '/^(?=.{1,253}$)[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$/D';
    foreach ([(string) parse_url((string) config('site_url', ''), PHP_URL_HOST), (string) gethostname()] as $name) {
        if (preg_match($re, $name) === 1 && preg_match('/^[0-9.]+$/D', $name) !== 1) {
            return $name;
        }
    }
    return '[127.0.0.1]';
}

/** Envelope address check: plain ASCII, no spaces/brackets/control characters (prevents SMTP command injection). */
function smtp_valid_address(string $addr): bool
{
    return strlen($addr) <= 254 && preg_match('/^[^\s<>()\[\],;:"\\\\\x00-\x1F\x7F-\xFF]+@[A-Za-z0-9.-]+$/D', $addr) === 1;
}

/**
 * Normalise line endings to CRLF and dot-stuff (RFC 5321 4.5.2): every line that starts with "." gets one more ".".
 * The result always ends with CRLF, ready for the terminating ".".
 */
function smtp_dot_stuff(string $raw): string
{
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    if ($raw === '' || substr($raw, -1) !== "\n") {
        $raw .= "\n";
    }
    $raw = (string) preg_replace('/^\./m', '..', $raw);
    return str_replace("\n", "\r\n", $raw);
}

/** One SMTP conversation. Internal - use smtp_send() / smtp_check(). */
final class TcdSmtpSession
{
    /** @var resource|null */
    private $fp = null;
    /** @var array<string,mixed> */
    private $s;
    /** @var array<int,string> values that must never appear in an error text */
    private $secrets = [];
    /** @var array<string,string> EHLO extensions (upper-case keyword => parameters) */
    private $ext = [];
    /** @var string */
    public $tlsInfo = '';

    /** @param array<string,mixed> $settings from smtp_settings() */
    public function __construct(array $settings)
    {
        $this->s = $settings;
        if ($settings['password'] !== '') {
            $this->secrets[] = (string) $settings['password'];
            $this->secrets[] = base64_encode((string) $settings['password']);
            $this->secrets[] = base64_encode("\0" . $settings['username'] . "\0" . $settings['password']);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /** Remove secrets and control characters from a text that may end up in a log or on screen. */
    public function redact(string $text): string
    {
        foreach ($this->secrets as $secret) {
            if ($secret !== '') {
                $text = str_replace($secret, '***', $text);
            }
        }
        $text = (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text);
        return strlen($text) > 300 ? substr($text, 0, 300) . '...' : $text;
    }

    /** Close the connection and throw (redacted message). Never returns. */
    private function fail(string $message): void
    {
        $this->close();
        throw new RuntimeException($this->redact($message));
    }

    /** Connect, greeting, EHLO, STARTTLS (for 'tls'), EHLO again, AUTH (when a username is set). */
    public function open(): void
    {
        $host = (string) $this->s['host'];
        $port = (int) $this->s['port'];
        $enc = (string) $this->s['encryption'];
        if ($host === '' || preg_match('/^[A-Za-z0-9.:\[\]-]{1,253}$/D', $host) !== 1) {
            $this->fail('SMTP host is not configured (admin panel -> Settings -> Mail).');
        }
        if ($port < 1 || $port > 65535) {
            $this->fail('SMTP port is invalid.');
        }
        if (!in_array($enc, ['tls', 'ssl', 'none'], true)) {
            $this->fail('SMTP encryption must be tls, ssl or none.');
        }
        if ($enc === 'none' && !smtp_is_loopback($host)) {
            $this->fail('SMTP without encryption is only allowed for a server on this machine (127.0.0.1/localhost). Use tls or ssl.');
        }
        if (!extension_loaded('openssl') && $enc !== 'none') {
            $this->fail('The PHP openssl extension is required for encrypted SMTP.');
        }

        $bare = trim($host, '[]');
        $ssl = [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'peer_name'         => $bare,
            'allow_self_signed' => false,
            'SNI_enabled'       => true,
            'disable_compression' => true,
            'crypto_method'     => smtp_crypto_method(),
        ];
        $cafile = getenv('TCD_SMTP_CAFILE');
        if ($cafile !== false && $cafile !== '' && is_file($cafile) && is_readable($cafile)) {
            $ssl['cafile'] = $cafile;   // test hook, see file comment
        }
        $ctx = stream_context_create(['ssl' => $ssl]);
        $target = 'tcp://' . (strpos($bare, ':') !== false ? '[' . $bare . ']' : $bare) . ':' . $port;
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($target, $errno, $errstr, (float) $this->s['timeout'], STREAM_CLIENT_CONNECT, $ctx);
        if (!is_resource($fp)) {
            $this->fail(sprintf('Cannot connect to SMTP server %s:%d (%s).', $host, $port, $errstr !== '' ? $errstr : 'error ' . $errno));
        }
        $this->fp = $fp;
        stream_set_timeout($fp, (int) $this->s['timeout']);

        if ($enc === 'ssl') {
            $this->startTls();
        }
        $this->expect($this->read(), [220], 'greeting');
        $this->ehlo();
        if ($enc === 'tls') {
            if (!isset($this->ext['STARTTLS'])) {
                $this->fail(sprintf('SMTP server %s:%d does not offer STARTTLS - refusing to continue without encryption (check port/encryption: 587 = tls, 465 = ssl).', $host, $port));
            }
            $this->cmd('STARTTLS', [220], 'STARTTLS');
            // Anything already buffered after the 220 was sent in plaintext and would be read as if it came through TLS
            // (STARTTLS response injection, cf. CVE-2011-0411): refuse instead.
            $meta = stream_get_meta_data($this->fp);
            if ((int) ($meta['unread_bytes'] ?? 0) > 0) {
                $this->fail(sprintf('SMTP server %s:%d sent unexpected data right after STARTTLS - refusing to continue (possible tampering on the network).', $host, $port));
            }
            $this->startTls();
            $this->ehlo();
        }
        if ($this->s['username'] !== '') {
            $this->auth();
        }
    }

    private function startTls(): void
    {
        error_clear_last();
        $ok = @stream_socket_enable_crypto($this->fp, true, smtp_crypto_method());
        if ($ok !== true) {
            $err = error_get_last();
            $msg = is_array($err) ? (string) $err['message'] : '';
            $msg = (string) preg_replace('/^stream_socket_enable_crypto\(\): /', '', $msg);
            while (function_exists('openssl_error_string') && ($e = openssl_error_string()) !== false) {
                $msg .= ' ' . $e;
            }
            $this->fail(sprintf('TLS handshake with %s failed (certificate not valid for this host name, or not trusted?): %s',
                (string) $this->s['host'], $msg !== '' ? trim($msg) : 'unknown error'));
        }
        $meta = stream_get_meta_data($this->fp);
        $this->tlsInfo = isset($meta['crypto']['protocol']) ? (string) $meta['crypto']['protocol'] : 'TLS';
    }

    private function ehlo(): void
    {
        [$code, $lines] = $this->send('EHLO ' . smtp_helo_name());
        if ($code !== 250) {
            $this->fail('SMTP server rejected EHLO: ' . $code . ' ' . implode(' ', $lines));
        }
        $this->ext = [];
        foreach (array_slice($lines, 1) as $line) {
            $parts = preg_split('/[\s=]+/', trim($line), 2) ?: [''];
            $key = strtoupper($parts[0]);
            $this->ext[$key] = trim(($this->ext[$key] ?? '') . ' ' . (isset($parts[1]) ? strtoupper($parts[1]) : ''));
        }
    }

    private function auth(): void
    {
        $mechs = preg_split('/\s+/', $this->ext['AUTH'] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $user = (string) $this->s['username'];
        $pass = (string) $this->s['password'];
        if (in_array('PLAIN', $mechs, true)) {
            [$code, $lines] = $this->send('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass));
        } elseif (in_array('LOGIN', $mechs, true)) {
            $this->cmd('AUTH LOGIN', [334], 'AUTH LOGIN');
            $this->cmd(base64_encode($user), [334], 'AUTH LOGIN (user name)');
            [$code, $lines] = $this->send(base64_encode($pass));
        } else {
            $this->fail('SMTP server offers no supported login method (AUTH PLAIN or LOGIN)'
                . ($mechs === [] ? ' - it may require TLS first, or the server does not need a login (leave the user name empty).' : ': ' . implode(' ', $mechs) . '.'));
            return;
        }
        if ($code !== 235) {
            $this->fail('SMTP login failed (wrong user name or password?): ' . $code . ' ' . implode(' ', $lines));
        }
    }

    /** MAIL FROM, RCPT TO, DATA. */
    public function deliver(string $from, array $rcpts, string $raw): void
    {
        $this->cmd('MAIL FROM:<' . $from . '>', [250], 'MAIL FROM');
        foreach ($rcpts as $rcpt) {
            $this->cmd('RCPT TO:<' . $rcpt . '>', [250, 251], 'RCPT TO');
        }
        $this->cmd('DATA', [354], 'DATA');
        $this->write(smtp_dot_stuff($raw) . ".\r\n");
        $this->expect($this->read(), [250], 'message');
    }

    /** Polite goodbye; errors are irrelevant at this point. */
    public function quit(): void
    {
        if ($this->fp !== null) {
            try {
                $this->write("QUIT\r\n");
                $this->read();
            } catch (RuntimeException $e) {
                // ignore
            }
        }
        $this->close();
    }

    public function close(): void
    {
        if (is_resource($this->fp)) {
            @fclose($this->fp);
        }
        $this->fp = null;
    }

    /** @param array<int,int> $ok */
    private function cmd(string $line, array $ok, string $what): void
    {
        $this->expect($this->send($line), $ok, $what);
    }

    /** @return array{0:int,1:array<int,string>} */
    private function send(string $line): array
    {
        if (preg_match('/[\r\n\x00]/', $line) === 1) {
            $this->fail('Invalid SMTP command.');
        }
        $this->write($line . "\r\n");
        return $this->read();
    }

    /**
     * @param array{0:int,1:array<int,string>} $reply
     * @param array<int,int> $ok
     */
    private function expect(array $reply, array $ok, string $what): void
    {
        if (!in_array($reply[0], $ok, true)) {
            $this->fail(sprintf('SMTP server rejected %s: %d %s', $what, $reply[0], implode(' ', $reply[1])));
        }
    }

    private function write(string $data): void
    {
        if (!is_resource($this->fp)) {
            $this->fail('SMTP connection is closed.');
        }
        $len = strlen($data);
        for ($done = 0; $done < $len; $done += $n) {
            $n = @fwrite($this->fp, substr($data, $done, 8192));
            if ($n === false || $n === 0) {
                $this->fail('SMTP connection lost while sending.');
            }
        }
    }

    /**
     * Read one (possibly multi-line) reply: "250-first", "250-second", "250 last". All lines must carry the same code.
     *
     * @return array{0:int,1:array<int,string>} code, text of each line
     */
    private function read(): array
    {
        if (!is_resource($this->fp)) {
            $this->fail('SMTP connection is closed.');
        }
        $deadline = microtime(true) + (int) $this->s['timeout'];
        $code = 0;
        $lines = [];
        for ($i = 0; $i < 200; $i++) {
            $left = $deadline - microtime(true);
            if ($left <= 0) {
                $this->fail('SMTP server did not answer in time.');
            }
            stream_set_timeout($this->fp, (int) floor($left), (int) (($left - floor($left)) * 1000000));
            $line = @fgets($this->fp, 4096);
            if ($line === false) {
                $meta = stream_get_meta_data($this->fp);
                $this->fail(!empty($meta['timed_out']) ? 'SMTP server did not answer in time.' : 'SMTP server closed the connection.');
            }
            $line = rtrim((string) $line, "\r\n");
            if (preg_match('/^([2-5][0-9]{2})(?:([ -])(.*))?$/sD', $line, $m) !== 1) {
                $this->fail('Unexpected SMTP reply: ' . $line);
            }
            $c = (int) $m[1];
            if ($code !== 0 && $c !== $code) {
                $this->fail('Inconsistent multi-line SMTP reply: ' . $line);
            }
            $code = $c;
            $lines[] = isset($m[3]) ? $m[3] : '';
            if (!isset($m[2]) || $m[2] === ' ') {
                return [$code, $lines];
            }
        }
        $this->fail('SMTP reply has too many lines.');
        return [0, []];
    }
}

/** TLS 1.2 or newer. */
function smtp_crypto_method(): int
{
    $m = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
        $m |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
    }
    return $m;
}

/**
 * Deliver one complete message (header lines, empty line, body) to $rcpts through the configured SMTP server.
 *
 * @param array<int,string> $rcpts
 * @param array<string,mixed>|null $settings override of config('mail.smtp.*') (tests / admin "test connection")
 * @throws RuntimeException with a message that is safe to log (no password, no credentials)
 */
function smtp_send(string $from, array $rcpts, string $rawMessage, ?array $settings = null): void
{
    if (!smtp_valid_address($from)) {
        throw new RuntimeException('SMTP: invalid sender address.');
    }
    if ($rcpts === []) {
        throw new RuntimeException('SMTP: no recipient.');
    }
    foreach ($rcpts as $rcpt) {
        if (!is_string($rcpt) || !smtp_valid_address($rcpt)) {
            throw new RuntimeException('SMTP: invalid recipient address.');
        }
    }
    $session = new TcdSmtpSession(smtp_settings($settings));
    $session->open();
    $session->deliver($from, array_values($rcpts), $rawMessage);
    $session->quit();
}

/**
 * Test the connection: connect, EHLO, STARTTLS/TLS, AUTH, QUIT. Sends no mail (no MAIL FROM, no DATA).
 *
 * @param array<string,mixed>|null $settings override of config('mail.smtp.*')
 * @return array{ok:bool,detail:string} detail is English and contains no secrets
 */
function smtp_check(?array $settings = null): array
{
    $s = smtp_settings($settings);
    $session = new TcdSmtpSession($s);
    try {
        $session->open();
    } catch (RuntimeException $e) {
        return ['ok' => false, 'detail' => $e->getMessage()];
    }
    $tls = $session->tlsInfo !== '' ? ($s['encryption'] === 'tls' ? 'STARTTLS, ' : 'implicit TLS, ') . $session->tlsInfo : 'no encryption (local server)';
    $session->quit();
    $detail = sprintf('Connected to %s:%d (%s)', $s['host'], $s['port'], $tls)
        . ($s['username'] !== '' ? ', signed in as ' . $session->redact((string) $s['username']) . '.' : ', without login.')
        . ' No e-mail was sent.';
    $from = (string) config('mail.from', '');
    if (strpos((string) $s['username'], '@') !== false && $from !== '' && strcasecmp($from, (string) $s['username']) !== 0) {
        $detail .= ' Note: many providers only accept mail whose sender address (' . $from . ') matches the login or is an alias of it.';
    }
    return ['ok' => true, 'detail' => $detail];
}
