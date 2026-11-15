<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('portal');
portal_guard();
$user = auth_require('customer');

$profile = portal_owned_profile(get_int('id'), $user);      // 404 for foreign ids
$pid = (int) $profile['id'];
$self = '/portal/profile-edit.php?id=' . $pid;
$back = '/portal/profile.php?id=' . $pid;

if ($profile['status'] === 'suspended') {
    redirect($back, ['error' => __('This imprint was suspended by our team. Please contact us.')]);
}
// Only the open DRAFT is editable. A submitted revision is read-only until it is reviewed or withdrawn.
$rev = revision_open($pid);
if ($rev === null) {
    redirect($back, ['error' => __('There is no open draft to edit. Use "Request changes" to start a new one.')]);
}
if ($rev['state'] !== 'draft') {
    redirect($back, ['error' => __('Your submission is waiting for review and cannot be edited. Withdraw it first if you want to change something.')]);
}
$rid = (int) $rev['id'];
$maxLinks = (int) config('max_links', 12);

$stored = $rev['public'] + imprint_public_defaults();
$storedPrivate = $rev['private'] + imprint_private_defaults();
$links = portal_links($stored);
$form = [
    'display_name'       => (string) $stored['display_name'],
    'kind'               => (string) $stored['kind'],
    'purpose'            => (string) $stored['purpose'],
    'show_contact_email' => !empty($stored['show_contact_email']),
    'contact_email'      => (string) $stored['contact_email'],
];
// Customers choose creator kinds only. A draft that already has another kind (set up by our team, e.g. "shop") keeps
// showing exactly that value, so editing and validation keep working; no other non-creator kind can be chosen.
$kinds = imprint_creator_kinds();
$storedKind = (string) $stored['kind'];
if (!isset($kinds[$storedKind]) && isset(imprint_kinds()[$storedKind])) {
    $kinds[$storedKind] = imprint_kinds()[$storedKind];
}
$formPrivate = [];
foreach (array_keys(imprint_private_defaults()) as $field) {
    $formPrivate[$field] = (string) $storedPrivate[$field];
}
$newLink = ['label' => '', 'url' => ''];
$errors = [];

