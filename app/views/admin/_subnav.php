<?php
/** Secondary navigation for pages that are not in the main menu. @var string $current */
defined('TCD_APP') || exit;
$items = [
    'settings.php' => __('Settings'), 'vault.php' => __('Identity vault'), 'admins.php' => __('Admins'), 'account.php' => __('My account'),
];
$current = isset($current) ? (string) $current : '';
?>
<nav class="pf-subnav" aria-label="<?= h(__('Administration')) ?>">
  <?php foreach ($items as $file => $label): ?>
    <a href="/admin/<?= h($file) ?>"<?= $current === $file ? ' class="is-active" aria-current="page"' : '' ?>><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>
