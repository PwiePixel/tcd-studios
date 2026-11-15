<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Record an action in the audit log.
 *
 * @param array<string,mixed> $meta  small, non-secret details (never passwords/tokens/private data)
 * @param int|null $actorId          defaults to the logged-in user
 */
function audit(string $action, ?string $targetType = null, $targetId = null, array $meta = [], ?int $actorId = null): void
{
    try {
        $label = '';
        if ($actorId === null && !empty($_SESSION['uid'])) {
            $actorId = (int) $_SESSION['uid'];
        }
        if ($actorId !== null) {
            $u = db_one('SELECT email, role FROM users WHERE id = ?', [$actorId]);
            $label = $u ? $u['role'] . ':' . $u['email'] : 'user#' . $actorId;
        } else {
            $label = 'system';
        }
        db_exec(
            'INSERT INTO audit_log (at, actor_id, actor_label, action, target_type, target_id, meta_json, ip) VALUES (?,?,?,?,?,?,?,?)',
            [now(), $actorId, $label, $action, $targetType, $targetId === null ? null : (string) $targetId, json_str($meta), client_ip()]
        );
    } catch (Throwable $e) {
        error_log('audit failed: ' . $e->getMessage());
    }
}
