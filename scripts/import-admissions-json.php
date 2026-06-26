<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
$config = require $root . '/public_html/admission/inc/config.php';
require_once $root . '/public_html/admission/inc/functions.php';

$commit = in_array('--commit', $argv, true);
$sourceArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--source=')) {
        $sourceArg = substr($arg, strlen('--source='));
    }
}

$source = $sourceArg ?: admission_storage_file($config);
if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Legacy JSON not found: {$source}\n");
    exit(1);
}

$decoded = json_decode((string)file_get_contents($source), true);
if (!is_array($decoded)) {
    fwrite(STDERR, "Legacy JSON is invalid.\n");
    exit(1);
}

$records = array_values(array_filter($decoded, 'is_array'));
echo ($commit ? 'IMPORT' : 'DRY-RUN') . " legacy admissions\n";
echo "Source: {$source}\n";
echo "Records: " . count($records) . "\n";

$imported = 0;
foreach ($records as $record) {
    $id = (string)($record['id'] ?? '');
    if ($id === '') {
        echo "- skip: missing id\n";
        continue;
    }

    echo "- {$id}";
    if (!$commit) {
        echo " dry-run\n";
        continue;
    }

    admission_save_record_to_db($record, 'legacy-json-' . $id);
    $imported++;
    echo " imported\n";
}

echo $commit ? "Imported: {$imported}\n" : "No changes written. Add --commit to import.\n";
