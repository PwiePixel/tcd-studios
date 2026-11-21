<?php
/**
 * @var array<int,array<string,mixed>> $packages @var array<int,int> $usage @var int $withoutPackage @var array<string,mixed>|null $defaultPackage
 * @var array<string,array<string,string>> $errors @var array<string,array<string,string>> $values @var string $note @var int $globalLimit
 */
defined('TCD_APP') || exit;

/** Editable fields of one package form. */
$renderFields = static function (string $key, array $v, array $err, bool $isNew): string {
    $id = 'f-' . $key . '-';
    $e = static function (string $f) use ($err): string {
        return isset($err[$f]) ? '<div class="pf-error-text" role="alert">' . h($err[$f]) . '</div>' : '';
    };
    $inv = static function (string $f) use ($err): string {
        return isset($err[$f]) ? ' aria-invalid="true"' : '';
    };
    $text = static function (string $f, string $label, int $max, string $help = '', string $extra = '') use ($id, $v, $e, $inv): string {
        return '<div class="pf-field"><label for="' . h($id . $f) . '">' . h($label) . '</label>'
            . '<input type="text" id="' . h($id . $f) . '" name="' . h($f) . '" maxlength="' . $max . '" value="' . h($v[$f]) . '"' . $extra . $inv($f) . '>'
            . ($help !== '' ? '<div class="pf-help">' . h($help) . '</div>' : '') . $e($f) . '</div>';
    };
    $out = '<div class="pf-grid pf-grid--2">';
    $out .= $text('name', __('Name'), 60, '', ' required');
    $out .= $text('slug', __('Identifier'), 40, $isNew
        ? __('Lower-case letters, digits and hyphens. Used in the links of the public page (register.php?package=…).')
        : __('Changing it breaks links that contain the old identifier.'), ' required spellcheck="false" pattern="[a-z0-9]+(-[a-z0-9]+)*"');
    $out .= '</div>';
    $out .= $text('tagline', __('Tagline (optional)'), 200);
    $out .= '<div class="pf-grid pf-grid--2">';
    $out .= $text('price_year', __('Price per month, billed yearly (EUR)'), 12, __('E.g. 3.90 or 3,90. Leave empty if there is no yearly price.'), ' inputmode="decimal"');
    $out .= $text('price_month', __('Price per month, billed monthly (EUR)'), 12, __('E.g. 4.90 or 4,90. Leave empty if there is no monthly price.'), ' inputmode="decimal"');
    $out .= '</div>';
    $out .= '<div class="pf-field"><label for="' . h($id) . 'features">' . h(__('Features (one per line)')) . '</label><textarea id="' . h($id) . 'features" name="features" rows="6"' . $inv('features') . '>' . h($v['features']) . '</textarea>'
        . '<div class="pf-help">' . h(sprintf(__('At most %d lines with up to 200 characters each.'), PACKAGE_MAX_FEATURES)) . '</div>' . $e('features') . '</div>';
    $out .= '<div class="pf-field"><label for="' . h($id) . 'extras">' . h(__('Additional, usage-based fees (one per line)')) . '</label><textarea id="' . h($id) . 'extras" name="extras" rows="3"' . $inv('extras') . '>' . h($v['extras']) . '</textarea>'
        . '<div class="pf-help">' . h(__('Shown under "Additional, depending on use". Leave empty if there are none.')) . '</div>' . $e('extras') . '</div>';
    $out .= '<div class="pf-grid pf-grid--3">';
    $out .= $text('badge', __('Badge (optional)'), 40, __('E.g. "Most popular". Empty = no badge.'));
    $out .= '<div class="pf-field"><label for="' . h($id) . 'max_profiles">' . h(__('Imprint profiles included')) . '</label><input type="number" id="' . h($id) . 'max_profiles" name="max_profiles" min="1" max="100" value="' . h($v['max_profiles']) . '" required' . $inv('max_profiles') . '>' . $e('max_profiles') . '</div>';
    $out .= '<div class="pf-field"><label for="' . h($id) . 'sort_order">' . h(__('Sort order')) . '</label><input type="number" id="' . h($id) . 'sort_order" name="sort_order" min="0" max="9999" value="' . h($v['sort_order']) . '" required' . $inv('sort_order') . '>' . $e('sort_order') . '</div>';
    $out .= '</div>';
    $out .= '<div class="pf-check"><input type="checkbox" id="' . h($id) . 'active" name="active" value="1"' . ($v['active'] !== '' ? ' checked' : '') . '><label for="' . h($id) . 'active">' . h(__('Active: shown on the website and choosable for new imprint profiles')) . '</label></div>';
    return $out;
};
?>
<h1><?= h(__('Packages')) ?></h1>
<p class="pf-muted"><?= h(__('The Imprint Protection packages are shown as pricing cards on the public page /imprint-protection/. Customers choose one for every imprint profile.')) ?></p>
<div class="pf-notice pf-notice--info">
  <?= h(sprintf(__('Profile limit: a customer may own at most as many imprint profiles as the highest package among his profiles includes, and never more than %d (setting "max_profiles_per_user").'), $globalLimit)) ?>
<?php if ($withoutPackage > 0): ?>
  <?= h(sprintf(__('%1$d imprint profiles have no package yet; they count as "%2$s" (the first active package).'), $withoutPackage, $defaultPackage !== null ? (string) $defaultPackage['name'] : '–')) ?>
<?php endif; ?>
</div>

