<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/schema.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../app/extension-api.php';

require_admin();

$user = admin_user();
$errors = [];
$notice = '';
$issuedPairing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'CSRF token is invalid.';
    } else {
        $action = (string)($_POST['extension_action'] ?? '');
        try {
            if ($action === 'issue_pairing') {
                $issuedPairing = extension_create_pairing_code((int)($user['id'] ?? 0));
                $notice = 'Pairing code was issued.';
            } elseif ($action === 'revoke_token') {
                $tokenId = (int)($_POST['token_id'] ?? 0);
                if ($tokenId <= 0) {
                    $errors[] = 'Token id is invalid.';
                } elseif (extension_revoke_access_token($tokenId, (int)($user['id'] ?? 0))) {
                    $notice = 'Access token was revoked.';
                } else {
                    $notice = 'No active token was changed.';
                }
            } elseif ($action === 'revoke_all') {
                $count = extension_revoke_all_access_tokens((int)($user['id'] ?? 0));
                $notice = (string)$count . ' access token(s) were revoked.';
            } else {
                $errors[] = 'Unknown action.';
            }
        } catch (ExtensionApiException $e) {
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Extension admin error: ' . $e->getMessage());
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Extension settings could not be updated.';
        }
    }
}

$config = extension_config();
$tablesReady = false;
$tokens = [];
try {
    $tablesReady = extension_tables_ready();
    if ($tablesReady) {
        $tokens = extension_active_access_tokens();
    }
} catch (Throwable $e) {
    $errors[] = APP_DEBUG ? $e->getMessage() : 'Extension API tables could not be checked.';
}

render_admin_header('Edge extension connection');
?>
<section class="admin-card">
  <div class="section-row">
    <div>
      <h2>Edge extension connection</h2>
      <p class="admin-note">Issue a one-time pairing code for the Microsoft Edge side panel. Codes are shown only once and expire in 5 minutes.</p>
    </div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="extension_action" value="issue_pairing">
      <button class="button-link" type="submit" <?= $tablesReady ? '' : 'disabled' ?>>Edgeを接続</button>
    </form>
  </div>

  <?php if (!$tablesReady): ?>
    <p class="error">Extension API migration has not been applied yet.</p>
  <?php endif; ?>

  <?php if (empty($config['transfer_enabled'])): ?>
    <p class="error">Extension transfer API is disabled. Set <code>extension.transfer_enabled</code> to true in the private config when ready.</p>
  <?php endif; ?>

  <?php if ($notice !== ''): ?>
    <div class="flash flash--success"><?= h($notice) ?></div>
  <?php endif; ?>

  <?php if ($errors !== []): ?>
    <div class="error" role="alert">
      <strong>Could not complete the action.</strong>
      <ul class="error-list">
        <?php foreach ($errors as $error): ?>
          <li><?= h((string)$error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($issuedPairing !== null): ?>
    <div class="extension-pairing-code" aria-live="polite">
      <span>Pairing code</span>
      <strong><?= h((string)$issuedPairing['display_code']) ?></strong>
      <small>Expires at <?= h((string)$issuedPairing['expires_at']) ?>. This code cannot be shown again.</small>
    </div>
  <?php endif; ?>
</section>

<section class="admin-card">
  <div class="section-row">
    <div>
      <h2>Active tokens</h2>
      <p class="admin-note">Only token hashes are stored. installation_id is shown by suffix only.</p>
    </div>
    <form method="post" onsubmit="return confirm('Revoke all active extension tokens?');">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="extension_action" value="revoke_all">
      <button class="button-link button-link--muted" type="submit" <?= $tokens === [] ? 'disabled' : '' ?>>全端末を失効</button>
    </form>
  </div>

  <table class="admin-table">
    <thead>
      <tr>
        <th>Staff</th>
        <th>Installation</th>
        <th>Version</th>
        <th>Last used</th>
        <th>Expires</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($tokens === []): ?>
        <tr><td colspan="6">No active extension tokens.</td></tr>
      <?php endif; ?>
      <?php foreach ($tokens as $token): ?>
        <tr>
          <td><?= h((string)$token['staff_display_name']) ?></td>
          <td><?= h((string)$token['installation_id_masked']) ?></td>
          <td><?= h((string)$token['extension_version']) ?></td>
          <td><?= h((string)($token['last_used_at'] ?: '-')) ?></td>
          <td><?= h((string)$token['expires_at']) ?></td>
          <td>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="extension_action" value="revoke_token">
              <input type="hidden" name="token_id" value="<?= h((string)$token['id']) ?>">
              <button class="button-link button-link--muted" type="submit">失効</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_admin_footer(); ?>
