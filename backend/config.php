<?php
declare(strict_types=1);

if (!defined('TCD_API')) {
    http_response_code(403);
    exit;
}

/**
 * Site-specific settings for the contact form backend. This is the ONLY file
 * you normally need to edit.
 *
 * IMPORTANT: the mailbox in 'recipient' (contact@tcd-studio.de) MUST EXIST and
 * be able to receive mail, and the sender domain should be hosted on the same
 * server (SPF/DKIM) - otherwise messages are silently dropped or land in spam.
 *
 * Keep the text values free of the characters < > & : error messages are shown
 * by the browser runtime as raw HTML.
 *
 * No secrets belong in here: this file is part of the website repository.
 */
return [
    // --- Mail ---------------------------------------------------------------
    'recipient'  => 'contact@tcd-studio.de',          // who receives the requests (must exist!)
    'sender'     => 'no-reply@tcd-studio.de',         // From: address and envelope sender
    'site_name'  => 'TCD-Studio/Agency',              // display name used in the From: header
    'mail_subject' => 'New contact request via tcd-studio.de',
    // Pass "-f <sender>" to sendmail. Set to false if the host rejects it.
    'use_envelope_sender' => true,

    // --- Messages shown to the visitor -------------------------------------
    'success_message' => 'Thank you for your message! We will get back to you as soon as possible.',

    // --- Form ---------------------------------------------------------------
    // Numeric id of the form container; POST data arrives as form_<id>[ed-f-...].
    'form_id' => '713850534',

    // Field id => label used in the e-mail.
    'fields' => [
        'ed-f-713850537' => 'Company',
        'ed-f-713850540' => 'Name',
        'ed-f-713850543' => 'Phone',
        'ed-f-713850546' => 'Email',
        'ed-f-713850549' => 'Message',
    ],

    // Maximum length in characters per field. Longer input is rejected (400).
    'max_lengths' => [
        'ed-f-713850537' => 120,
        'ed-f-713850540' => 120,
        'ed-f-713850543' => 40,
        'ed-f-713850546' => 254,
        'ed-f-713850549' => 5000,
    ],

    // Which field id plays which role.
    'roles' => [
        'name'    => 'ed-f-713850540',
        'email'   => 'ed-f-713850546',
        'message' => 'ed-f-713850549',   // the only multi-line field
        'consent' => 'ed-f-713850552',   // privacy-policy checkbox (required)
        'captcha' => 'ed-f-713850555',   // [text] = answer, [hash] = token
    ],

    // --- Spam protection ----------------------------------------------------
    'min_fill_seconds'   => 3,      // faster submissions are treated as bots
    'cooldown_seconds'   => 30,     // pause between two sent messages per session
    'captcha_lifetime'   => 1800,   // seconds a captcha stays valid (30 min)
    'captcha_length'     => 5,      // number of characters in the image
    'captcha_max_tokens' => 10,     // unsolved captchas kept per session
];
