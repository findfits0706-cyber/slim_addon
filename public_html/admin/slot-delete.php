<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../app/trial.php';
require_once __DIR__ . '/../app/schema.php';
require_once __DIR__ . '/../app/audit.php';
require_once __DIR__ . '/../app/flash.php';

require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$template = $id > 0 ? (fetch_slot_templates($id)[0] ?? null) : null;

if (!$template) {
    http_response_code(404);
    exit('指定された体験枠が見つかりません。');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = '不正な送信です。';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if (db_column_exists_cached('trial_slot_templates', 'archived_at')) {
                $sql = 'UPDATE trial_slot_templates SET status = :status, archived_at = NOW()';
                if (db_column_exists_cached('trial_slot_templates', 'version')) {
                    $sql .= ', version = version + 1';
                }
                $sql .= ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['status' => 'hidden', 'id' => $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE trial_slot_templates SET status = 'hidden' WHERE id = :id");
                $stmt->execute(['id' => $id]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        audit_log('trial_slot_template', (string)$id, 'slot_archive', $template, ['status' => 'hidden']);
        flash_set('ok', '体験枠をアーカイブしました。');
        redirect(base_path('/admin/slots.php'));
    }
}

render_admin_header('体験枠削除');
?>
<section class="admin-card admin-card--narrow">
  <h1>体験枠をアーカイブ</h1>
  <?php if ($error !== ''): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
  <p>以下の体験枠を公開対象から外します。予約履歴を守るため、物理削除ではなくアーカイブします。</p>
  <ul class="detail-list">
    <li>ID: <?= h((string)$template['id']) ?></li>
    <li>メニュー: <?= h(genre_label((string)$template['genre'])) ?></li>
    <li>枠名: <?= h((string)$template['lesson_name']) ?></li>
  </ul>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= h((string)$id) ?>">
    <div class="form-actions">
      <button type="submit">アーカイブする</button>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/slots.php')) ?>">戻る</a>
    </div>
  </form>
</section>
<?php render_admin_footer(); ?>
