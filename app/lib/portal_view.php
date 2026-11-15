<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/** Small template helpers of the customer portal views. */

/** Error text below a field (empty string if the field has no error). @param array<string,string> $errors */
function portal_err(array $errors, string $key): string
{
    if (empty($errors[$key])) {
        return '';
    }
    return '<div class="pf-error-text" id="err-' . h($key) . '" role="alert">' . h($errors[$key]) . '</div>';
}

/** aria attributes for an input with an error (empty string if the field is fine). @param array<string,string> $errors */
function portal_invalid(array $errors, string $key): string
{
    return empty($errors[$key]) ? '' : ' aria-invalid="true" aria-describedby="err-' . h($key) . '"';
}

/** Short date for tables. */
function portal_date(?int $ts): string
{
    return $ts ? gmdate('Y-m-d', $ts) : '–';
}
