<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Terms and Conditions (AGB) of Imprint Protection: acceptance gate of the customer portal and the evidence
 * that is shown to the team. Acceptance is stored per user (users.terms_accepted_at, users.terms_version);
 * config('terms_version') is the version customers have to have accepted.
 */

/** The version of the Terms and Conditions that is currently in force. */
function portal_terms_version(): string
{
    return (string) config('terms_version', '');
}

/**
 * Has the user accepted the CURRENT version of the Terms and Conditions?
 *
 * @param array<string,mixed> $user a users row
 */
function portal_terms_accepted(array $user): bool
{
    return (string) ($user['terms_version'] ?? '') === portal_terms_version();
}

/**
 * Gate for creating and submitting imprints: sends the customer to the acceptance page unless the current
 * version is accepted. Viewing and editing drafts is not gated. Never returns when the gate is closed.
 *
 * @param array<string,mixed> $user a users row
 */
function portal_require_terms(array $user, string $message = ''): void
{
    if (portal_terms_accepted($user)) {
        return;
    }
    redirect('/portal/terms.php', ['error' => $message !== ''
        ? $message
        : __('Please accept our current Terms and Conditions (AGB) before you create or submit an imprint.')]);
}

/** Record the acceptance of the current version (time + version) and write the audit log. No personal data in the log. */
function portal_terms_accept(int $userId): void
{
    $version = portal_terms_version();
    db_exec('UPDATE users SET terms_accepted_at = ?, terms_version = ? WHERE id = ?', [now(), $version, $userId]);
    audit('user.terms_accepted', 'user', $userId, ['version' => $version], $userId);
}

/**
 * Evidence of a user's acceptance: version and time, or nulls if the user never accepted.
 *
 * @param array<string,mixed> $user a users row
 * @return array{version:?string,at:?int,current:bool}
 */
function portal_terms_info(array $user): array
{
    $version = isset($user['terms_version']) && $user['terms_version'] !== null && (string) $user['terms_version'] !== '' ? (string) $user['terms_version'] : null;
    $at = isset($user['terms_accepted_at']) && $user['terms_accepted_at'] !== null ? (int) $user['terms_accepted_at'] : null;
    return ['version' => $version, 'at' => $at, 'current' => $version !== null && portal_terms_accepted($user)];
}

/**
 * Admin helper: the acceptance of a customer as escaped HTML (badge + version + date), shared by the review page and
 * the customer page. A missing acceptance is a warning badge; an outdated version is marked as such.
 *
 * @param array<string,mixed> $user a users row
 */
function terms_status_html(array $user): string
{
    $info = portal_terms_info($user);
    if ($info['version'] === null) {
        return '<span class="pf-badge pf-badge--pending">' . h(__('Terms not accepted')) . '</span>';
    }
    $html = '<span class="pf-badge pf-badge--ok">' . h(__('Terms accepted')) . '</span> '
        . h(sprintf(__('Version %s'), $info['version'])) . ', ' . h(fmt_time($info['at']));
    if (!$info['current']) {
        $html .= ' <span class="pf-badge pf-badge--pending">' . h(__('older version')) . '</span>'
            . ' <span class="pf-muted pf-small">' . h(sprintf(__('current: %s'), portal_terms_version())) . '</span>';
    }
    return $html;
}
