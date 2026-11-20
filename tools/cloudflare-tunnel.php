<?php
declare(strict_types=1);

/**
 * Cloudflare Tunnel setup for installer.sh (CLI only, standalone: does not load the application).
 * The API token is read from standard input, never from the command line. installer.sh runs this as "nobody".
 *
 * stdin (key=value lines): token, domain (e.g. tcd-studio.de), hostnames (comma separated, e.g. tcd-studio.de,www.tcd-studio.de),
 *                          tunnel_name, service (e.g. http://127.0.0.1:80), replace_dns (yes|no)
 *
 *   php tools/cloudflare-tunnel.php inspect   token valid? zone, account, existing tunnel, existing DNS records of the hostnames
 *   php tools/cloudflare-tunnel.php apply     create/reuse the tunnel, set its routes (ingress), point DNS at it, print the run token
 *
 * Output lines: STATUS<TAB>label<TAB>detail (OK, WARN, FAIL, INFO); "apply" prints one line "TOKEN<TAB><tunnel run token>".
 * Required API token permissions: Account > Cloudflare Tunnel > Edit, Zone > DNS > Edit, Zone > Zone > Read.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// TCD_CF_API_BASE: only for automated tests against a local mock (http only for 127.0.0.1).
$cfBase = (string) getenv('TCD_CF_API_BASE');
define('CF_API', preg_match('~^http://127\.0\.0\.1:\d+$~', $cfBase) === 1 ? $cfBase : 'https://api.cloudflare.com/client/v4');

function cf_out(string $status, string $label, string $detail = ''): void
{
    echo $status, "\t", preg_replace('/[\x00-\x1f\x7f]/', ' ', $label), "\t", preg_replace('/[\x00-\x1f\x7f]/', ' ', $detail), "\n";
}

function cf_fail(string $detail): void
{
    cf_out('FAIL', 'cloudflare', $detail);
    exit(1);
}

/** @return array<string,string> */
function cf_read_input(): array
{
    $kv = [];
    foreach (preg_split('/\r?\n/', (string) stream_get_contents(STDIN)) as $line) {
        if ($line === '' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $kv[$k] = trim($v);
    }
    return $kv;
}

/**
 * One API call. @return array<string,mixed> decoded "result"
 * @throws RuntimeException with Cloudflare's error messages
 */
function cf_api(string $token, string $method, string $path, ?array $body = null)
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP extension curl is missing');
    }
    $ch = curl_init(CF_API . $path);
    $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => strncmp(CF_API, 'https://', 8) === 0 ? CURLPROTO_HTTPS : CURLPROTO_HTTP,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('Cloudflare is not reachable: ' . $err);
    }
    $data = json_decode((string) $raw, true);
    if (!is_array($data) || empty($data['success'])) {
        $msgs = [];
        foreach ((array) ($data['errors'] ?? []) as $e) {
            $msgs[] = (isset($e['code']) ? $e['code'] . ': ' : '') . (string) ($e['message'] ?? '');
        }
        throw new RuntimeException('Cloudflare API ' . $method . ' ' . preg_replace('~/[0-9a-f]{32}~', '/…', $path) . ' failed (HTTP ' . $code . ')' . ($msgs ? ': ' . implode('; ', $msgs) : ''));
    }
    return $data['result'] ?? [];
}

/** Zone of a host name: tries the name itself and its parent domains. @return array{id:string,name:string,account:string} */
function cf_zone(string $token, string $domain): array
{
    $labels = explode('.', $domain);
    while (count($labels) >= 2) {
        $name = implode('.', $labels);
        $res = cf_api($token, 'GET', '/zones?name=' . rawurlencode($name));
        if (is_array($res) && isset($res[0]['id'])) {
            return ['id' => (string) $res[0]['id'], 'name' => (string) $res[0]['name'], 'account' => (string) ($res[0]['account']['id'] ?? '')];
        }
        array_shift($labels);
    }
    throw new RuntimeException('The domain ' . $domain . ' is not in this Cloudflare account (or the token may not read it). Add the domain to Cloudflare first and give the token "Zone > Zone > Read".');
}

/** Existing tunnel by name or null. @return array<string,mixed>|null */
function cf_find_tunnel(string $token, string $account, string $name): ?array
{
    $res = cf_api($token, 'GET', '/accounts/' . rawurlencode($account) . '/cfd_tunnel?is_deleted=false&name=' . rawurlencode($name));
    foreach ((array) $res as $t) {
        if (is_array($t) && ($t['name'] ?? '') === $name && empty($t['deleted_at'])) {
            return $t;
        }
    }
    return null;
}

/** @return array<int,array<string,mixed>> */
function cf_dns_records(string $token, string $zone, string $fqdn): array
{
    $res = cf_api($token, 'GET', '/zones/' . rawurlencode($zone) . '/dns_records?per_page=100&name=' . rawurlencode($fqdn));
    $out = [];
    foreach ((array) $res as $r) {
        if (is_array($r) && strcasecmp((string) ($r['name'] ?? ''), $fqdn) === 0 && in_array($r['type'] ?? '', ['A', 'AAAA', 'CNAME'], true)) {
            $out[] = $r;
        }
    }
    return $out;
}

$cmd = $argv[1] ?? '';
$in = cf_read_input();
$token = (string) ($in['token'] ?? '');
$domain = strtolower((string) ($in['domain'] ?? ''));
$hosts = array_values(array_filter(array_map('trim', explode(',', strtolower((string) ($in['hostnames'] ?? $domain))))));
$tunnelName = (string) ($in['tunnel_name'] ?? 'tcd-studio');
$service = (string) ($in['service'] ?? 'http://127.0.0.1:80');

