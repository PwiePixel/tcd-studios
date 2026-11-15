<?php
/** @var string $title @var string $area @var array|null $user @var string $content @var array $flash */
defined('TCD_APP') || exit;
$isAdmin = $area === 'admin';
$base = $isAdmin ? '/admin/' : '/portal/';
$nav = $isAdmin
    ? [
        ['index.php', __('Dashboard')], ['reviews.php', __('Reviews')], ['profiles.php', __('Imprints')],
        ['customers.php', __('Customers')], ['requests.php', __('Requests')], ['services.php', __('Services')], ['vault.php', __('ID vault')],
        ['audit.php', __('Audit log')], ['settings.php', __('Settings')],
    ]
    : [['dashboard.php', __('Dashboard')], ['services.php', __('Services')], ['account.php', __('Account')]];
$script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= h($title !== '' ? $title . ' – ' : '') ?><?= h($isAdmin ? __('Admin') : __('Customer area')) ?> · TCD-Studio/Agency</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/png" sizes="32x32" href="/images/32x32/16356138/TCD-Studio-Logoohneschrift-gross-JT70FmUTbj_ha7UTW9FF7w-wK-10hrhv1epRSxcDGYMCA.png">
  <link rel="stylesheet" href="/fonts/poppins.css">
  <link rel="stylesheet" href="/css/portal.css">
<?php $areaCss = $isAdmin ? 'admin.css' : 'customer.css'; if (is_file(TCD_ROOT . '/css/' . $areaCss)): ?>  <link rel="stylesheet" href="/css/<?= h($areaCss) ?>">
<?php endif; ?></head>
<body class="pf-body<?= $isAdmin ? ' pf-body--admin' : '' ?>">
<header class="pf-header">
  <div class="pf-container pf-header-inner">
    <a class="pf-logo" href="<?= $isAdmin ? '/admin/' : '/portal/' ?>"><img src="/images/218/16332534/TCD-Studio-LogoohneIcon-wagerecht-x0y5LHULFifqLQb0SZXRUw.png" alt="TCD-Studio/Agency" width="160"></a>
    <span class="pf-area"><?= h($isAdmin ? __('Admin panel') : __('Customer area')) ?></span>
    <?php if ($user): ?>
    <nav class="pf-nav" aria-label="<?= h(__('Main navigation')) ?>">
      <?php foreach ($nav as [$file, $label]): ?>
        <a href="<?= h($base . $file) ?>"<?= $script === $file ? ' class="is-active" aria-current="page"' : '' ?>><?= h($label) ?></a>
      <?php endforeach; ?>
      <form class="pf-logout" method="post" action="<?= h($base) ?>logout.php">
        <?= csrf_field() ?>
        <button class="pf-nav-button" type="submit"><?= h(__('Sign out')) ?> (<?= h($user['email']) ?>)</button>
      </form>
    </nav>
    <?php endif; ?>
  </div>
</header>
<main class="pf-container pf-main" id="main-content">
  <?php foreach ($flash as [$type, $message]): ?>
    <div class="pf-flash pf-flash--<?= h($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= h($message) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="pf-footer">
  <div class="pf-container">
    <a href="/">tcd-studio.de</a> · <a href="/imprint-protection/"><?= h(__('Imprint Protection')) ?></a> ·
    <a href="/legal-notice/"><?= h(__('Legal Notice')) ?></a> · <a href="/privacy/"><?= h(__('Privacy Policy')) ?></a> · <a href="/agb/"><?= h(__('Terms (AGB)')) ?></a>
    · <a href="mailto:<?= h((string) config('mail.contact')) ?>"><?= h((string) config('mail.contact')) ?></a>
  </div>
</footer>
</body>
</html>
