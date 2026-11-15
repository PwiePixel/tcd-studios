<?php
declare(strict_types=1);

if (!defined('TCD_API')) {
    http_response_code(403);
    exit;
}

/**
 * Contact form submission: POST /api.php/form_container/submit
 *
 * tcd_contact_handle() runs the checks in a fixed order; the first failure
 * ends the request with a plain-text error (see tcd_fail()).
 */

/**
 * Honeypot: the page contains a hidden, unchecked checkbox "tos". Humans never
 * tick it; bots that fill every input do.
 *
 * @return bool
 */
function tcd_is_honeypot(): bool
{
    if (!isset($_POST['tos'])) {
        return false;
    }
    $value = $_POST['tos'];
    if (is_array($value)) {
        return count($value) > 0;
    }
    $value = trim((string) $value);
    return $value !== '' && $value !== '0';
}

/**
 * The posted form_<id>[...] array, or an empty array.
 *
 * @param array<string, mixed> $config Backend configuration
 * @return array<string, mixed>
 */
function tcd_posted_form(array $config): array
{
    $key = 'form_' . $config['form_id'];
    return (isset($_POST[$key]) && is_array($_POST[$key])) ? $_POST[$key] : [];
}

/**
 * Reject forms submitted faster than a human could fill them in. "tac" is the
 * number of seconds since the form was rendered, sent by the browser runtime.
 *
 * @param array<string, mixed> $config Backend configuration
 * @return void
 * @throws TcdHttpException 400
 */
function tcd_check_fill_time(array $config): void
{
    $tac = 0;
    if (isset($_POST['tac']) && is_string($_POST['tac']) && preg_match('/^\d{1,9}$/D', $_POST['tac']) === 1) {
        $tac = (int) $_POST['tac'];
    }
    if ($tac < (int) $config['min_fill_seconds']) {
        tcd_fail(400, 'Please take a moment to fill in the form.');
    }
}

/**
 * One message per session per cooldown period. The timestamp is only stored
 * after a successful send, so a failed attempt never blocks the visitor.
 *
 * @param array<string, mixed> $config Backend configuration
 * @return void
 * @throws TcdHttpException 429
 */
function tcd_check_cooldown(array $config): void
{
    $state = tcd_state();
    $last = isset($state['last_sent']) ? (int) $state['last_sent'] : 0;
    if ($last > 0 && time() - $last < (int) $config['cooldown_seconds']) {
        tcd_fail(429, 'Please wait a moment before sending another message.');
    }
}

/**
 * The privacy-policy checkbox is required. A ticked box is posted as
 * ed-f-...[]=0, so presence (not truthiness) is what counts.
 *
 * @param array<string, mixed> $config Backend configuration
 * @param array<string, mixed> $form   Posted form_<id> data
 * @return void
 * @throws TcdHttpException 400
 */
function tcd_check_consent(array $config, array $form): void
{
    $value = isset($form[$config['roles']['consent']]) ? $form[$config['roles']['consent']] : null;
    $given = is_array($value) ? count($value) > 0 : (is_string($value) && $value !== '');
    if (!$given) {
        tcd_fail(400, 'Please accept the privacy policy.');
    }
}

/**
 * Verify (and thereby burn) the captcha token. Wrong, expired and replayed
 * tokens are deliberately indistinguishable. The token is gone afterwards; js/site.js
 * therefore fetches a fresh captcha whenever the form reports an error.
 *
 * @param array<string, mixed> $config Backend configuration
 * @param array<string, mixed> $form   Posted form_<id> data
 * @return void
 * @throws TcdHttpException 400
 */
function tcd_check_captcha(array $config, array $form): void
{
    $field = isset($form[$config['roles']['captcha']]) ? $form[$config['roles']['captcha']] : [];
    $field = is_array($field) ? $field : [];
    $token = (isset($field['hash']) && is_string($field['hash'])) ? $field['hash'] : '';
    $answer = (isset($field['text']) && is_string($field['text'])) ? $field['text'] : '';

    if (!tcd_captcha_verify($token, $answer)) {
        tcd_fail(400, 'Wrong security code. Please try again with the new code.');
    }
}