if (preg_match('/^[A-Za-z0-9_-]{20,200}$/', $token) !== 1) {
    cf_fail('This does not look like a Cloudflare API token.');
}
foreach (array_merge([$domain], $hosts) as $h) {
    if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $h) !== 1) {
        cf_fail('Invalid host name: ' . $h);
    }
}
if (preg_match('/^[A-Za-z0-9._-]{1,60}$/', $tunnelName) !== 1 || preg_match('~^http://(127\.0\.0\.1|localhost)(:\d{1,5})?$~', $service) !== 1) {
    cf_fail('Invalid tunnel name or service.');
}

try {
    $zone = cf_zone($token, $domain);
    if ($zone['account'] === '') {
        cf_fail('The account of the zone could not be determined.');
    }
    foreach ($hosts as $h) {
        if ($h !== $zone['name'] && substr($h, -strlen('.' . $zone['name'])) !== '.' . $zone['name']) {
            cf_fail('The host name ' . $h . ' is not part of the zone ' . $zone['name'] . '.');
        }
    }
    $tunnel = cf_find_tunnel($token, $zone['account'], $tunnelName);

    if ($cmd === 'inspect') {
        cf_out('OK', 'zone', $zone['name']);
        cf_out('INFO', 'tunnel', $tunnel !== null ? (string) $tunnel['id'] : '');
        foreach ($hosts as $h) {
            $recs = cf_dns_records($token, $zone['id'], $h);
            $desc = [];
            $ours = $tunnel !== null ? $tunnel['id'] . '.cfargotunnel.com' : '';
            $foreign = false;
            foreach ($recs as $r) {
                $desc[] = $r['type'] . ' ' . $r['content'];
                if (!($r['type'] === 'CNAME' && $ours !== '' && strcasecmp((string) $r['content'], $ours) === 0)) {
                    $foreign = true;
                }
            }
            cf_out('INFO', 'dns:' . $h, ($foreign ? 'foreign' : ($recs ? 'ours' : 'none')) . ($desc ? ' ' . implode(', ', $desc) : ''));
        }
        exit(0);
    }

    if ($cmd !== 'apply') {
        cf_fail('Unknown command.');
    }
    $acc = rawurlencode($zone['account']);
    if ($tunnel === null) {
        $tunnel = cf_api($token, 'POST', '/accounts/' . $acc . '/cfd_tunnel', ['name' => $tunnelName, 'config_src' => 'cloudflare']);
        cf_out('OK', 'tunnel', 'created ' . $tunnelName);
    } else {
        cf_out('OK', 'tunnel', 'reused ' . $tunnelName);
    }
    $tid = (string) ($tunnel['id'] ?? '');
    if (preg_match('/^[0-9a-f-]{36}$/', $tid) !== 1) {
        cf_fail('Unexpected tunnel id from Cloudflare.');
    }
    $ingress = [];
    foreach ($hosts as $h) {
        $ingress[] = ['hostname' => $h, 'service' => $service];
    }
    $ingress[] = ['service' => 'http_status:404'];
    cf_api($token, 'PUT', '/accounts/' . $acc . '/cfd_tunnel/' . $tid . '/configurations', ['config' => ['ingress' => $ingress]]);
    cf_out('OK', 'routes', implode(', ', $hosts) . ' -> ' . $service);

    $target = $tid . '.cfargotunnel.com';
    $replace = ($in['replace_dns'] ?? 'no') === 'yes';
    foreach ($hosts as $h) {
        $recs = cf_dns_records($token, $zone['id'], $h);
        $record = ['type' => 'CNAME', 'name' => $h, 'content' => $target, 'proxied' => true, 'ttl' => 1, 'comment' => 'tcd-studio tunnel (installer.sh)'];
        $mine = null;
        $others = [];
        foreach ($recs as $r) {
            if ($r['type'] === 'CNAME' && $mine === null) {
                $mine = $r;
            } else {
                $others[] = $r;
            }
        }
        $needsChange = $others !== [] || ($mine !== null && (strcasecmp((string) $mine['content'], $target) !== 0 || empty($mine['proxied'])));
        if ($needsChange && !$replace) {
            cf_out('WARN', 'dns:' . $h, 'existing records kept (not replaced): ' . implode(', ', array_map(static function ($r) {
                return $r['type'] . ' ' . $r['content'];
            }, $recs)));
            continue;
        }
        foreach ($others as $r) {
            cf_api($token, 'DELETE', '/zones/' . rawurlencode($zone['id']) . '/dns_records/' . rawurlencode((string) $r['id']));
        }
        if ($mine !== null) {
            if ($needsChange) {
                cf_api($token, 'PUT', '/zones/' . rawurlencode($zone['id']) . '/dns_records/' . rawurlencode((string) $mine['id']), $record);
            }
        } else {
            cf_api($token, 'POST', '/zones/' . rawurlencode($zone['id']) . '/dns_records', $record);
        }
        cf_out('OK', 'dns:' . $h, 'CNAME -> tunnel (proxied)');
    }
    $runToken = cf_api($token, 'GET', '/accounts/' . $acc . '/cfd_tunnel/' . $tid . '/token');
    if (!is_string($runToken) || preg_match('/^[A-Za-z0-9+\/=_-]{40,}$/', $runToken) !== 1) {
        cf_fail('Cloudflare did not return a tunnel token.');
    }
    echo "TOKEN\t", $runToken, "\n";
} catch (Throwable $e) {
    cf_fail($e->getMessage());
}
