<?php
/**
 * @var array<int,array<string,mixed>> $services @var array<string,array<string,string>> $errors @var array<string,array<string,string>> $values
 */
defined('TCD_APP') || exit;

/** Render the editable fields of one service form. */
$renderFields = static function (string $key, array $s, array $err): string {
    $id = 'f-' . $key . '-';
    $e = static function (string $f) use ($err): string {
        return isset($err[$f]) ? '<div class="pf-error-text">' . h($err[$f]) . '</div>' : '';
    };
    $out = '<div class="pf-field"><label for="' . h($id) . 'name">' . h(__('Name')) . '</label><input type="text" id="' . h($id) . 'name" name="name" required maxlength="80" value="' . h($s['name']) . '">' . $e('name') . '</div>';
    $out .= '<div class="pf-field"><label for="' . h($id) . 'summary">' . h(__('Summary (short text on the service overview)')) . '</label><textarea id="' . h($id) . 'summary" name="summary" maxlength="500">' . h($s['summary']) . '</textarea>' . $e('summary') . '</div>';
    $out .= '<div class="pf-field"><label for="' . h($id) . 'details">' . h(__('Details')) . '</label><textarea id="' . h($id) . 'details" name="details" maxlength="4000">' . h($s['details']) . '</textarea>' . $e('details') . '</div>';
    $out .= '<div class="pf-grid pf-grid--3"><div class="pf-field"><label for="' . h($id) . 'availability">' . h(__('Availability')) . '</label><select id="' . h($id) . 'availability" name="availability">';
    foreach (admin_service_availabilities() as $a) {
        $out .= '<option value="' . h($a) . '"' . ($s['availability'] === $a ? ' selected' : '') . '>' . h(status_label($a)) . '</option>';
    }
    $out .= '</select>' . $e('availability') . '</div>';
    $out .= '<div class="pf-field"><label for="' . h($id) . 'sort_order">' . h(__('Sort order')) . '</label><input type="number" id="' . h($id) . 'sort_order" name="sort_order" min="0" max="9999" value="' . h($s['sort_order']) . '">' . $e('sort_order') . '</div>';
    $out .= '<div class="pf-field"><span class="pf-label">' . h(__('Requests')) . '</span><div class="pf-check"><input type="checkbox" id="' . h($id) . 'requestable" name="requestable" value="1"' . (!empty($s['requestable']) ? ' checked' : '') . '><label for="' . h($id) . 'requestable">' . h(__('Visitors can send a request')) . '</label></div></div></div>';
    return $out;
};
?>
<h1><?= h(__('Services')) ?></h1>
<p class="pf-muted"><?= h(__('This catalogue feeds the public website and the customer area. "Hidden" services are not shown anywhere.')) ?></p>

<?php foreach ($services as $svc): $key = (string) $svc['slug'];
    $vals = $values[$key] ?? ['name' => (string) $svc['name'], 'summary' => (string) $svc['summary'], 'details' => (string) $svc['details'],
        'availability' => (string) $svc['availability'], 'requestable' => (string) $svc['requestable'], 'sort_order' => (string) $svc['sort_order']]; ?>
<section class="pf-card" id="svc-<?= h($key) ?>">
  <div class="pf-row pf-row--between"><h2><?= h($svc['name']) ?> <span class="pf-muted pf-title-sub"><?= h($key) ?></span></h2><?= status_badge((string) $svc['availability']) ?></div>
  <form class="pf-form pf-wide-form" method="post" action="/admin/services.php#svc-<?= h($key) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="slug" value="<?= h($key) ?>">
    <?= $renderFields($key, $vals, $errors[$key] ?? []) ?>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Save service')) ?></button></div>
  </form>
</section>
<?php endforeach; ?>

<?php $nv = $values['_new'] ?? ['slug' => '', 'name' => '', 'summary' => '', 'details' => '', 'availability' => 'coming_soon', 'requestable' => '', 'sort_order' => '100'];
      $ne = $errors['_new'] ?? []; ?>
<section class="pf-card pf-card--accent" id="svc-new">
  <h2><?= h(__('Add a service')) ?></h2>
  <form class="pf-form pf-wide-form" method="post" action="/admin/services.php#svc-new">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="pf-field"><label for="f-_new-slug"><?= h(__('Identifier')) ?></label>
      <input type="text" id="f-_new-slug" name="slug" required maxlength="40" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= h($nv['slug']) ?>" spellcheck="false">
      <div class="pf-help"><?= h(__('Lower-case letters, digits and hyphens. It is used in URLs and cannot be changed later.')) ?></div>
      <?php if (!empty($ne['slug'])): ?><div class="pf-error-text"><?= h($ne['slug']) ?></div><?php endif; ?></div>
    <?= $renderFields('_new', $nv, $ne) ?>
    <div class="pf-actions"><button class="pf-btn pf-btn--primary" type="submit"><?= h(__('Add service')) ?></button></div>
  </form>
</section>
