<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * @var array<string,string> $values @var string[] $interest @var array<string,string> $interests
 * @var array<string,string> $errors @var string $notice @var bool $sent @var string[] $sentInterest
 * @var string $token @var array<string,string> $ranges @var string $contact
 */
$field = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? ' site-field--error' : '';
};
$err = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? '<p class="site-error-text" id="err-' . h($name) . '">' . h(__($errors[$name])) . '</p>' : '';
};
?>
        <?= site_headline('Shops – on request', 'h2') ?>

        <div class="ed-element ed-text custom-theme site-page">
<?php if ($sent) : ?>
          <p class="site-lead">Thank you for your request!</p>
          <div class="site-notice site-notice--ok" role="status">
            <p>
              We have received your request and will get back to you by e-mail. Because shops are available on
              request and in limited numbers during the launch phase, it can take a little while.
            </p>
<?php if ($sentInterest) : ?>
            <p>You told us you are interested in:</p>
            <ul class="site-list">
<?php foreach ($sentInterest as $key) : ?>
              <li><?= h(__($interests[$key])) ?></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
          </div>
          <div class="site-actions">
            <a class="button button-large" href="/">Back to start</a>
            <a class="button button-large site-btn-ghost" href="/services/">Our services</a>
          </div>
<?php else : ?>
          <p class="site-lead">
            Imprint protection for shops is available on request only, and payment processing for shops is planned
            via Stripe. Both are offered in limited numbers during the launch phase.
          </p>

          <div class="site-notice">
            <p>
              <strong>Launch phase:</strong> shops are not part of our standard offer. Everything on this page is
              available on request and in limited numbers only. Send us a request below and we will get in touch.
            </p>
          </div>

          <div class="site-section">
            <?= site_headline('Imprint protection for shops', 'h3') ?>
            <div class="site-intro">
              <p>
                Imprint Protection is our service for <a href="/imprint-protection/">creators</a>. For online shops it
                is not part of the standard offer: we provide it on request only and in limited numbers.
              </p>
            </div>
            <div class="site-grid">
              <div class="site-card">
                <h4>Request instead of sign-up</h4>
                <p>Send us a request below. We look at every shop individually and get back to you by e-mail.</p>
              </div>
              <div class="site-card">
                <h4>Limited numbers</h4>
                <p>During the launch phase we can only take on a small number of shops.</p>
              </div>
              <div class="site-card">
                <h4>Agreed individually</h4>
                <p>
                  The conditions are agreed with you individually. They are not covered by the standard
                  <a href="/agb/">Terms and Conditions (AGB)</a> of Imprint Protection for creators.
                </p>
              </div>
            </div>
          </div>

          <div class="site-section">
            <?= site_headline('Shop payments – planned via Stripe', 'h3') ?>
            <div class="site-intro">
              <p>
                In future we plan to process shop payments through the Stripe API. This is <strong>planned and not yet
                available</strong>. Like imprint protection for shops, it will only be offered on request and in
                limited numbers.
              </p>
            </div>
            <div class="site-grid">
              <div class="site-card">
                <span class="site-badge site-badge--coming_soon">Planned</span>
                <h4>Payments via Stripe</h4>
                <p>The payments of your shop are planned to be processed through Stripe, so your customers can pay you through it.</p>
              </div>
              <div class="site-card">
                <span class="site-badge site-badge--coming_soon">Planned</span>
                <h4>Agreed revenue share</h4>
                <p>TCD-Studio/Agency retains an agreed share of the revenue. The terms are agreed with you individually before you start.</p>
              </div>
              <div class="site-card">
                <span class="site-badge site-badge--coming_soon">Planned</span>
                <h4>Tax settlement and payout</h4>
                <p>We take care of the tax settlement and pay out to you according to tax law.</p>
              </div>
            </div>
            <p class="site-center">
              Stripe's own terms apply to the payment processing. There is no launch date yet; conditions are agreed
              with you individually.
            </p>
          </div>

          <div class="site-section">
            <?= site_headline('Send us a request', 'h3') ?>
