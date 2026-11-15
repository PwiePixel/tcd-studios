<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Ending the service in the customer portal (portal/terminate.php). The deletion itself is done by erase.php
 * (termination_request / termination_confirm / account_erase); this file only describes the state for the views.
 */

/**
 * State of the deletion request of a customer.
 *
 * status: none | requested (confirmation link mailed) | confirmed (confirmed, but the deletion failed and can be retried)
 *         | on_hold (legal hold: the deletion waits)
 *
 * @param array<string,mixed> $user a users row
 * @return array{status:string,created_at:?int,link_until:?int,link_valid:bool,legal_hold:bool}
 */
function portal_termination_state(array $user): array
{
    $pending = termination_pending((int) $user['id']);
    if ($pending === null) {
        return ['status' => 'none', 'created_at' => null, 'link_until' => null, 'link_valid' => false, 'legal_hold' => !empty($user['legal_hold'])];
    }
    $until = $pending['token_expires'] !== null ? (int) $pending['token_expires'] : null;
    return [
        'status'     => (string) $pending['status'],
        'created_at' => (int) $pending['created_at'],
        'link_until' => $until,
        'link_valid' => $pending['token_hash'] !== null && $until !== null && $until >= now(),
        'legal_hold' => !empty($user['legal_hold']),
    ];
}

/**
 * The customer withdraws a request that was not confirmed yet. Only the status "requested" can be withdrawn.
 *
 * @return bool true if a request was withdrawn
 */
function portal_termination_cancel(int $userId): bool
{
    $pending = termination_pending($userId);
    if ($pending === null || $pending['status'] !== 'requested') {
        return false;
    }
    $n = db_exec(
        'UPDATE deletion_requests SET status = \'cancelled\', token_hash = NULL, token_expires = NULL WHERE id = ? AND status = \'requested\'',
        [(int) $pending['id']]
    );
    if ($n !== 1) {
        return false;
    }
    audit('termination.cancelled', 'deletion_request', (int) $pending['id'], [], $userId);
    notify_admins('Deletion request withdrawn', 'A customer withdrew the request to end the service (customer #' . $userId . "). Nothing was deleted.");
    return true;
}

/** Plain-language list of what the deletion removes (shown before the customer confirms). @return string[] */
function portal_termination_effects(): array
{
    return [
        __('Your imprint pages go offline immediately.'),
        __('Your account, all imprint profiles with all versions and your service requests are deleted.'),
        __('Your address data (legal name, address, phone, VAT ID) is deleted.'),
        __('Your ID photos are deleted from our web server and from the secure storage system (inbox and archive).'),
        __('Nothing can be restored afterwards: the deletion is irrevocable.'),
        __('You may no longer use the service. To use it again you would have to register again and repeat the identity check.'),
    ];
}