if (!is_post() && get_int('check') === 1) {
    // After a refused submission: show what is missing, without changing anything.
    $errors = imprint_clean_public($stored)[1] + imprint_clean_private($storedPrivate, true)[1];
}

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 60);
    $removeId = '';
    if (strpos($action, 'remove_link:') === 0) {
        $removeId = substr($action, strlen('remove_link:'));
        $action = 'remove_link';
    }
    if (!in_array($action, ['save', 'add_link', 'remove_link', 'save_submit'], true)) {
        redirect($self, ['error' => __('Unknown action.')]);
    }

    // What the customer typed (kept in the form if anything is wrong). The link list is NOT taken from the form.
    $form = [
        'display_name'       => post_str('display_name', 200),
        'kind'               => post_str('kind', 20),
        'purpose'            => post_str('purpose', 2000),
        'show_contact_email' => post_bool('show_contact_email'),
        'contact_email'      => post_str('contact_email', 300),
    ];
    foreach (array_keys($formPrivate) as $field) {
        $formPrivate[$field] = post_str($field, $field === 'notes' ? 3000 : 400);
    }
    $newLink = ['label' => post_str('new_link_label', 200), 'url' => post_str('new_link_url', 1000)];

    // --- link manager -------------------------------------------------------------------------------------
    $linkChanged = false;
    $linkNote = '';
    if ($action === 'remove_link') {
        $kept = [];
        foreach ($links as $link) {
            if (portal_link_id($link) !== $removeId) {
                $kept[] = $link;
            }
        }
        $linkChanged = count($kept) !== count($links);
        $links = $kept;
        $linkNote = $linkChanged ? __('Link removed.') : '';
    }
    if ($action === 'add_link' || $newLink['label'] !== '' || $newLink['url'] !== '') {
        if (!v_len($newLink['label'], 1, 40) || !v_oneline($newLink['label'])) {
            $errors['new_link_label'] = __('Please enter a label of 1–40 characters.');
        }
        if (!v_https_url($newLink['url'])) {
            $errors['new_link_url'] = __('Please enter a complete https:// address, e.g. https://www.twitch.tv/yourname');
        }
        if (!isset($errors['new_link_label']) && !isset($errors['new_link_url'])) {
            foreach ($links as $link) {
                if (mb_strtolower($link['url'], 'UTF-8') === mb_strtolower($newLink['url'], 'UTF-8')) {
                    $errors['new_link_url'] = __('This link is already in your list.');
                }
            }
            if (count($links) >= $maxLinks) {
                $errors['links'] = sprintf(__('At most %d links are allowed.'), $maxLinks);
            }
        }
        if (!isset($errors['new_link_label']) && !isset($errors['new_link_url']) && !isset($errors['links'])) {
            $links[] = ['label' => $newLink['label'], 'url' => $newLink['url']];
            $newLink = ['label' => '', 'url' => ''];
            $linkChanged = true;
            $linkNote = __('Link added.');
        }
    }

    // --- validate everything --------------------------------------------------------------------------------
    [$cleanPublic, $publicErrors] = imprint_clean_public($form + ['links' => $links]);
    [$cleanPrivate, $privateErrors] = imprint_clean_private($formPrivate, false);
    $errors = $errors + $publicErrors + $privateErrors;
    if (!isset($errors['kind']) && !isset($kinds[$form['kind']])) {
        $errors['kind'] = __('Imprint Protection is for content creators and streamers. Please choose one of these types. Imprint protection for shops is available on request.');
    }

    if ($errors === []) {
        revision_save_draft($rid, $cleanPublic, $cleanPrivate);
        $links = $cleanPublic['links'];
        if ($action === 'save_submit') {
            if (!portal_terms_accepted($user)) {
                // The draft is saved, but submitting for review needs the current Terms and Conditions.
                audit('revision.save', 'revision', $rid, ['profile_id' => $pid, 'action' => $action]);
                portal_require_terms($user, __('Your draft was saved. Please accept our current Terms and Conditions (AGB) before you submit it for review.'));
            }
            $fresh = revision_open($pid);
            $result = $fresh !== null ? portal_submit_draft($profile, $fresh, $user) : ['ok' => false, 'errors' => ['_form' => __('There is no draft to submit.')]];
            if ($result['ok']) {
                redirect($back, ['ok' => __('Thank you! Your imprint was submitted. Our team will review it and send you an e-mail.')]);
            }
            $errors = $result['errors'];
            flash_add('error', __('Your changes were saved as a draft, but the imprint cannot be submitted yet. Please complete the highlighted fields.'));
        } else {
            audit('revision.save', 'revision', $rid, ['profile_id' => $pid, 'action' => $action]);
            redirect($self . ($action === 'save' ? '' : '#links'), ['ok' => $linkNote !== '' ? $linkNote : __('Draft saved.')]);
        }
    } else {
        if ($linkChanged) {
            // Do not lose a valid link change just because another field needs attention.
            $partial = $stored;
            $partial['links'] = $links;
            revision_save_draft($rid, $partial, $rev['private']);
            flash_add('ok', $linkNote . ' ' . __('Your other changes were not saved yet; please fix the highlighted fields.'));
        } else {
            flash_add('error', __('Please check the highlighted fields. Nothing was saved.'));
        }
    }
}

render_view('portal/profile-edit', [
    'title'       => __('Edit imprint'),
    'profile'     => $profile,
    'revision'    => $rev,
    'form'        => $form,
    'private'     => $formPrivate,
    'privateLabels' => portal_private_labels(),
    'links'       => $links,
    'newLink'     => $newLink,
    'errors'      => $errors,
    'kinds'       => $kinds,
    'termsAccepted' => portal_terms_accepted($user),
    'maxLinks'    => $maxLinks,
    'self'        => $self,
    'back'        => $back,
]);
