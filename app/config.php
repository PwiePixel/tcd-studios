<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Default settings. Do NOT put secrets in here (the file is part of the repository).
 * Local overrides: data/config.local.php (same array shape, merged recursively; created
 * automatically on first run, never committed). Environment: TCD_SITE_URL, TCD_DATA_DIR.
 */
return [
    'site_url'  => 'https://tcd-studio.de',
    'site_name' => 'TCD-Studio/Agency',

    // Mailboxes (create them at the mail host).
    'mail' => [
        'from'         => 'no-reply@tcd-studio.de',   // sender of all system mails
        'contact'      => 'contact@tcd-studio.de',    // contact form, service requests
        'info'         => 'info@tcd-studio.de',       // general
        'imprint'      => 'imprint@tcd-studio.de',    // imprint reviews + contact shown in imprints
        'privacy'      => 'privacy-policy@tcd-studio.de',
        'admin_notify' => 'imprint@tcd-studio.de',    // "new submission" notifications for admins
        'requests'     => 'contact@tcd-studio.de',    // service / shop requests
    ],

    // Version of the Terms and Conditions (AGB). Bump it whenever /agb/ changes in a way customers must accept again;
    // customers are asked to accept the current version before they create or submit an imprint.
    'terms_version' => '2026-11-16',

    // Identity vault (ID photos): encrypted on upload with the PUBLIC key set in the admin panel (the private key never
    // touches a server), then moved to the archive; the web server copy is deleted.
    //   driver 'directory': ciphertext files in `dir` (set it to a path OUTSIDE the web root, ideally on a separate
    //                       volume/storage server; default: data/vault-archive)
    //   driver 's3'       : any S3-compatible object storage (AWS S3, Hetzner/Backblaze/Wasabi/MinIO ...). Put the keys in
    //                       data/config.local.php, never in this file.
    'vault' => [
        'driver' => 'directory',
        'dir'    => '',
        's3'     => ['endpoint' => '', 'region' => 'eu-central-1', 'bucket' => '', 'access_key' => '', 'secret_key' => '',
                     'path_style' => true, 'prefix' => 'tcd-id/'],
        'max_upload_bytes' => 8388608,   // 8 MB per image
        'require_https'    => true,      // refuse ID uploads over plain http
    ],
    // Set to true ONLY if the site runs behind a TLS-terminating proxy/CDN that sets X-Forwarded-Proto (otherwise the app would
    // consider the connection insecure and refuse ID uploads). Put it in data/config.local.php.
    'trust_forwarded_proto' => false,
    'termination_token_hours' => 24,     // validity of the confirmation link when a customer ends the service

    'password_min_length' => 10,
    'max_links'           => 12,
    'max_profiles_per_user' => 5,
    'admin_idle_seconds'  => 1800,    // admin session idle timeout (30 min)
    'portal_idle_seconds' => 28800,   // customer session idle timeout (8 h)

    // [max attempts, window in seconds]
    'rate' => [
        'login_ip'     => [30, 900],
        'login_email'  => [6, 900],       // per e-mail address AND ip
        'login_account' => [40, 900],     // per account over all IPs (distributed guessing)
        'register_email' => [3, 3600],
        'register_ip'  => [10, 3600],
        'reset_ip'     => [10, 3600],
        'reset_email'  => [3, 3600],      // per e-mail address AND ip
        'reset_account' => [20, 3600],    // per e-mail address over all IPs
        'pwchange'     => [6, 900],
        'request_ip'   => [5, 3600],
        'totp'         => [8, 900],
    ],

    // Management block printed in every imprint (editable in the admin panel: settings "management.<key>").
    'management' => [
        'name'           => 'TCD-Studio/Agency',
        'street'         => 'Bahnhofstraße 22',
        'city'           => '8054 Graz, Austria',
        'phone'          => '+43 699 81865094',
        'email'          => 'imprint@tcd-studio.de',
        'website'        => 'https://tcd-studio.de',
        'legal_form'     => 'Sole proprietorship (Einzelunternehmen)',
        'chamber'        => 'Member of the Austrian Economic Chamber (WKO)',
        'authority'      => 'Municipal District Office of Graz',
        'regulations_url' => 'https://www.ris.bka.gv.at',
        'notice'         => 'This address is strictly for official authorities and regulatory matters only. No packages or general mail will be accepted.',
    ],
];
