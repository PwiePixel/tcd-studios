<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/** Per-session CSRF token. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token(32);
    }
    return $_SESSION['csrf'];
}

/** Hidden input for every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

/** Verify the token of a POST request; answers 403 and stops on failure. */
function csrf_verify(): void
{
    $given = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : '';
    if ($given === '' || empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], $given)) {
        abort(403, __('Your form session expired. Please go back, reload the page and try again.'));
    }
}
