<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Helpers of the customer portal (portal/*.php): ownership checks, profile summaries, the
 * submit / withdraw / take-offline workflow around the revision state machine.
 * All functions here are prefixed portal_ so they cannot clash with the admin helpers.
 */

/** Install a friendly 500 page: never show exceptions, paths or half-rendered pages to a customer. */
function portal_guard(): void
{
    set_exception_handler(static function (Throwable $e): void {
        error_log('portal: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        abort(500, __('Something went wrong on our side. Please try again later.'));
    });
}

/** Raw (untrimmed) password field from POST; passwords must not be trimmed or stripped. */
function portal_post_password(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? substr($_POST[$key], 0, 1000) : '';
}

/**
 * Load a profile that belongs to $user. Answers 404 (not 403) for unknown ids AND for other
 * people's profiles, so the existence of a foreign profile is never revealed.
 *
 * @param array<string,mixed> $user
 * @return array<string,mixed>
 */
function portal_owned_profile(int $id, array $user): array
{
    $profile = $id > 0 ? profile_get($id) : null;
    if ($profile === null || $profile['owner_id'] === null || (int) $profile['owner_id'] !== (int) $user['id']) {
        abort(404, __('This page does not exist.'));
    }
    return $profile;
}

/** Number of profiles the user owns and the allowed maximum. @return array{0:int,1:int} */
function portal_profile_limit(int $userId): array
{
    $row = db_one('SELECT COUNT(*) AS n FROM profiles WHERE owner_id = ?', [$userId]);
    return [(int) ($row['n'] ?? 0), max(1, (int) config('max_profiles_per_user', 5))];
}

/**
 * Everything the dashboard / profile page needs to know about one profile.
 *
 * @param array<string,mixed> $profile
 * @return array<string,mixed>
 */
function portal_profile_summary(array $profile): array
{
    $pid = (int) $profile['id'];
    $open = revision_open($pid);
    $live = revision_live($pid);
    $shown = $live ?? $open ?? revision_latest($pid);
    $name = $shown !== null ? (string) ($shown['public']['display_name'] ?? '') : '';
    $isLive = $profile['status'] === 'live';
    return [
        'profile'        => $profile,
        'name'           => $name !== '' ? $name : (string) $profile['slug'],
        'open'           => $open,
        'live'           => $live,
        'is_live'        => $isLive,
        'url'            => $isLive ? imprint_public_url((string) $profile['slug']) : '',
        'update_pending' => $open !== null && $open['state'] === 'submitted' && $profile['live_revision_id'] !== null,
    ];
}

/**
 * The newest review result if it was a rejection (so the customer sees the admin's note), else null.
 *
 * @param array<int,array<string,mixed>> $revisions newest first
 * @return array<string,mixed>|null
 */
function portal_last_rejection(array $revisions): ?array
{
    foreach ($revisions as $rev) {
        if (in_array($rev['state'], ['draft', 'submitted'], true)) {
            continue;
        }
        return $rev['state'] === 'rejected' ? $rev : null;
    }
    return null;
}

/** @return array<string,string> private field => label */
function portal_private_labels(): array
{
    return [
        'legal_name' => __('Full legal name'), 'address_line1' => __('Street and house number'), 'postal_code' => __('Postal code'),
        'city' => __('City'), 'country' => __('Country'), 'phone' => __('Phone'), 'vat_id' => __('VAT ID'), 'notes' => __('Notes for our team'),
    ];
}

/** Names of the fields a validation error list refers to, e.g. "Public name, City". @param array<string,string> $errors */
function portal_error_fields(array $errors): string
{
    $labels = portal_private_labels() + [
        'display_name' => __('Public name'), 'kind' => __('Type'), 'purpose' => __('Business purpose'),
        'links' => __('Links'), 'contact_email' => __('Contact e-mail'),
    ];
    $names = [];
    foreach (array_keys($errors) as $field) {
        $names[] = $labels[$field] ?? '';
    }
    return implode(', ', array_filter($names, static function ($n) {
        return $n !== '';
    }));
}

/** Labels of the mandatory private fields that are still empty. @param array<string,mixed> $private @return string[] */
function portal_private_missing(array $private): array
{
    $labels = portal_private_labels();
    $missing = [];
    foreach (['legal_name', 'address_line1', 'postal_code', 'city', 'country'] as $f) {
        if (trim((string) ($private[$f] ?? '')) === '') {
            $missing[] = $labels[$f];
        }
    }
    return $missing;
}

/** Links of a public data set, normalised to a list of [label, url] arrays. @param array<string,mixed> $public @return array<int,array{label:string,url:string}> */
function portal_links(array $public): array
{
    $out = [];
    foreach ((array) ($public['links'] ?? []) as $l) {
        if (is_array($l) && isset($l['label'], $l['url'])) {
            $out[] = ['label' => (string) $l['label'], 'url' => (string) $l['url']];
        }
    }
    return $out;
}

/** Stable short id of a link (used by the remove buttons so a stale page can never remove the wrong link). */
function portal_link_id(array $link): string
{
    return substr(hash('sha256', (string) ($link['url'] ?? '')), 0, 12);
}

/**
 * submitted → draft for one revision (the customer withdraws a submission before it is reviewed).
 * Guarded in SQL, so it loses cleanly against an admin who approves/rejects at the same moment.
 *
 * @return bool true if the revision was withdrawn
 */
function portal_revision_withdraw(int $revisionId): bool
{
    return (bool) db_transaction(static function () use ($revisionId) {
        $rev = db_one('SELECT * FROM revisions WHERE id = ?', [$revisionId]);
        if (!$rev || $rev['state'] !== 'submitted') {
            return false;
        }
        $n = db_exec(
            'UPDATE revisions SET state = \'draft\', submitted_at = NULL, updated_at = ? WHERE id = ? AND state = \'submitted\'',
            [now(), $revisionId]
        );
        if ($n !== 1) {
            return false;
        }
        $profile = profile_get((int) $rev['profile_id']);
        if ($profile && $profile['live_revision_id'] === null && $profile['status'] === 'pending') {
            profile_set_status((int) $profile['id'], 'draft');
        } else {
            db_exec('UPDATE profiles SET updated_at = ? WHERE id = ?', [now(), (int) $rev['profile_id']]);
        }
        return true;
    });
}

/**
 * Validate a draft for completeness and submit it for review (notifies the team and writes the audit log).
 * Does not check ownership: the caller has to do that.
 *
 * @param array<string,mixed> $profile
 * @param array<string,mixed> $revision the open draft revision (decoded)
 * @return array{ok:bool,errors:array<string,string>}
 */
function portal_submit_draft(array $profile, array $revision, array $user): array
{
    if ($revision['state'] !== 'draft') {
        return ['ok' => false, 'errors' => ['_form' => __('This revision has already been submitted.')]];
    }
    [$public, $pubErrors] = imprint_clean_public($revision['public']);
    [$private, $privErrors] = imprint_clean_private($revision['private'], true);
    $errors = $pubErrors + $privErrors;
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }
    $rid = (int) $revision['id'];
    try {
        db_transaction(static function () use ($rid, $public, $private) {
            revision_save_draft($rid, $public, $private);
            revision_submit($rid);
        });
    } catch (RuntimeException $e) {
        return ['ok' => false, 'errors' => ['_form' => __('This revision can no longer be submitted. Please reload the page.')]];
    }
    $first = $profile['live_revision_id'] === null;
    notify_admins(
        sprintf('Imprint review needed: %s', (string) $public['display_name']),
        "A customer submitted an imprint " . ($first ? 'for the first time' : 'update') . " for review.\n\n"
        . 'Public name: ' . $public['display_name'] . "\n"
        . 'Page name:   ' . $profile['slug'] . "\n"
        . 'Owner:       ' . $user['email'] . "\n\n"
        . "Review it here:\n" . site_url('/admin/review.php?id=' . $rid)
    );
    audit('revision.submit', 'revision', $rid, ['profile_id' => (int) $profile['id'], 'slug' => (string) $profile['slug'], 'first' => $first]);
    return ['ok' => true, 'errors' => []];
}

/**
 * Take a live imprint offline (status "withdrawn", static page removed).
 *
 * @param array<string,mixed> $profile
 * @return bool false if the page could not be removed (then nothing was changed)
 */
function portal_take_offline(array $profile): bool
{
    if ($profile['status'] !== 'live') {
        return false;
    }
    try {
        imprint_unpublish((int) $profile['id']);
    } catch (Throwable $e) {
        error_log('portal_take_offline: unpublish failed: ' . $e->getMessage());
        return false;
    }
    profile_set_status((int) $profile['id'], 'withdrawn');
    audit('profile.take_offline', 'profile', (int) $profile['id'], ['slug' => (string) $profile['slug']]);
    return true;
}

/** Plain-language explanation of a profile status for the customer. */
function portal_status_explanation(string $status, bool $hasLiveRevision): string
{
    switch ($status) {
        case 'live':
            return __('Your imprint page is online. Changes you make go through a new review; the current version stays online until the new one is approved.');
        case 'pending':
            return __('Your submission is being checked by our team. You will get an e-mail as soon as it has been reviewed.');
        case 'suspended':
            return __('This imprint was taken offline by our team. Please contact us if you have questions.');
        case 'withdrawn':
            return __('You took this imprint offline. To publish it again, create a new draft, submit it and wait for approval.');
        default:
            return $hasLiveRevision
                ? __('There is no open draft. Request changes to start a new one.')
                : __('Not published yet. Fill in your details and submit the imprint for review.');
    }
}