/**
 * Number of characters (not bytes) in a UTF-8 string.
 *
 * @param string $text Valid UTF-8
 * @return int
 */
function tcd_strlen(string $text): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }
    return (int) preg_match_all('/./su', $text);
}

/**
 * Normalise one posted value. Single-line fields lose all control characters
 * (CR, LF, tab, NUL, ...); the message keeps line breaks and tabs only.
 *
 * @param string $raw       Valid UTF-8
 * @param bool   $multiline Keep line breaks?
 * @return string
 */
function tcd_clean_value(string $raw, bool $multiline): string
{
    if ($multiline) {
        $value = preg_replace('/\r\n|\r|\x{2028}|\x{2029}/u', "\n", $raw);
        $value = preg_replace('/(?![\n\t])\p{Cc}/u', '', (string) $value);
    } else {
        $value = preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', $raw);
    }
    return trim((string) $value);
}

/**
 * Collect the configured fields from the POST data, cleaned.
 *
 * @param array<string, mixed> $config Backend configuration
 * @param array<string, mixed> $form   Posted form_<id> data
 * @return array<string, string> Field id => cleaned value ('' when missing)
 * @throws TcdHttpException 400 for arrays or invalid UTF-8
 */
function tcd_collect_values(array $config, array $form): array
{
    $values = [];
    foreach (array_keys($config['fields']) as $id) {
        $raw = isset($form[$id]) ? $form[$id] : '';
        if (!is_string($raw) || preg_match('//u', $raw) !== 1) {
            tcd_fail(400, 'Invalid form data.');
        }
        $values[$id] = tcd_clean_value($raw, $id === $config['roles']['message']);
    }
    return $values;
}

/**
 * Content checks: e-mail syntax, "something was filled in", maximum lengths
 * (too long input is rejected, never silently truncated).
 *
 * @param array<string, mixed>  $config Backend configuration
 * @param array<string, string> $values Cleaned values
 * @return void
 * @throws TcdHttpException 400
 */
function tcd_validate_values(array $config, array $values): void
{
    $roles = $config['roles'];
    $email = $values[$roles['email']];

    if ($email !== '' && !tcd_is_valid_email($email)) {
        tcd_fail(400, 'Please enter a valid email address.');
    }
    if ($values[$roles['message']] === '' && $values[$roles['name']] === '' && $email === '') {
        tcd_fail(400, 'Please fill in the form.');
    }
    foreach ($config['max_lengths'] as $id => $max) {
        if (isset($values[$id]) && tcd_strlen($values[$id]) > (int) $max) {
            tcd_fail(400, sprintf('%s is too long (maximum %d characters).', $config['fields'][$id], (int) $max));
        }
    }
}

/**
 * Route handler: POST /api.php/form_container/submit
 *
 * @param array<string, mixed> $config Backend configuration
 * @return void
 */
function tcd_contact_handle(array $config): void
{
    if (!tcd_origin_matches_host()) {
        tcd_fail(403, 'Forbidden.');
    }
    if (tcd_is_honeypot()) {                             // pretend success, send nothing
        tcd_respond_success_html((string) $config['success_message']);
        return;
    }
    tcd_check_fill_time($config);
    tcd_session_start($config);
    tcd_check_cooldown($config);

    $form = tcd_posted_form($config);
    tcd_check_consent($config, $form);

    // Content errors come before the captcha check on purpose: the captcha token
    // is single-use, so a typo in the e-mail address must not cost the visitor
    // their (correct) captcha answer.
    $values = tcd_collect_values($config, $form);
    tcd_validate_values($config, $values);
    tcd_check_captcha($config, $form);

    if (!tcd_mail_send($config, $values)) {
        tcd_fail(500, 'Could not send the message. Please try again later.');
    }
    tcd_state_set('last_sent', time());                  // cooldown starts only after success
    tcd_respond_success_html((string) $config['success_message']);
}
