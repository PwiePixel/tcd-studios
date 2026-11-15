<?php
/**
 * @var array<string,mixed> $req @var array<string,mixed>|null $service @var array<int,array{0:string,1:string}> $payload @var array<string,mixed>|null $requester
 */
defined('TCD_APP') || exit;
$self = '/admin/request.php?id=' . (int) $req['id'];
$slug = (string) $req['service_slug'];
?>
<p><a href="/admin/requests.php">&larr; <?= h(__('Requests')) ?></a></p>
<div class="pf-row pf-row--between">
  <h1><?= h(__('Request')) ?> #<?= (int) $req['id'] ?></h1>
  <div><?= status_badge((string) $req['status']) ?></div>
</div>

<?php if ($slug === 'shops'): ?>
  <div class="pf-notice pf-notice--info"><strong><?= h(__('Shop partnership request.')) ?></strong>
    <?= h(__('Shops are available on request and in limited numbers only. Check the shop and the payment/tax requirements before accepting.')) ?></div>
<?php endif; ?>
<?php if ($slug === 'account-deletion'): ?>
  <div class="pf-notice"><strong><?= h(__('Account deletion request.')) ?></strong>
    <?= h(__('Nothing is deleted automatically. Process this request manually according to privacy law: verify the identity of the requester, answer within one month, and delete or anonymise the data unless a legal retention duty applies.')) ?></div>
<?php endif; ?>

<div class="pf-grid pf-grid--2">
  <section class="pf-card">
    <h2><?= h(__('Request')) ?></h2>
    <dl class="pf-kv">
      <dt><?= h(__('Service')) ?></dt><dd><?= h($service ? (string) $service['name'] : $slug) ?> <span class="pf-muted">(<?= h($slug) ?>)</span></dd>
      <dt><?= h(__('Name')) ?></dt><dd><?= h((string) $req['name']) ?></dd>
      <dt><?= h(__('E-mail')) ?></dt><dd><?= h((string) $req['email']) ?></dd>
      <dt><?= h(__('Account')) ?></dt><dd><?= $requester ? '<a href="/admin/customer.php?id=' . (int) $requester['id'] . '">' . h($requester['email']) . '</a>' : '<span class="pf-muted">' . h(__('no account (public form)')) . '</span>' ?></dd>
      <dt><?= h(__('Received')) ?></dt><dd><?= h(fmt_time((int) $req['created_at'])) ?></dd>
      <dt><?= h(__('Updated')) ?></dt><dd><?= h(fmt_time((int) $req['updated_at'])) ?></dd>
    </dl>
  </section>
  <section class="pf-card">
    <h2><?= h(__('Submitted details')) ?></h2>
    <?php if (!$payload): ?><p class="pf-muted"><?= h(__('No further details.')) ?></p><?php else: ?>
    <dl class="pf-kv">
      <?php foreach ($payload as [$k, $v]): ?><dt><?= h($k) ?></dt><dd class="pf-pre"><?= h($v) ?></dd><?php endforeach; ?>
    </dl>
    <?php endif; ?>
  </section>
</div>

<section class="pf-card pf-card--accent">
  <h2><?= h(__('Handle request')) ?></h2>
  <form class="pf-form pf-wide-form" method="post" action="<?= h($self) ?>">
    <?= csrf_field() ?>
    <div class="pf-field"><label for="status"><?= h(__('Status')) ?></label>
      <select id="status" name="status">
        <?php foreach (admin_request_statuses() as $s): ?><option value="<?= h($s) ?>"<?= $req['status'] === $s ? ' selected' : '' ?>><?= h(status_label($s)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="pf-field"><label for="note"><?= h(__('Internal note')) ?></label>
      <textarea id="note" name="note" maxlength="3000"><?= h((string) $req['admin_note']) ?></textarea>
      <div class="pf-help"><?= h(__('Only visible to admins. It is not part of the status e-mail.')) ?></div></div>
    <div class="pf-check"><input type="checkbox" id="send_mail" name="send_mail" value="1"><label for="send_mail"><?= h(__('Send a short status e-mail to the requester')) ?></label></div>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Save')) ?></button></div>
  </form>
</section>
