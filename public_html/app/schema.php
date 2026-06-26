<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function db_table_exists_cached(string $tableName): bool
{
    static $cache = [];
    $key = 'table:' . $tableName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $tableName]);
        return $cache[$key] = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function db_column_exists_cached(string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = 'column:' . $tableName . '.' . $columnName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);
        return $cache[$key] = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}
