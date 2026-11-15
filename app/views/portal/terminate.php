<?php
/**
 * @var string $mode start|requested|confirm|retry|hold|invalid_link
 * @var array{status:string,created_at:?int,link_until:?int,link_valid:bool,legal_hold:bool} $state
 * @var string $token @var array<string,string> $errors @var string $formError @var string $holdMessage
 * @var string[] $effects @var string $email @var int $linkHours
 */
defined('TCD_APP') || exit;
?>
<div class="pf-wide-form">
<p><a href="/portal/account.php">&larr; <?= h(__('Account')) ?></a></p>
<h1><?= h(__('End the service')) ?></h1>

<?php if ($formError !== ''): ?>
  <div class="pf-flash pf-flash--error" role="alert" id="terminate-error"><?= h($formError) ?></div>
<?php endif; ?>

<?php if ($mode === 'hold'): ?>
  <div class="pf-card pf-card--accent" id="terminate-hold">
    <h2><?= h(__('Your request is on hold')) ?> <span class="pf-badge pf-badge--pending"><?= h(__('On hold')) ?></span></h2>
<?php if ($holdMessage !== ''): ?>
    <p><strong><?= h($holdMessage) ?></strong></p>
<?php endif; ?>
    <p><?= h(__('We received your deletion request and you confirmed it. For legal reasons we have to keep some of your data for now, so nothing has been deleted yet. As soon as the reason no longer applies, your data is deleted irrevocably.')) ?></p>
    <p><?= h(__('If you have questions, please contact us:')) ?> <a href="mailto:<?= h((string) config('mail.contact')) ?>"><?= h((string) config('mail.contact')) ?></a></p>
    <p><a class="pf-btn" href="/portal/dashboard.php"><?= h(__('Back to the dashboard')) ?></a></p>
  </div>

<?php elseif ($mode === 'confirm' || $mode === 'retry'): ?>
  <div class="pf-card pf-card--accent" id="terminate-confirm">
    <h2><?= h(__('Final step: confirm that you want to end the service')) ?></h2>
<?php if ($mode === 'retry'): ?>
    <div class="pf-notice" id="terminate-retry">
      <strong><?= h(__('Your confirmation was received, but the deletion could not be completed.')) ?></strong>
      <?= h(__('Nothing else was deleted. It is safe to try again: enter your password once more.')) ?>
    </div>
<?php endif; ?>
    <div class="pf-notice pf-notice--danger">
      <strong><?= h(__('This cannot be undone.')) ?></strong>
      <?= h(__('If you confirm, the following happens immediately:')) ?>
      <ul class="pf-list">
        <?php foreach ($effects as $effect): ?><li><?= h($effect) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <form class="pf-form" method="post" action="/portal/terminate.php" id="terminate-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="confirm">
<?php if ($token !== ''): ?>
      <input type="hidden" name="token" value="<?= h($token) ?>">
<?php endif; ?>
      <div class="pf-check">
        <input type="checkbox" id="f-confirm" name="confirm" value="1" required<?= portal_invalid($errors, 'confirm') ?>>
        <label for="f-confirm"><?= h(__('I confirm that the service ends, that all my data is deleted irrevocably and that I may no longer use the service.')) ?></label>
      </div>
      <?= portal_err($errors, 'confirm') ?>
      <div class="pf-field">
        <label for="f-password"><?= h(__('Your current password')) ?></label>
        <input type="password" id="f-password" name="password" autocomplete="current-password" required<?= portal_invalid($errors, 'password') ?>>
        <div class="pf-help"><?= h(__('We ask for your password once more to make sure it is really you.')) ?></div>
        <?= portal_err($errors, 'password') ?>
      </div>
      <div class="pf-actions">
        <button class="pf-btn pf-btn--danger" type="submit"><?= h(__('Delete everything and end the service')) ?></button>
        <a class="pf-btn pf-btn--ghost" href="/portal/dashboard.php"><?= h(__('No, keep my service')) ?></a>
      </div>
    </form>
  </div>
  <div class="pf-card" id="terminate-export">
    <h2><?= h(__('Take a copy of your data first')) ?></h2>
    <p class="pf-muted"><?= h(__('After the deletion we cannot give you your data any more. You can still download a copy now (your imprint profiles including private details and all versions, and your requests).')) ?></p>
    <form class="pf-form" method="post" action="/portal/account-export.php">
      <?= csrf_field() ?>
      <div class="pf-actions"><button class="pf-btn" type="submit"><?= h(__('Download my data (JSON)')) ?></button></div>
    </form>
  </div>

