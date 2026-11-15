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

/**
 * Remove everything that identifies a profile from the audit log (details, IP) - used when a profile is deleted or its
 * owner is erased. The rows stay as a record that an action happened.
 */
function audit_scrub_profile(int $profileId, string $slug = '', ?int $ownerId = null): void
{
    $revisionIds = array_map(static function ($r) { return (string) $r['id']; }, db_all('SELECT id FROM revisions WHERE profile_id = ?', [$profileId]));
    $fileIds = array_map(static function ($r) { return (string) $r['id']; }, db_all('SELECT f.id FROM identity_files f JOIN identity_requests r ON r.id = f.request_id WHERE r.profile_id = ?', [$profileId]));
    foreach (['profile' => [(string) $profileId], 'revision' => $revisionIds, 'identity_file' => $fileIds] as $type => $ids) {
        foreach ($ids as $tid) {
            db_exec('UPDATE audit_log SET target_id = NULL, meta_json = \'{}\', ip = \'\' WHERE target_type = ? AND target_id = ?', [$type, $tid]);
        }
    }
    if ($slug !== '') {
        $like = '%"slug":' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], json_encode($slug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '%';
        db_exec('UPDATE audit_log SET meta_json = \'{}\' WHERE meta_json LIKE ? ESCAPE \'\\\'', [$like]);
    }
    if ($ownerId !== null) {
        db_exec('UPDATE audit_log SET meta_json = \'{}\' WHERE meta_json LIKE ?', ['%"owner_user":' . $ownerId . '%']);
    }
}
