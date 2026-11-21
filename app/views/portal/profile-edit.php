<?php
/**
 * @var array<string,mixed> $profile @var array<string,mixed> $revision
 * @var array<string,mixed> $form @var array<string,string> $private @var array<string,string> $privateLabels
 * @var array<int,array{label:string,url:string}> $links @var array<string,string> $newLink @var array<string,string> $errors
 * @var array<string,string> $kinds @var int $maxLinks @var string $self @var string $back @var bool $termsAccepted
 * @var array<string,mixed> $identity @var array<int,array<string,mixed>> $packages @var string $packageSlug
 */
defined('TCD_APP') || exit;
$e = $errors;
?>
<div class="pf-wide-form">
<p><a href="<?= h($back) ?>">&larr; <?= h(__('Back to the imprint overview')) ?></a></p>
<div class="pf-row pf-row--between">
  <h1><?= h(__('Edit imprint')) ?></h1>
  <div class="pf-row"><?= status_badge('draft') ?> <span class="pf-muted pf-small"><?= h(sprintf(__('Version %d'), (int) $revision['number'])) ?></span></div>
</div>
<div class="pf-notice pf-notice--info">
  <?= h(__('You are editing a draft. Saving does not publish anything: when you are ready, submit the draft for review. Our team checks every change before it goes live.')) ?>
  <br><?= h(__('Your page:')) ?> <code><?= h(imprint_public_url((string) $profile['slug'])) ?></code>
</div>
<?php if (!$termsAccepted): ?>
<div class="pf-notice"><?= h(__('You can keep editing your draft. To submit it for review, please accept our current Terms and Conditions (AGB) first.')) ?>
  <a href="/portal/terms.php"><?= h(__('Review and accept')) ?></a></div>
<?php endif; ?>
<?php if (!empty($e['_form'])): ?>
  <div class="pf-flash pf-flash--error" role="alert"><?= h($e['_form']) ?></div>
<?php endif; ?>

<form class="pf-form" method="post" action="<?= h($self) ?>">
  <?= csrf_field() ?>
  <?php /* First submit button = default for the Enter key: it must be "save", never one of the remove buttons below. */ ?>
  <button class="pf-hp" type="submit" name="action" value="save" tabindex="-1" aria-hidden="true"><?= h(__('Save draft')) ?></button>

  <div class="pf-card" id="public">
    <h2><?= h(__('Public information')) ?></h2>
    <p class="pf-muted"><?= h(__('This is what visitors see in your imprint (together with the management details of TCD-Studio/Agency).')) ?></p>
    <div class="pf-field">
      <label for="f-display_name"><?= h(__('Public name')) ?></label>
      <input type="text" id="f-display_name" name="display_name" value="<?= h($form['display_name']) ?>" maxlength="200"<?= portal_invalid($e, 'display_name') ?>>
      <?= portal_err($e, 'display_name') ?>
    </div>
    <div class="pf-field">
      <label for="f-kind"><?= h(__('Type')) ?></label>
      <select id="f-kind" name="kind"<?= portal_invalid($e, 'kind') ?>>
        <?php foreach ($kinds as $key => $label): ?>
          <option value="<?= h($key) ?>"<?= $form['kind'] === $key ? ' selected' : '' ?>><?= h(__($label)) ?></option>
        <?php endforeach; ?>
      </select>
      <?= portal_err($e, 'kind') ?>
    </div>
    <div class="pf-field">
      <label for="f-purpose"><?= h(__('Business purpose')) ?></label>
      <textarea id="f-purpose" name="purpose" rows="4"<?= portal_invalid($e, 'purpose') ?>><?= h($form['purpose']) ?></textarea>
      <div class="pf-help"><?= h(__('A short plain-text description of what you do (10–300 characters). Links and formatting are not possible here.')) ?></div>
      <?= portal_err($e, 'purpose') ?>
    </div>
    <div class="pf-check">
      <input type="checkbox" id="f-show_contact_email" name="show_contact_email" value="1"<?= !empty($form['show_contact_email']) ? ' checked' : '' ?>>
      <label for="f-show_contact_email"><?= h(__('Show a direct contact e-mail address in my imprint')) ?></label>
    </div>
    <div class="pf-field">
      <label for="f-contact_email"><?= h(__('Direct contact e-mail')) ?></label>
      <input type="email" id="f-contact_email" name="contact_email" value="<?= h($form['contact_email']) ?>" maxlength="254" autocomplete="off"<?= portal_invalid($e, 'contact_email') ?>>
      <div class="pf-help"><?= h(__('Hidden by default. It is only published if you tick the box above. By default visitors reach you through the management contact of TCD-Studio/Agency.')) ?></div>
      <?= portal_err($e, 'contact_email') ?>
    </div>
  </div>

  <div class="pf-card" id="links">
    <h2><?= h(__('Links')) ?></h2>
    <p class="pf-muted"><?= h(sprintf(__('Links to your channels and websites that are shown in your imprint (at most %d, https:// only). Adding or removing a link is saved immediately.'), $maxLinks)) ?></p>
    <?= portal_err($e, 'links') ?>
