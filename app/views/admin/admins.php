<?php
/**
 * @var array<int,array<string,mixed>> $admins @var array<string,string> $errors @var array<string,string> $values @var int $self @var int $activeCount
 */
defined('TCD_APP') || exit;
?>
<h1><?= h(__('Admins')) ?></h1>
<?= render_partial('admin/_subnav', ['current' => 'admins.php']) ?>

<section class="pf-card">
  <h2><?= h(__('Admin accounts')) ?></h2>
  <div class="pf-table-wrap"><table class="pf-table">
    <thead><tr><th><?= h(__('Name')) ?></th><th><?= h(__('E-mail')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('2FA')) ?></th><th><?= h(__('Last sign-in')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($admins as $a): $isSelf = (int) $a['id'] === $self; ?>
      <tr>
        <td><?= h($a['name']) ?><?= $isSelf ? ' <span class="pf-badge pf-badge--info">' . h(__('you')) . '</span>' : '' ?></td>
        <td><?= h($a['email']) ?></td>
        <td><?= status_badge((string) $a['status']) ?></td>
        <td><?= !empty($a['totp_enabled']) ? '<span class="pf-badge pf-badge--ok">' . h(__('on')) . '</span>' : '<span class="pf-badge pf-badge--draft">' . h(__('off')) . '</span>' ?></td>
        <td><?= h(fmt_time($a['last_login_at'] !== null ? (int) $a['last_login_at'] : null)) ?></td>
        <td class="pf-num">
          <?php if (!$isSelf): ?>
            <?php if ($a['status'] === 'disabled'): ?>
              <form class="pf-inline-form" method="post" action="/admin/admins.php"><?= csrf_field() ?><input type="hidden" name="action" value="enable"><input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                <button class="pf-btn pf-btn--small" type="submit"><?= h(__('Enable')) ?></button></form>
            <?php else: ?>
              <form class="pf-inline-form" method="post" action="/admin/admins.php"><?= csrf_field() ?><input type="hidden" name="action" value="disable"><input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                <button class="pf-btn pf-btn--small pf-btn--danger" type="submit"><?= h(__('Disable')) ?></button></form>
            <?php endif; ?>
            <?php if (!empty($a['totp_enabled'])): ?>
              <form class="pf-inline-form" method="post" action="/admin/admins.php"><?= csrf_field() ?><input type="hidden" name="action" value="reset_2fa"><input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                <button class="pf-btn pf-btn--small" type="submit"><?= h(__('Reset 2FA')) ?></button></form>
            <?php endif; ?>
          <?php else: ?><span class="pf-muted pf-small"><?= h(__('see My account')) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="pf-card">
  <h2><?= h(__('Create another admin')) ?></h2>
  <p class="pf-muted"><?= h(__('You choose the initial password and pass it on to the new admin yourself (never by plain e-mail). The new admin should change it under "My account" and set up 2FA right after the first sign-in.')) ?></p>
  <?php if (!empty($errors['form'])): ?><div class="pf-flash pf-flash--error" role="alert"><?= h($errors['form']) ?></div><?php endif; ?>
  <form class="pf-form pf-wide-form" method="post" action="/admin/admins.php" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="pf-grid pf-grid--2">
      <div class="pf-field"><label for="name"><?= h(__('Name')) ?></label><input type="text" id="name" name="name" required maxlength="80" value="<?= h($values['name']) ?>">
        <?php if (!empty($errors['name'])): ?><div class="pf-error-text"><?= h($errors['name']) ?></div><?php endif; ?></div>
      <div class="pf-field"><label for="email"><?= h(__('E-mail')) ?></label><input type="email" id="email" name="email" required maxlength="254" value="<?= h($values['email']) ?>">
        <?php if (!empty($errors['email'])): ?><div class="pf-error-text"><?= h($errors['email']) ?></div><?php endif; ?></div>
    </div>
    <div class="pf-field"><label for="password"><?= h(__('Initial password')) ?></label><input type="password" id="password" name="password" required autocomplete="new-password">
      <div class="pf-help"><?= h(sprintf(__('At least %d characters.'), (int) config('password_min_length', 10))) ?></div>
      <?php if (!empty($errors['password'])): ?><div class="pf-error-text"><?= h($errors['password']) ?></div><?php endif; ?></div>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Create admin')) ?></button></div>
  </form>
</section>
