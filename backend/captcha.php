<?php
declare(strict_types=1);

if (!defined('TCD_API')) {
    http_response_code(403);
    exit;
}

/**
 * Self-hosted image captcha (GD only, no TTF files needed).
 *
 * Flow: the browser POSTs /api.php/form_container/captcha and gets
 * {"image": "<base64 PNG>", "hash": "<token>"}. The token is a random one-time
 * id; the expected answer lives only in the visitor's PHP session. On submit
 * the form sends token + typed answer back and tcd_captcha_verify() checks them.
 */

const TCD_CAPTCHA_WIDTH = 140;
const TCD_CAPTCHA_HEIGHT = 40;
/** Unambiguous alphabet: no 0, O, 1, I, L (and no Q, which looks like O/G in a blocky font). */
const TCD_CAPTCHA_ALPHABET = 'ABCDEFGHJKMNPRSTUVWXYZ23456789';

/**
 * Are the GD functions this captcha needs available?
 *
 * @return bool
 */
function tcd_captcha_available(): bool
{
    foreach (['imagecreatetruecolor', 'imagefilledrectangle', 'imagestring', 'imagecopyresampled',
              'imagecolorat', 'imagesetpixel', 'imageline', 'imagepng'] as $function) {
        if (!function_exists($function)) {
            return false;
        }
    }
    return true;
}

/**
 * Random captcha text from the unambiguous alphabet.
 *
 * @param int $length Number of characters
 * @return string
 */
function tcd_captcha_text(int $length): string
{
    $text = '';
    $max = strlen(TCD_CAPTCHA_ALPHABET) - 1;
    for ($i = 0; $i < $length; $i++) {
        $text .= TCD_CAPTCHA_ALPHABET[random_int(0, $max)];
    }
    return $text;
}

/**
 * Pack an RGB triple into the integer GD uses for truecolor images.
 *
 * @param int $r Red 0-255
 * @param int $g Green 0-255
 * @param int $b Blue 0-255
 * @return int
 */
function tcd_gd_rgb(int $r, int $g, int $b): int
{
    return ($r << 16) | ($g << 8) | $b;
}

/**
 * Draw one character: render it with GD's built-in 9x15 font, scale it up with
 * smoothing and paint it pixel by pixel with a random horizontal wave and shear,
 * which makes the glyphs wobbly while keeping them readable for humans.
 *
 * @param resource|\GdImage $img Target image
 * @param string $char Single character
 * @param int    $x    Left edge of the glyph box
 * @param int    $y    Top edge of the glyph box
 * @param int    $w    Scaled glyph width
 * @param int    $h    Scaled glyph height
 * @param int    $ink  Packed text color
 * @return void
 */
function tcd_captcha_draw_char($img, string $char, int $x, int $y, int $w, int $h, int $ink): void
{
    $small = imagecreatetruecolor(9, 15);
    imagefilledrectangle($small, 0, 0, 8, 14, 0xFFFFFF);
    imagestring($small, 5, 0, 0, $char, 0x000000);

    $big = imagecreatetruecolor($w, $h);
    imagecopyresampled($big, $small, 0, 0, 0, 0, $w, $h, 9, 15);

    $shear = random_int(-25, 25) / 100;
    $phase = random_int(0, 628) / 100;
    $amplitude = random_int(10, 25) / 10;
    $period = random_int(40, 70) / 10;

    for ($py = 0; $py < $h; $py++) {
        $shift = (int) round(sin($py / $period + $phase) * $amplitude + ($py - $h / 2) * $shear);
        for ($px = 0; $px < $w; $px++) {
            $coverage = (255 - (imagecolorat($big, $px, $py) & 0xFF)) / 255;
            $dx = $x + $px + $shift;
            $dy = $y + $py;
            if ($coverage < 0.08 || $dx < 0 || $dx >= TCD_CAPTCHA_WIDTH || $dy < 0 || $dy >= TCD_CAPTCHA_HEIGHT) {
                continue;
            }
            $under = imagecolorat($img, $dx, $dy);
            $r = (int) round((($under >> 16) & 0xFF) * (1 - $coverage) + (($ink >> 16) & 0xFF) * $coverage);
            $g = (int) round((($under >> 8) & 0xFF) * (1 - $coverage) + (($ink >> 8) & 0xFF) * $coverage);
            $b = (int) round(($under & 0xFF) * (1 - $coverage) + ($ink & 0xFF) * $coverage);
            imagesetpixel($img, $dx, $dy, tcd_gd_rgb($r, $g, $b));
        }
    }
}

/**
 * Render the captcha as a 140x40 PNG: light speckled background, dark-grey
 * jittered characters and a few crossing lines.
 *
 * @param string $text Text to show
 * @return string PNG file contents
 * @throws RuntimeException when GD fails
 */
