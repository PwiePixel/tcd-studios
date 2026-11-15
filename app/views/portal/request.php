<?php
/**
 * @var array<string,mixed> $service @var bool $isShop @var array<string,string> $ranges
 * @var array<string,string> $values @var array<string,string> $errors
 */
defined('TCD_APP') || exit;
?>
<p><a href="/portal/services.php">&larr; <?= h(__('Services')) ?></a></p>
<div class="pf-card pf-card--accent pf-wide-form">
  <h1><?= h(sprintf(__('Request: %s'), (string) $service['name'])) ?></h1>
  <p><?= h($service['summary']) ?></p>
<?php if ($service['availability'] === 'coming_soon'): ?>
  <div class="pf-notice pf-notice--info"><?= h(__('This service is not available yet. Send us a request and we will get in touch when it launches.')) ?></div>
<?php endif; ?>
<?php if ($isShop): ?>
  <div class="pf-notice"><?= h(__('In the launch phase the shop partnership (payments, tax settlement and payouts; we retain an agreed share) is available on request and in limited numbers only.')) ?></div>
<?php endif; ?>
  <?php if (!empty($errors['form'])): ?>
    <div class="pf-flash pf-flash--error" role="alert"><?= h($errors['form']) ?></div>
  <?php endif; ?>
  <form class="pf-form" method="post" action="/portal/request.php?service=<?= h(rawurlencode((string) $service['slug'])) ?>">
    <?= csrf_field() ?>
    <div class="pf-grid pf-grid--2">
      <div class="pf-field">
        <label for="f-name"><?= h(__('Your name')) ?></label>
        <input type="text" id="f-name" name="name" value="<?= h($values['name']) ?>" maxlength="80" required<?= portal_invalid($errors, 'name') ?>>
        <?= portal_err($errors, 'name') ?>
      </div>
      <div class="pf-field">
        <label for="f-email"><?= h(__('E-mail address')) ?></label>
        <input type="email" id="f-email" name="email" value="<?= h($values['email']) ?>" maxlength="254" required<?= portal_invalid($errors, 'email') ?>>
        <?= portal_err($errors, 'email') ?>
      </div>
    </div>
<?php if ($isShop): ?>
    <fieldset class="pf-fieldset">
      <legend><?= h(__('Your shop')) ?></legend>
      <div class="pf-grid pf-grid--2">
        <div class="pf-field">
          <label for="f-shop_name"><?= h(__('Shop name')) ?></label>
          <input type="text" id="f-shop_name" name="shop_name" value="<?= h($values['shop_name']) ?>" maxlength="80" required<?= portal_invalid($errors, 'shop_name') ?>>
          <?= portal_err($errors, 'shop_name') ?>
        </div>
        <div class="pf-field">
          <label for="f-shop_url"><?= h(__('Shop URL')) ?></label>
          <input type="url" id="f-shop_url" name="shop_url" value="<?= h($values['shop_url']) ?>" maxlength="300" placeholder="https://" required<?= portal_invalid($errors, 'shop_url') ?>>
          <?= portal_err($errors, 'shop_url') ?>
        </div>
        <div class="pf-field">
          <label for="f-country"><?= h(__('Country of your business')) ?></label>
          <input type="text" id="f-country" name="country" value="<?= h($values['country']) ?>" maxlength="60" required<?= portal_invalid($errors, 'country') ?>>
          <?= portal_err($errors, 'country') ?>
        </div>
        <div class="pf-field">
          <label for="f-monthly_revenue"><?= h(__('Expected monthly revenue')) ?></label>
          <select id="f-monthly_revenue" name="monthly_revenue" required<?= portal_invalid($errors, 'monthly_revenue') ?>>
            <option value=""><?= h(__('Please choose…')) ?></option>
            <?php foreach ($ranges as $key => $label): ?>
              <option value="<?= h($key) ?>"<?= $values['monthly_revenue'] === $key ? ' selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?= portal_err($errors, 'monthly_revenue') ?>
        </div>
      </div>
      <div class="pf-field">
        <label for="f-payment_provider"><?= h(__('Current payment provider')) ?> <span class="pf-muted">(<?= h(__('optional')) ?>)</span></label>
        <input type="text" id="f-payment_provider" name="payment_provider" value="<?= h($values['payment_provider']) ?>" maxlength="80"<?= portal_invalid($errors, 'payment_provider') ?>>
        <div class="pf-help"><?= h(__('For example PayPal, Stripe or Mollie.')) ?></div>
        <?= portal_err($errors, 'payment_provider') ?>
      </div>
    </fieldset>
<?php endif; ?>
    <div class="pf-field">
      <label for="f-message"><?= h(__('Message')) ?><?= $isShop ? ' (' . h(__('optional')) . ')' : '' ?></label>
      <textarea id="f-message" name="message" rows="5" maxlength="2000"<?= $isShop ? '' : ' required' ?><?= portal_invalid($errors, 'message') ?>><?= h($values['message']) ?></textarea>
      <?= portal_err($errors, 'message') ?>
    </div>
    <div class="pf-actions">
      <button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Send request')) ?></button>
      <a class="pf-btn pf-btn--ghost" href="/portal/services.php"><?= h(__('Cancel')) ?></a>
    </div>
  </form>
</div>
