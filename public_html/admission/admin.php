<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';

$basePath = defined('APP_BASE_PATH') ? rtrim((string)APP_BASE_PATH, '/') : '';
$query = $_SERVER['QUERY_STRING'] ?? '';
$location = $basePath . '/admin/admissions.php' . ($query !== '' ? '?' . $query : '');

header('Location: ' . $location, true, 302);
exit;
