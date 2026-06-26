<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function db_table_exists(string $tableName): bool
{
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $tableName]);

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function audit_log(string $entityType, string $entityId, string $action, ?array $before = null, ?array $after = null, string $comment = ''): void
{
    try {
        if (!db_table_exists('trial_audit_logs')) {
            return;
        }

        $user = admin_user();
        $stmt = db()->prepare(
            'INSERT INTO trial_audit_logs (
                actor_id, actor_name, entity_type, entity_id, action, before_json, after_json, comment
            ) VALUES (
                :actor_id, :actor_name, :entity_type, :entity_id, :action, :before_json, :after_json, :comment
            )'
        );
        $stmt->execute([
            'actor_id' => $user ? (int)$user['id'] : null,
            'actor_name' => $user ? (string)($user['display_name'] !== '' ? $user['display_name'] : $user['username']) : null,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== '' ? $entityId : null,
            'action' => $action,
            'before_json' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_json' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'comment' => $comment !== '' ? $comment : null,
        ]);
    } catch (Throwable $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
