<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

const AUDIT_PER_PAGE = 50;

$fAction = get_str('action', 60);
$fActor = get_str('actor', 100);
$fTarget = get_str('target', 100);

$where = [];
$params = [];
if ($fAction !== '') {
    $where[] = 'action LIKE ? ESCAPE \'\\\'';
    $params[] = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $fAction) . '%';
}
if ($fActor !== '') {
    $where[] = '(actor_label LIKE ? ESCAPE \'\\\'' . (ctype_digit($fActor) ? ' OR actor_id = ?' : '') . ')';
    $params[] = admin_like($fActor);
    if (ctype_digit($fActor)) {
        $params[] = (int) $fActor;
    }
}
if ($fTarget !== '') {
    if (strpos($fTarget, ':') !== false) {
        [$tType, $tId] = explode(':', $fTarget, 2);
        $where[] = 'target_type = ? AND target_id = ?';
        array_push($params, $tType, $tId);
    } else {
        $where[] = '(target_type = ? OR target_id = ?)';
        array_push($params, $fTarget, $fTarget);
    }
}
$cond = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total = (int) (db_one('SELECT COUNT(*) AS n FROM audit_log' . $cond, $params)['n'] ?? 0);
$pager = admin_pager($total, AUDIT_PER_PAGE);
$rows = db_all(
    'SELECT * FROM audit_log' . $cond . ' ORDER BY id DESC LIMIT ' . (int) $pager['per'] . ' OFFSET ' . (int) $pager['offset'],
    $params
);

render_view('admin/audit', [
    'title' => __('Audit log'),
    'rows' => $rows,
    'pager' => $pager,
    'filters' => ['action' => $fAction, 'actor' => $fActor, 'target' => $fTarget],
]);
