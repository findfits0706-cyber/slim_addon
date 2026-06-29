<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../admission/inc/repository.php';

require_admin();

$admissionConfig = require __DIR__ . '/../admission/inc/config.php';
$source = (string)($admissionConfig['admin']['storage_file'] ?? (__DIR__ . '/../admission/tmp/admissions.json'));
$errors = [];
$notice = '';
$result = [
    'records' => 0,
    'imported' => 0,
    'skipped' => 0,
    'ids' => [],
];

function admission_import_count_db_rows(): ?int
{
    if (!admission_repository_ready()) {
        return null;
    }

    return (int)db()->query('SELECT COUNT(*) FROM admissions')->fetchColumn();
}

function admission_import_legacy_json(string $source, bool $commit): array
{
    if ($source === '' || !is_file($source)) {
        throw new RuntimeException('Legacy admissions JSON file was not found.');
    }

    $decoded = json_decode((string)file_get_contents($source), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Legacy admissions JSON is invalid.');
    }

    $records = array_values(array_filter($decoded, 'is_array'));
    $result = [
        'records' => count($records),
        'imported' => 0,
        'skipped' => 0,
        'ids' => [],
    ];

    foreach ($records as $record) {
        $id = (string)($record['id'] ?? '');
        if ($id === '') {
            $result['skipped']++;
            continue;
        }

        $result['ids'][] = $id;
        if ($commit) {
            admission_save_record_to_db($record, 'legacy-json-' . $id);
            $result['imported']++;
        }
    }

    return $result;
}

$dbCount = null;
try {
    $dbCount = admission_import_count_db_rows();
    if (is_file($source)) {
        $result = admission_import_legacy_json($source, false);
    }
} catch (Throwable $e) {
    $errors[] = APP_DEBUG ? $e->getMessage() : 'Legacy admission import check failed.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token. Please reload and try again.';
    } else {
        try {
            $result = admission_import_legacy_json($source, true);
            $dbCount = admission_import_count_db_rows();
            $notice = 'Imported legacy admissions into MySQL.';
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Legacy admission import failed.';
        }
    }
}

render_admin_header('Admission JSON import');
?>
<section class="admin-card">
  <div class="section-row">
    <div>
      <h2>Admission JSON import</h2>
      <p class="admin-note">Import existing legacy admission records into the MySQL admissions table used by the Edge extension.</p>
    </div>
    <a class="button-link button-link--muted" href="<?= h(base_path('/admin/admissions.php')) ?>">Back to admissions</a>
  </div>

  <?php if ($notice !== ''): ?>
    <div class="flash flash--success"><?= h($notice) ?></div>
  <?php endif; ?>

  <?php if ($errors !== []): ?>
    <div class="error" role="alert">
      <strong>Could not import admissions.</strong>
      <ul class="error-list">
        <?php foreach ($errors as $error): ?>
          <li><?= h((string)$error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <dl class="admin-definition-list">
    <dt>Legacy JSON</dt>
    <dd><?= h($source) ?></dd>
    <dt>Legacy records</dt>
    <dd><?= h((string)$result['records']) ?></dd>
    <dt>MySQL admissions</dt>
    <dd><?= $dbCount === null ? 'not ready' : h((string)$dbCount) ?></dd>
    <dt>Skipped records</dt>
    <dd><?= h((string)$result['skipped']) ?></dd>
  </dl>

  <?php if ($result['ids'] !== []): ?>
    <details>
      <summary>Preview record IDs</summary>
      <ul class="error-list">
        <?php foreach (array_slice($result['ids'], 0, 50) as $id): ?>
          <li><?= h((string)$id) ?></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>

  <form method="post" onsubmit="return confirm('Import legacy admissions into MySQL?');">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <button class="button-link" type="submit" <?= $result['records'] > 0 && $dbCount !== null ? '' : 'disabled' ?>>Import to MySQL</button>
  </form>
</section>
<?php render_admin_footer(); ?>
