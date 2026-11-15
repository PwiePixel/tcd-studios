<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Identity check (photo of the ID card) in the customer portal.
 *
 * The cryptography, storage and limits live in vault.php (vault_store_upload() & co.). This file only translates the
 * state of a profile's identity request into what the customer sees and decides whether submitting is allowed yet.
 * The portal NEVER shows, links or echoes an uploaded image or any ciphertext: only side, upload time and size.
 */

/** Maximum size of one uploaded image in whole MB (for the texts; the limit itself is enforced by vault.php). */
function portal_identity_max_mb(): int
{
    return max(1, intdiv((int) config('vault.max_upload_bytes', 8388608), 1048576));
}

/**
 * Everything the portal needs to know about the identity check of one profile.
 *
 * Keys: status (none|uploaded|verified|archived|rejected), step (none|needed|new_check|uploaded|verified|rejected),
 *       files (list of side/created_at/size/archived - no names, no references), note (team note of a rejection),
 *       required (does submitting the open/next draft need a check), present (identity_upload_present()),
 *       locked (nothing can be changed any more), can_upload, can_remove, verified_profile, archived_at.
 *
 * @param array<string,mixed> $profile a profiles row (fresh)
 * @return array<string,mixed>
 */
function portal_identity_state(array $profile): array
{
    $pid = (int) $profile['id'];
    $req = identity_request_for_profile($pid);
    $status = $req === null ? 'none' : (string) $req['status'];
    $files = [];
    $archivedAt = null;
    if ($req !== null && $status !== 'rejected') {
        foreach (identity_files((int) $req['id']) as $f) {
            $files[] = [
                'id'         => (int) $f['id'],
                'side'       => (string) $f['side'],
                'created_at' => (int) $f['created_at'],
                'size'       => (int) $f['plain_size'],
                'archived'   => $f['archive_ref'] !== null && $f['archive_ref'] !== '',
            ];
            if ($f['archived_at'] !== null) {
                $archivedAt = max((int) $archivedAt, (int) $f['archived_at']);
            }
        }
    }
    if ($status === 'uploaded' && $files === []) {
        $status = 'none';                       // an open request without any image counts as "not started"
    }

    $open = revision_open($pid);
    $ref = $open ?? revision_latest($pid);
    $required = $ref !== null && identity_required_for($profile, $ref);
    $present = identity_upload_present($pid);
    $suspended = (string) $profile['status'] === 'suspended';
    $verifiedProfile = !empty($profile['identity_verified']);
    $done = in_array($status, ['verified', 'archived'], true);
    // An archived check does not cover changed name/address data: a new photo is needed (a new request is opened).
    $newCheck = $status === 'archived' && $required;
    $locked = ($done && !$newCheck) || ($status === 'none' && $verifiedProfile && !$required);

    if ($status === 'rejected') {
        $step = 'rejected';
    } elseif ($locked) {
        $step = 'verified';
    } elseif ($status === 'uploaded') {
        $step = 'uploaded';
    } elseif ($newCheck) {
        $step = 'new_check';
    } elseif ($required) {
        $step = 'needed';
    } else {
        $step = 'none';
    }

    return [
        'status'           => $status,
        'step'             => $step,
        'files'            => $files,
        'note'             => $status === 'rejected' ? (string) $req['note'] : '',
        'required'         => $required,
        'present'          => $present,
        'locked'           => $locked,
        'can_upload'       => !$suspended && !$locked,
        'can_remove'       => $status === 'uploaded' && $files !== [] && !$suspended,
        'verified_profile' => $verifiedProfile,
        'archived_at'      => $archivedAt,
        // The customer still has something to do before the draft can be submitted.
        'attention'        => $open !== null && $open['state'] === 'draft' && in_array($step, ['needed', 'new_check', 'rejected'], true),
    ];
}

/** Badge (class suffix + label) for a step of the identity check. @return array{0:string,1:string} */
function portal_identity_badge(string $step): array
{
    switch ($step) {
        case 'needed':
            return ['pending', __('ID photo needed')];
        case 'new_check':
            return ['pending', __('New ID check needed')];
        case 'uploaded':
            return ['info', __('Uploaded, in review')];
        case 'verified':
            return ['ok', __('Verified')];
        case 'rejected':
            return ['rejected', __('Not accepted')];
        default:
            return ['draft', __('Not started')];
    }
}

/** <span class="pf-badge …">…</span> of a step (escaped). */
function portal_identity_badge_html(string $step): string
{
    [$class, $label] = portal_identity_badge($step);
    return '<span class="pf-badge pf-badge--' . h($class) . '">' . h($label) . '</span>';
}

/** One-paragraph explanation of the state for the customer. @param array<string,mixed> $state */
function portal_identity_explanation(array $state): string
{
    switch ((string) $state['step']) {
        case 'needed':
            return __('To approve your imprint our team has to verify your name and address. For that we need a photo of your ID card (front and back).');
        case 'new_check':
            return __('You changed your legal name or address. Your earlier check does not cover the new details, so we need a new photo of your ID card before the change can be approved.');
        case 'uploaded':
            return __('Your images are uploaded and waiting for review. Our team opens them when they check your imprint. Until then you can replace or remove an image.');
        case 'rejected':
            return __('We could not use your images for the check. Please upload new, clear images of your ID card.');
        case 'verified':
            if ($state['status'] === 'archived') {
                return __('Your identity was verified. Your ID images were removed from our web server and are stored only encrypted on a separate, secured storage system until you end the service.');
            }
            if ($state['status'] === 'verified') {
                return __('Your identity was verified. Your encrypted ID images are being moved to a separate, secured storage system.');
            }
            return __('Our team has verified your identity for this imprint. No ID photo is stored. If you change your legal name or address later, we will ask for a new photo.');
        default:
            return __('Nothing to do right now.');
    }
}

/**
 * Is submitting this revision blocked because the identity check is missing? Returns the customer message, or null if
 * the revision may be submitted. (Needs a new check for the first submission and when name/address changed.)
 *
 * @param array<string,mixed> $profile
 * @param array<string,mixed> $revision decoded revision with 'private'
 */
function portal_identity_gate(array $profile, array $revision): ?string
{
    if (!identity_required_for($profile, $revision) || identity_upload_present((int) $profile['id'])) {
        return null;
    }
    return __('Your draft is saved, but it was not submitted yet: please upload a photo of your ID card (front and back) first so that our team can verify your name and address.');
}

/**
 * Variables of the view portal/identity (shared by the page and by the upload handler that re-renders it with errors).
 *
 * @param array<string,mixed> $profile
 * @param array<string,string> $errors keys front, back, _form
 * @return array<string,mixed>
 */
function portal_identity_view_vars(array $profile, array $errors = []): array
{
    $summary = portal_profile_summary($profile);
    return [
        'title'       => __('Identity check'),
        'profile'     => $profile,
        'name'        => (string) $summary['name'],
        'state'       => $summary['identity'],
        'errors'      => $errors,
        'vaultReady'  => vault_configured(),
        'transportOk' => vault_transport_ok(),
        'maxMb'       => portal_identity_max_mb(),
    ];
}

/** Human size of an image ("1.2 MB", "340 KB"). */
function portal_identity_size(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, '.', '') . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}