<?php else: /* start | requested | invalid_link */ ?>
<?php if ($mode === 'requested'): ?>
  <div class="pf-notice pf-notice--info" id="terminate-requested">
    <strong><?= h(__('Request received: confirmation link mailed.')) ?></strong>
    <?= h(sprintf(__('We sent a link to %s. Open it while you are signed in to confirm.'), $email)) ?>
<?php if ($state['link_valid'] && $state['link_until'] !== null): ?>
    <?= h(sprintf(__('The link is valid until %s.'), fmt_time($state['link_until']))) ?>
<?php else: ?>
    <strong><?= h(__('The link has expired. Please request a new one.')) ?></strong>
<?php endif; ?>
    <?= h(__('Nothing has been deleted yet.')) ?>
  </div>
<?php elseif ($mode === 'invalid_link'): ?>
  <div class="pf-notice" id="terminate-invalid-link">
    <strong><?= h(__('This confirmation link is not valid any more.')) ?></strong>
    <?= h(__('It may have expired or a newer link was sent. Please request a new link below. Nothing has been deleted.')) ?>
  </div>
<?php endif; ?>

  <div class="pf-card pf-card--accent" id="terminate-info">
    <h2><?= h(__('What happens when you end the service')) ?></h2>
    <p><?= h(__('You can end Imprint Protection at any time. This is how it works:')) ?></p>
    <ol class="pf-steps">
      <li><?= h(__('You ask us to end the service here. That creates a deletion request in our system and we e-mail you a confirmation link.')) ?></li>
      <li><?= h(__('You open the link, tick the confirmation and enter your password.')) ?></li>
      <li><?= h(__('Then everything is deleted automatically and irrevocably:')) ?>
        <ul class="pf-list">
          <?php foreach ($effects as $effect): ?><li><?= h($effect) ?></li><?php endforeach; ?>
        </ul>
      </li>
    </ol>
    <p class="pf-muted pf-small"><?= h(__('Only if we are legally required to keep data (for example because of an open claim or a request from an authority), the deletion waits until the reason no longer applies.')) ?></p>

    <h3><?= h(__('Current state')) ?></h3>
<?php if ($mode === 'requested'): ?>
    <p><span class="pf-badge pf-badge--pending"><?= h(__('Requested, waiting for your confirmation')) ?></span>
      <?= h(__('since')) ?> <?= h(fmt_time($state['created_at'])) ?></p>
<?php else: ?>
    <p><span class="pf-badge pf-badge--draft"><?= h(__('Nothing requested')) ?></span></p>
<?php endif; ?>

    <div class="pf-actions">
      <form class="pf-inline-form" method="post" action="/portal/terminate.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="request">
        <button class="pf-btn pf-btn--danger" type="submit"><?= h($mode === 'start' ? __('Request deletion and end the service') : __('Send me a new confirmation link')) ?></button>
      </form>
<?php if ($mode === 'requested'): ?>
      <form class="pf-inline-form" method="post" action="/portal/terminate.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <button class="pf-btn" type="submit"><?= h(__('Withdraw my request')) ?></button>
      </form>
<?php endif; ?>
      <a class="pf-btn pf-btn--ghost" href="/portal/account.php"><?= h(__('Back to my account')) ?></a>
    </div>
  </div>

  <div class="pf-card" id="terminate-export">
    <h2><?= h(__('Take a copy of your data first')) ?></h2>
    <p class="pf-muted"><?= h(__('After the deletion we cannot give you your data any more. You can download a copy now (your imprint profiles including private details and all versions, and your requests). The file does not contain your password or your ID images.')) ?></p>
    <form class="pf-form" method="post" action="/portal/account-export.php">
      <?= csrf_field() ?>
      <div class="pf-actions"><button class="pf-btn" type="submit"><?= h(__('Download my data (JSON)')) ?></button></div>
    </form>
  </div>
<?php endif; ?>
</div>