function tcd_captcha_render(string $text): string
{
    $width = TCD_CAPTCHA_WIDTH;
    $height = TCD_CAPTCHA_HEIGHT;
    $img = imagecreatetruecolor($width, $height);
    imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, tcd_gd_rgb(240, 243, 247));

    for ($i = 0; $i < 160; $i++) {                       // light speckles
        $shade = random_int(205, 228);
        imagesetpixel($img, random_int(0, $width - 1), random_int(0, $height - 1), tcd_gd_rgb($shade, $shade, $shade + 8));
    }

    $count = strlen($text);
    $margin = 8;
    $slot = ($width - 2 * $margin) / $count;
    for ($i = 0; $i < $count; $i++) {
        $glyphW = (int) round(9 * random_int(21, 25) / 10);
        $glyphH = (int) round(15 * random_int(18, 21) / 10);
        $x = (int) round($margin + $i * $slot + random_int(-1, 1));
        $y = intdiv($height - $glyphH, 2) + random_int(-3, 3);
        $tone = random_int(35, 75);
        tcd_captcha_draw_char($img, $text[$i], $x, $y, $glyphW, $glyphH, tcd_gd_rgb($tone, $tone, $tone + 6));
    }

    for ($i = 0; $i < 3; $i++) {                         // crossing noise lines
        $tone = random_int(120, 165);
        imageline(
            $img,
            random_int(0, 20),
            random_int(0, $height - 1),
            random_int($width - 20, $width - 1),
            random_int(0, $height - 1),
            tcd_gd_rgb($tone, $tone, $tone)
        );
    }

    // No imagedestroy(): it is a no-op since PHP 8.0 and deprecated in 8.5.
    ob_start();
    $ok = imagepng($img);
    $png = ob_get_clean();
    if (!$ok || !is_string($png) || $png === '') {
        throw new RuntimeException('imagepng failed');
    }
    return $png;
}

/**
 * Remember an answer in the session under a fresh random token. Expired
 * entries are dropped and the oldest ones are evicted so the session can never
 * grow beyond the configured number of tokens.
 *
 * @param array<string, mixed> $config Backend configuration
 * @param string               $answer Expected answer (upper case)
 * @return string The token to hand to the browser (32 hex characters)
 */
function tcd_captcha_store(array $config, string $answer): string
{
    $now = time();
    $state = tcd_state();
    $tokens = (isset($state['captcha']) && is_array($state['captcha'])) ? $state['captcha'] : [];

    foreach ($tokens as $key => $entry) {
        if (!is_array($entry) || !isset($entry['expires']) || (int) $entry['expires'] <= $now) {
            unset($tokens[$key]);
        }
    }
    $limit = max(1, (int) $config['captcha_max_tokens']);
    while (count($tokens) >= $limit) {
        array_shift($tokens);                            // oldest first
    }

    $token = bin2hex(random_bytes(16));
    $tokens[$token] = ['answer' => $answer, 'expires' => $now + (int) $config['captcha_lifetime']];
    tcd_state_set('captcha', $tokens);
    return $token;
}

/**
 * Check a visitor's answer. The token is deleted whether the answer is right
 * or wrong, so every token can be tried exactly once (no brute forcing, no
 * replay of an accepted answer).
 *
 * @param string $token  Token from the hidden "hash" field
 * @param string $answer Text the visitor typed
 * @return bool
 */
function tcd_captcha_verify(string $token, string $answer): bool
{
    $state = tcd_state();
    $tokens = (isset($state['captcha']) && is_array($state['captcha'])) ? $state['captcha'] : [];
    if (preg_match('/^[a-f0-9]{32,64}$/D', $token) !== 1 || !isset($tokens[$token])) {
        return false;
    }

    $entry = $tokens[$token];
    unset($tokens[$token]);                              // single use
    tcd_state_set('captcha', $tokens);

    if (!is_array($entry) || !isset($entry['answer'], $entry['expires']) || (int) $entry['expires'] <= time()) {
        return false;
    }
    $given = strtoupper((string) preg_replace('/\s+/', '', $answer));
    return hash_equals((string) $entry['answer'], $given);
}

/**
 * Route handler: POST /api.php/form_container/captcha
 *
 * @param array<string, mixed> $config Backend configuration
 * @return void
 */
function tcd_captcha_handle(array $config): void
{
    if (!tcd_captcha_available()) {
        tcd_respond_text(500, 'Captcha unavailable');
        return;
    }
    tcd_session_start($config);

    $text = tcd_captcha_text(max(3, (int) $config['captcha_length']));
    $png = tcd_captcha_render($text);
    $token = tcd_captcha_store($config, $text);

    tcd_respond_json(['image' => base64_encode($png), 'hash' => $token]);
}