<?php if ($links === []): ?>
    <p class="pf-muted"><?= h(__('No links yet.')) ?></p>
<?php else: ?>
    <ul class="pf-link-list">
      <?php foreach ($links as $link): ?>
        <li>
          <span class="pf-link-text"><strong><?= h($link['label']) ?></strong><br><span class="pf-muted pf-small"><?= h($link['url']) ?></span></span>
          <button class="pf-btn pf-btn--small pf-btn--danger" type="submit" name="action" value="remove_link:<?= h(portal_link_id($link)) ?>"><?= h(__('Remove')) ?></button>
        </li>
      <?php endforeach; ?>
    </ul>
<?php endif; ?>
    <fieldset class="pf-fieldset">
      <legend><?= h(__('Add a link')) ?></legend>
      <div class="pf-grid pf-grid--2">
        <div class="pf-field">
          <label for="f-new_link_label"><?= h(__('Label')) ?></label>
          <input type="text" id="f-new_link_label" name="new_link_label" value="<?= h($newLink['label']) ?>" maxlength="40" placeholder="<?= h(__('e.g. Twitch')) ?>"<?= portal_invalid($e, 'new_link_label') ?>>
          <?= portal_err($e, 'new_link_label') ?>
        </div>
        <div class="pf-field">
          <label for="f-new_link_url"><?= h(__('Address (https://…)')) ?></label>
          <input type="url" id="f-new_link_url" name="new_link_url" value="<?= h($newLink['url']) ?>" maxlength="300" placeholder="https://"<?= portal_invalid($e, 'new_link_url') ?>>
          <?= portal_err($e, 'new_link_url') ?>
        </div>
      </div>
      <div class="pf-actions pf-actions--tight">
        <button class="pf-btn pf-btn--small" type="submit" name="action" value="add_link"><?= h(__('Add link')) ?></button>
      </div>
    </fieldset>
  </div>

  <div class="pf-card" id="private">
    <h2><?= h(__('Private information')) ?></h2>
    <div class="pf-notice pf-notice--ok">
      <strong><?= h(__('These details are never published.')) ?></strong>
      <?= h(__('They are only visible to you and to our team, who need them to verify your identity before approving your imprint. Your imprint shows the management address of TCD-Studio/Agency instead.')) ?>
    </div>
    <p class="pf-muted pf-small"><?= h(__('Legal name, address, postal code, city and country are required before you can submit.')) ?></p>
    <div class="pf-notice pf-notice--info" id="identity-note">
      <strong><?= h(__('A photo of your ID card is needed before the first submission.')) ?></strong>
      <?= h(__('We use it once to verify the name and address above. It is encrypted when you upload it, only authorised staff can open it, and after the check it is kept only encrypted on a separate secure storage until you end the service. You can do this at any time before you submit.')) ?>
      <div class="pf-row">
        <?= portal_identity_badge_html((string) $identity['step']) ?>
        <a href="/portal/identity.php?id=<?= (int) $profile['id'] ?>"><?= h($identity['attention'] ? __('Upload ID photo') : __('Identity check details')) ?></a>
      </div>
    </div>
    <div class="pf-field">
      <label for="f-legal_name"><?= h($privateLabels['legal_name']) ?></label>
      <input type="text" id="f-legal_name" name="legal_name" value="<?= h($private['legal_name']) ?>" maxlength="120" autocomplete="name"<?= portal_invalid($e, 'legal_name') ?>>
      <?= portal_err($e, 'legal_name') ?>
    </div>
    <div class="pf-field">
      <label for="f-address_line1"><?= h($privateLabels['address_line1']) ?></label>
      <input type="text" id="f-address_line1" name="address_line1" value="<?= h($private['address_line1']) ?>" maxlength="120" autocomplete="street-address"<?= portal_invalid($e, 'address_line1') ?>>
      <?= portal_err($e, 'address_line1') ?>
    </div>
    <div class="pf-grid pf-grid--2">
      <div class="pf-field">
        <label for="f-postal_code"><?= h($privateLabels['postal_code']) ?></label>
        <input type="text" id="f-postal_code" name="postal_code" value="<?= h($private['postal_code']) ?>" maxlength="20" autocomplete="postal-code"<?= portal_invalid($e, 'postal_code') ?>>
        <?= portal_err($e, 'postal_code') ?>
      </div>
      <div class="pf-field">
        <label for="f-city"><?= h($privateLabels['city']) ?></label>
        <input type="text" id="f-city" name="city" value="<?= h($private['city']) ?>" maxlength="80" autocomplete="address-level2"<?= portal_invalid($e, 'city') ?>>
        <?= portal_err($e, 'city') ?>
      </div>
    </div>
    <div class="pf-field">
      <label for="f-country"><?= h($privateLabels['country']) ?></label>
      <input type="text" id="f-country" name="country" value="<?= h($private['country']) ?>" maxlength="80" autocomplete="country-name"<?= portal_invalid($e, 'country') ?>>
      <?= portal_err($e, 'country') ?>
    </div>
    <div class="pf-grid pf-grid--2">
      <div class="pf-field">
        <label for="f-phone"><?= h($privateLabels['phone']) ?> <span class="pf-muted">(<?= h(__('optional')) ?>)</span></label>
        <input type="text" id="f-phone" name="phone" value="<?= h($private['phone']) ?>" maxlength="40" autocomplete="tel"<?= portal_invalid($e, 'phone') ?>>
        <?= portal_err($e, 'phone') ?>
      </div>
      <div class="pf-field">
        <label for="f-vat_id"><?= h($privateLabels['vat_id']) ?> <span class="pf-muted">(<?= h(__('optional')) ?>)</span></label>
        <input type="text" id="f-vat_id" name="vat_id" value="<?= h($private['vat_id']) ?>" maxlength="30" autocomplete="off"<?= portal_invalid($e, 'vat_id') ?>>
        <?= portal_err($e, 'vat_id') ?>
      </div>
    </div>
    <div class="pf-field">
      <label for="f-notes"><?= h($privateLabels['notes']) ?> <span class="pf-muted">(<?= h(__('optional')) ?>)</span></label>
      <textarea id="f-notes" name="notes" rows="3"<?= portal_invalid($e, 'notes') ?>><?= h($private['notes']) ?></textarea>
      <?= portal_err($e, 'notes') ?>
    </div>
  </div>

<?php if ($packages !== []): ?>
  <div class="pf-card" id="package-card">
    <h2><?= h(__('Package')) ?></h2>
    <p class="pf-muted"><?= h(__('The package of this imprint profile. A change is saved together with your draft; it does not need a review.')) ?></p>
    <?= render_partial('portal/_package-choice', ['packages' => $packages, 'selected' => $packageSlug, 'errors' => $e, 'help' => '']) ?>
  </div>
<?php endif; ?>

  <div class="pf-actions">
    <button class="pf-btn" type="submit" name="action" value="save"><?= h(__('Save draft')) ?></button>
    <button class="pf-btn pf-btn--primary" type="submit" name="action" value="save_submit"><?= h(__('Save and submit for review')) ?></button>
    <a class="pf-btn pf-btn--ghost" href="<?= h($back) ?>"><?= h(__('Cancel')) ?></a>
  </div>
</form>
</div>
