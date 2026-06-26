<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

$requestedFile = basename((string)($_GET['file'] ?? ''));
$formSession = $_SESSION['admission_form'] ?? null;
$photo = is_array($formSession) ? ($formSession['photo'] ?? null) : null;

if ($requestedFile === '' || !is_array($photo) || (string)($photo['filename'] ?? '') !== $requestedFile) {
    http_response_code(404);
    exit;
}

$createdAt = (int)($formSession['created_at'] ?? 0);
if ($createdAt <= 0 || (time() - $createdAt) > 3600) {
    http_response_code(410);
    exit;
}

$path = (string)($photo['path'] ?? '');
$tmpDir = realpath((string)($config['photo']['tmp_dir'] ?? ''));
$realPath = $path !== '' ? realpath($path) : false;

if ($tmpDir === false || $realPath === false || !is_file($realPath)) {
    http_response_code(404);
    exit;
}

$tmpPrefix = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strncmp($realPath, $tmpPrefix, strlen($tmpPrefix)) !== 0) {
    http_response_code(403);
    exit;
}

$mime = (string)($photo['mime'] ?? '');
$allowed = $config['photo']['allowed_mime'] ?? [];
if (!in_array($mime, is_array($allowed) ? $allowed : [], true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($realPath));
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
readfile($realPath);