<section class="pf-card">
  <h2><?= h(__('Overview')) ?></h2>
<?php if ($packages === []): ?>
  <p class="pf-muted"><?= h(__('There are no packages. Without an active package, customers create imprint profiles without one and the public page shows no pricing cards.')) ?></p>
<?php else: ?>
  <div class="pf-table-wrap"><table class="pf-table" id="packages-table">
    <thead><tr><th><?= h(__('Package')) ?></th><th><?= h(__('Status')) ?></th><th><?= h(__('Prices')) ?></th><th><?= h(__('Profiles included')) ?></th><th><?= h(__('Used by')) ?></th><th><?= h(__('Sort order')) ?></th><th></th></tr></thead>
    <tbody>
<?php foreach ($packages as $p): ?>
      <tr>
        <td><strong><?= h($p['name']) ?></strong> <span class="pf-muted pf-small"><?= h($p['slug']) ?></span><?php if ($p['badge'] !== ''): ?> <span class="pf-badge pf-badge--info"><?= h($p['badge']) ?></span><?php endif; ?></td>
        <td><?= $p['active'] === 1 ? '<span class="pf-badge pf-badge--live">' . h(__('Active')) . '</span>' : '<span class="pf-badge pf-badge--draft">' . h(__('Inactive')) . '</span>' ?></td>
        <td><?= h(package_price_short($p)) ?></td>
        <td class="pf-num"><?= (int) $p['max_profiles'] ?></td>
        <td class="pf-num"><?= (int) ($usage[$p['id']] ?? 0) ?></td>
        <td class="pf-num"><?= (int) $p['sort_order'] ?></td>
        <td class="pf-num"><a class="pf-btn pf-btn--small" href="#pkg-<?= (int) $p['id'] ?>"><?= h(__('Edit')) ?></a></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>

<section class="pf-card" id="pkg-note">
  <h2><?= h(__('Note under the pricing cards')) ?></h2>
  <form class="pf-form pf-wide-form" method="post" action="/admin/packages.php#pkg-note">
    <?= csrf_field() ?><input type="hidden" name="action" value="note">
    <div class="pf-field"><label for="f-note"><?= h(__('Note')) ?></label>
      <textarea id="f-note" name="note" rows="3" maxlength="1000"><?= h($note) ?></textarea>
      <div class="pf-help"><?= h(__('Plain text, shown on /imprint-protection/ under the cards and in the customer area. It has to match the Terms and Conditions (AGB § 6).')) ?></div>
      <?php if (!empty($errors['_note']['note'])): ?><div class="pf-error-text" role="alert"><?= h($errors['_note']['note']) ?></div><?php endif; ?>
    </div>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Save note')) ?></button></div>
  </form>
</section>

<?php foreach ($packages as $p): $key = (string) $p['id']; $used = (int) ($usage[$p['id']] ?? 0);
    $vals = $values[$key] ?? admin_package_values($p); ?>
<section class="pf-card" id="pkg-<?= (int) $p['id'] ?>">
  <div class="pf-row pf-row--between">
    <h2><?= h($p['name']) ?> <span class="pf-muted pf-title-sub"><?= h($p['slug']) ?></span></h2>
    <?= $p['active'] === 1 ? '<span class="pf-badge pf-badge--live">' . h(__('Active')) . '</span>' : '<span class="pf-badge pf-badge--draft">' . h(__('Inactive')) . '</span>' ?>
  </div>
  <p class="pf-muted pf-small"><?= h(sprintf(__('Used by %d imprint profiles.'), $used)) ?></p>
  <form class="pf-form pf-wide-form" method="post" action="/admin/packages.php#pkg-<?= (int) $p['id'] ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <?= $renderFields($key, $vals, $errors[$key] ?? [], false) ?>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Save package')) ?></button></div>
  </form>
  <div class="pf-actions">
    <form method="post" action="/admin/packages.php">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
<?php if ($p['active'] === 1): ?>
      <input type="hidden" name="action" value="deactivate"><button class="pf-btn" type="submit"><?= h(__('Deactivate')) ?></button>
<?php else: ?>
      <input type="hidden" name="action" value="activate"><button class="pf-btn" type="submit"><?= h(__('Activate')) ?></button>
<?php endif; ?>
    </form>
<?php if ($used === 0): ?>
    <form method="post" action="/admin/packages.php">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="delete">
      <button class="pf-btn pf-btn--danger" type="submit"><?= h(__('Delete package')) ?></button>
    </form>
<?php else: ?>
    <span class="pf-muted pf-small"><?= h(__('This package is used by imprint profiles and cannot be deleted. Deactivate it instead: existing profiles keep it, new customers cannot choose it.')) ?></span>
<?php endif; ?>
  </div>
</section>
<?php endforeach; ?>

<?php $nv = $values['_new'] ?? ['slug' => '', 'name' => '', 'tagline' => '', 'price_month' => '', 'price_year' => '', 'features' => '', 'extras' => '',
        'badge' => '', 'max_profiles' => '1', 'sort_order' => '100', 'active' => '1']; ?>
<section class="pf-card pf-card--accent" id="pkg-new">
  <h2><?= h(__('Add a package')) ?></h2>
  <form class="pf-form pf-wide-form" method="post" action="/admin/packages.php#pkg-new">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <?= $renderFields('_new', $nv, $errors['_new'] ?? [], true) ?>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Add package')) ?></button></div>
  </form>
</section>
