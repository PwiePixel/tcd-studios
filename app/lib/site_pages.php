<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Controllers of the public pages (no sessions, no cookies). The entry scripts services/index.php,
 * imprint-protection/index.php, shops/index.php and coming-soon/index.php only load the bootstrap and call these.
 */

const SITE_CSS = ['/css/site.css'];

/* ------------------------------------------------------------------ imprint protection */

function site_page_imprint_protection(): void
{
    site_render([
        'title'       => 'Imprint Protection - TCD-Studio/Agency',
        'description' => 'A legally sound imprint for creators on tcd-studio.de, with the management address of TCD-Studio/Agency instead of your private address.',
        'canonical'   => '/imprint-protection/',
        'css'         => SITE_CSS,
        'content'     => render_partial('site/imprint-protection', ['imprintMail' => (string) config('mail.imprint')]),
    ]);
}

/* ------------------------------------------------------------------ services */

/** Badge label of an availability value. */
function site_availability_label(string $availability): string
{
    $labels = ['active' => 'Available', 'on_request' => 'On request', 'coming_soon' => 'Coming soon'];
    return __($labels[$availability] ?? 'Coming soon');
}

/**
 * Sensible next step of a service card.
 *
 * @param array<string,mixed> $service
 * @return array{0:string,1:string} [href, button label]
 */
function site_service_link(array $service): array
{
    $slug = (string) ($service['slug'] ?? '');
    $availability = (string) ($service['availability'] ?? '');
    if ($slug === 'imprint-protection' && $availability !== 'coming_soon') {
        return ['/imprint-protection/', __('Learn more')];
    }
    if ($slug === 'shops') {
        return $availability === 'coming_soon' ? ['/coming-soon/?topic=shops', __('Learn more')] : ['/shops/', __('Learn more and request')];
    }
    if ($availability === 'coming_soon') {
        return ['/coming-soon/?topic=' . rawurlencode($slug), __('Learn more')];
    }
    return ['/#ed-713620122', __('Contact us')];
}

function site_page_services(): void
{
    try {
        $services = services_all();
    } catch (Throwable $e) {
        error_log('services page: ' . $e->getMessage());
        $services = null;
    }
    $cards = [];
    foreach ((array) $services as $s) {
        [$href, $label] = site_service_link($s);
        $cards[] = [
            'name' => (string) $s['name'], 'summary' => (string) $s['summary'], 'details' => (string) $s['details'],
            'availability' => (string) $s['availability'], 'badge' => site_availability_label((string) $s['availability']),
            'href' => $href, 'label' => $label,
        ];
    }
    site_render([
        'title'       => 'Services - TCD-Studio/Agency',
        'description' => 'Services of TCD-Studio/Agency for creators, streamers and shop operators.',
        'canonical'   => '/services/',
        'css'         => SITE_CSS,
        'content'     => render_partial('site/services', ['cards' => $cards, 'unavailable' => $services === null]),
    ], $services === null);
}

/* ------------------------------------------------------------------ coming soon */

/**
 * Topics of the coming-soon page: slug => [headline, text, optional [href, label]].
 *
 * @return array<string,array{0:string,1:string,2:?array{0:string,1:string}}>
 */
function site_coming_soon_topics(): array
{
    return [
        'instagram' => [
            'Our Instagram profile is coming soon',
            'We are still setting up our Instagram profile. Until it is online, you can reach us by e-mail or through the contact form on our home page.',
            ['/#ed-713620122', 'Contact us'],
        ],
        'shops' => [
            'Shop partnership – on request',
            'Connecting your shop to our payment system is available on request and in limited numbers during the launch phase. Tell us about your shop and we will get back to you.',
            ['/shops/', 'Learn more and send a request'],
        ],
        'creator-protection' => [
            'Creator Protection – coming soon',
            'We are building further protection services for creators and streamers. Imprint Protection is already available; everything else follows step by step.',
            ['/imprint-protection/', 'Discover Imprint Protection'],
        ],
        'payments' => [
            'Payments – coming soon',
            'Our payment services are in preparation. In the launch phase they are only offered to selected shop partners on request.',
            ['/shops/', 'Shop partnership'],
        ],
        'generic' => [
            'This page is coming soon',
            'We are still working on this page. Please check back later or get in touch if you have any questions.',
            ['/#ed-713620122', 'Contact us'],
        ],
    ];
}

/** Resolve the ?topic= value to [headline, text, link]; unknown topics fall back to a service from the catalogue or the generic text. */
function site_coming_soon_topic(string $topic): array
{
    $topics = site_coming_soon_topics();
    if (preg_match('/^[a-z0-9-]{1,40}$/D', $topic) === 1 && $topic !== 'generic') {
        if (isset($topics[$topic])) {
            return $topics[$topic];
        }
        try {
            $service = service_get($topic);
        } catch (Throwable $e) {
            $service = null;
        }
        if ($service && $service['availability'] !== 'hidden' && trim((string) $service['name']) !== '') {
            $text = trim((string) $service['summary']);
            return [
                $service['name'] . ' – coming soon',
                $text !== '' ? $text : $topics['generic'][1],
                $topics['generic'][2],
            ];
        }
    }
    return $topics['generic'];
}

function site_page_coming_soon(): void
{
    [$headline, $text, $link] = site_coming_soon_topic(get_str('topic', 60));
    site_render([
        'title'       => $headline . ' - TCD-Studio/Agency',
        'description' => $text,
        'canonical'   => '',
        'robots'      => 'noindex, follow',
        'css'         => SITE_CSS,
        'content'     => render_partial('site/coming-soon', ['headline' => $headline, 'text' => $text, 'link' => $link]),
    ]);
}

/* ------------------------------------------------------------------ shops */