<?php if ($notice !== '') : ?>
            <div class="site-notice site-notice--error" role="alert"><p><?= h(__($notice)) ?></p></div>
<?php endif; ?>
            <form class="site-form" method="post" action="/shops/" novalidate>
              <input type="hidden" name="_t" value="<?= h($token) ?>">
              <div class="site-hp" aria-hidden="true">
                <label for="f-website">Leave this field empty</label>
                <input type="text" id="f-website" name="website" value="" tabindex="-1" autocomplete="off">
              </div>

              <fieldset class="site-field site-fieldset<?= $field('interest') ?>">
                <legend>I am interested in</legend>
<?php foreach ($interests as $key => $label) : ?>
                <label class="site-check" for="f-interest-<?= h($key) ?>">
                  <input type="checkbox" id="f-interest-<?= h($key) ?>" name="interest[]" value="<?= h($key) ?>"<?= in_array($key, $interest, true) ? ' checked' : '' ?>>
                  <span><?= h(__($label)) ?></span>
                </label>
<?php endforeach; ?>
                <p class="site-help">Please choose at least one option.</p>
                <?= $err('interest') ?>
              </fieldset>

              <div class="site-field<?= $field('shop_name') ?>">
                <label for="f-shop_name">Shop name</label>
                <input type="text" id="f-shop_name" name="shop_name" value="<?= h($values['shop_name']) ?>" maxlength="120" required autocomplete="organization">
                <?= $err('shop_name') ?>
              </div>
              <div class="site-field<?= $field('shop_url') ?>">
                <label for="f-shop_url">Shop address (URL)</label>
                <input type="url" id="f-shop_url" name="shop_url" value="<?= h($values['shop_url']) ?>" maxlength="300" required placeholder="https://" autocomplete="url">
                <?= $err('shop_url') ?>
              </div>
              <div class="site-row">
                <div class="site-field<?= $field('country') ?>">
                  <label for="f-country">Country</label>
                  <input type="text" id="f-country" name="country" value="<?= h($values['country']) ?>" maxlength="80" required autocomplete="country-name">
                  <?= $err('country') ?>
                </div>
                <div class="site-field<?= $field('revenue') ?>">
                  <label for="f-revenue">Expected monthly revenue</label>
                  <select id="f-revenue" name="revenue" required>
                    <option value="">Please choose</option>
<?php foreach ($ranges as $key => $label) : ?>
                    <option value="<?= h($key) ?>"<?= $values['revenue'] === $key ? ' selected' : '' ?>><?= h($label) ?></option>
<?php endforeach; ?>
                  </select>
                  <?= $err('revenue') ?>
                </div>
              </div>
              <div class="site-row">
                <div class="site-field<?= $field('contact_name') ?>">
                  <label for="f-contact_name">Contact name</label>
                  <input type="text" id="f-contact_name" name="contact_name" value="<?= h($values['contact_name']) ?>" maxlength="120" required autocomplete="name">
                  <?= $err('contact_name') ?>
                </div>
                <div class="site-field<?= $field('email') ?>">
                  <label for="f-email">E-mail</label>
                  <input type="email" id="f-email" name="email" value="<?= h($values['email']) ?>" maxlength="254" required autocomplete="email">
                  <?= $err('email') ?>
                </div>
              </div>
              <div class="site-field<?= $field('message') ?>">
                <label for="f-message">Message (optional)</label>
                <textarea id="f-message" name="message" maxlength="2000" rows="6"><?= h($values['message']) ?></textarea>
                <?= $err('message') ?>
              </div>

              <p class="site-help">
                We use your details only to answer your request. More information in our
                <a href="/privacy/">privacy policy</a>. You can also write to us at
                <a href="mailto:<?= h($contact) ?>"><?= h($contact) ?></a>.
              </p>
              <div class="site-actions">
                <button class="button button-large" type="submit">Send request</button>
              </div>
            </form>
          </div>
<?php endif; ?>
        </div>
