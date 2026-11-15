<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Render app/views/<view>.php inside the portal/admin layout.
 *
 * Views receive $vars as local variables plus $user (current user or null) and
 * helper access to h(), csrf_field(), url helpers. The layout is app/views/layout-portal.php
 * and receives $title, $area ('portal'|'admin'), $user, $content, $flash.
 *
 * @param array<string,mixed> $vars
 */
function render_view(string $view, array $vars = [], string $layout = 'layout-portal'): void
{
    if (!preg_match('~^[a-z0-9/_-]+$~', $view) || !preg_match('~^[a-z0-9_-]+$~', $layout)) {
        throw new InvalidArgumentException('bad view name');
    }
    $user = auth_current_user();
    $area = current_area();
    $title = isset($vars['title']) ? (string) $vars['title'] : '';
    $content = (static function (string $__file, array $__vars, ?array $user): string {
        extract($__vars, EXTR_SKIP);
        ob_start();
        require $__file;
        return (string) ob_get_clean();
    })(__DIR__ . '/../views/' . $view . '.php', $vars, $user);

    $flash = flash_pop();
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    require __DIR__ . '/../views/' . $layout . '.php';
}

/** Render a template and return it as string (no layout), e.g. for mails or fragments. @param array<string,mixed> $vars */
function render_partial(string $view, array $vars = []): string
{
    if (!preg_match('~^[a-z0-9/_-]+$~', $view)) {
        throw new InvalidArgumentException('bad view name');
    }
    return (static function (string $__file, array $__vars): string {
        extract($__vars, EXTR_SKIP);
        ob_start();
        require $__file;
        return (string) ob_get_clean();
    })(__DIR__ . '/../views/' . $view . '.php', $vars);
}

/** Human label for a profile/revision/request status (used in badges). */
function status_label(string $status): string
{
    $labels = [
        'draft' => 'Draft', 'pending' => 'In review', 'live' => 'Live', 'suspended' => 'Suspended', 'withdrawn' => 'Withdrawn',
        'submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'superseded' => 'Superseded',
        'new' => 'New', 'in_progress' => 'In progress', 'accepted' => 'Accepted', 'declined' => 'Declined', 'done' => 'Done',
        'active' => 'Active', 'disabled' => 'Disabled',
        'on_request' => 'On request', 'coming_soon' => 'Coming soon', 'hidden' => 'Hidden',
    ];
    return __($labels[$status] ?? ucfirst($status));
}

/** CSS badge modifier for a status. */
function status_badge_class(string $status): string
{
    $map = [
        'live' => 'live', 'approved' => 'live', 'active' => 'live', 'accepted' => 'live', 'done' => 'live',
        'pending' => 'pending', 'submitted' => 'pending', 'in_progress' => 'pending', 'on_request' => 'info', 'new' => 'new',
        'draft' => 'draft', 'superseded' => 'draft', 'withdrawn' => 'draft', 'coming_soon' => 'draft', 'hidden' => 'draft',
        'rejected' => 'rejected', 'suspended' => 'suspended', 'disabled' => 'suspended', 'declined' => 'rejected',
    ];
    return 'pf-badge pf-badge--' . ($map[$status] ?? 'draft');
}

/** <span class="pf-badge …">Label</span> */
function status_badge(string $status): string
{
    return '<span class="' . h(status_badge_class($status)) . '">' . h(status_label($status)) . '</span>';
}