/** @return array<string,string> key => label of the expected monthly revenue ranges */
function site_shop_revenue_ranges(): array
{
    return [
        'unknown' => 'Not sure yet',
        'lt1k'    => 'Up to 1,000 EUR',
        '1k-5k'   => '1,000 – 5,000 EUR',
        '5k-20k'  => '5,000 – 20,000 EUR',
        'gt20k'   => 'More than 20,000 EUR',
    ];
}

/**
 * Validate the shop request form.
 *
 * @param array<string,string> $in trimmed input
 * @return array<string,string> errors by field (empty = valid)
 */
function site_shop_validate(array $in): array
{
    $e = [];
    if (!v_len($in['shop_name'], 2, 120) || !v_oneline($in['shop_name'])) {
        $e['shop_name'] = 'Please enter the name of your shop (2–120 characters).';
    }
    if (!v_https_url($in['shop_url'])) {
        $e['shop_url'] = 'Please enter the complete address of your shop, starting with https://';
    }
    if (!v_len($in['country'], 2, 80) || !v_oneline($in['country'])) {
        $e['country'] = 'Please enter the country of your business.';
    }
    if (!v_len($in['contact_name'], 2, 120) || !v_oneline($in['contact_name'])) {
        $e['contact_name'] = 'Please enter the name of the contact person.';
    }
    if (!v_email($in['email'])) {
        $e['email'] = 'Please enter a valid e-mail address.';
    }
    if (!isset(site_shop_revenue_ranges()[$in['revenue']])) {
        $e['revenue'] = 'Please choose an expected revenue range.';
    }
    if (!v_len($in['message'], 0, 2000)) {
        $e['message'] = 'Your message is too long (maximum 2,000 characters).';
    }
    return $e;
}

function site_page_shops(): void
{
    $purpose = 'shops-request';
    $values = ['shop_name' => '', 'shop_url' => '', 'country' => '', 'contact_name' => '', 'email' => '', 'revenue' => '', 'message' => ''];
    $errors = [];
    $notice = '';
    $status = 200;
    $sent = !is_post() && get_str('sent', 2) === '1';

    if (is_post()) {
        foreach (array_keys($values) as $k) {
            $values[$k] = post_str($k, $k === 'message' ? 2000 : 300);
        }
        $values['email'] = mb_strtolower($values['email'], 'UTF-8');

        if (!site_same_origin()) {
            $notice = 'The request could not be verified. Please open this page again and send the form from here.';
            $status = 403;
        } elseif (post_str('website', 200) !== '') {
            // Honeypot filled in: behave as if everything worked, store nothing.
            redirect('/shops/?sent=1');
        } else {
            $tokenState = site_form_token_check($purpose, post_str('_t', 80));
            if ($tokenState === 'invalid') {
                $notice = 'Your form session is not valid. Please check your entries and send the form again.';
                $status = 400;
            } elseif ($tokenState === 'fast') {
                $notice = 'That was very quick. Please check your entries and send the form again.';
            } elseif ($tokenState === 'expired') {
                $notice = 'The form has expired. Please check your entries and send it again.';
            } else {
                $errors = site_shop_validate($values);
                if (!$errors) {
                    if (!rate_limit_hit('shops:ip:' . client_ip(), (array) config('rate.request_ip', [5, 3600]))) {
                        $notice = 'Too many requests from your network. Please try again later or write to us by e-mail.';
                        $status = 429;
                    } else {
                        site_shop_store($values);
                        redirect('/shops/?sent=1');
                    }
                } else {
                    $notice = 'Please check the marked fields.';
                    $status = 422;
                }
            }
        }
    }

    $content = render_partial('site/shops', [
        'values'   => $values,
        'errors'   => $errors,
        'notice'   => $notice,
        'sent'     => $sent,
        'token'    => site_form_token($purpose),
        'ranges'   => site_shop_revenue_ranges(),
        'contact'  => (string) config('mail.requests'),
    ]);
    site_render([
        'title'       => 'Shops - TCD-Studio/Agency',
        'description' => 'Connect your shop to the TCD payment system: payments, tax settlement and payouts from one partner. Available on request in the launch phase.',
        'canonical'   => '/shops/',
        'robots'      => $sent ? 'noindex, follow' : '',
        'css'         => SITE_CSS,
        'content'     => $content,
    ], true, $status);
}

/**
 * Store a validated shop request and notify the team.
 *
 * @param array<string,string> $v
 */
function site_shop_store(array $v): void
{
    $ranges = site_shop_revenue_ranges();
    $payload = [
        'shop_name' => $v['shop_name'], 'url' => $v['shop_url'], 'country' => $v['country'],
        'revenue_range' => $ranges[$v['revenue']], 'message' => $v['message'],
    ];
    $id = request_create('shops', null, $v['contact_name'], $v['email'], $payload);
    audit('request.create', 'request', $id, ['service' => 'shops', 'source' => 'public form']);

    $text = "New shop partnership request (#" . $id . ")\n\n"
        . 'Shop: ' . $v['shop_name'] . "\n"
        . 'URL: ' . $v['shop_url'] . "\n"
        . 'Country: ' . $v['country'] . "\n"
        . 'Contact: ' . $v['contact_name'] . ' <' . $v['email'] . ">\n"
        . 'Expected monthly revenue: ' . $ranges[$v['revenue']] . "\n\n"
        . "Message:\n" . ($v['message'] !== '' ? $v['message'] : '-') . "\n\n"
        . 'Manage it in the admin panel: ' . site_url('/admin/requests.php') . "\n";
    if (!send_mail((string) config('mail.requests'), 'Shop request: ' . $v['shop_name'], $text, $v['email'])) {
        error_log('shop request #' . $id . ': notification mail could not be sent');
    }
}
