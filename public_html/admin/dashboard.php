<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../app/trial-schedule.php';

require_admin();

function dashboard_column_exists(string $table, string $column): bool
{
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND COLUMN_NAME = :column_name'
        );
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

$dbStatus = db_health_check();
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$weekStart = week_start_monday($today)->format('Y-m-d');
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
$sevenDaysAgo = (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d') . ' 00:00:00';

$summary = [
    'today_slots' => 0,
    'week_slots' => 0,
    'week_open_capacity' => 0,
    'full_slots' => 0,
    'changed_slots' => 0,
    'bookings_new' => 0,
    'contact_required' => 0,
    'today_visits' => 0,
    'recent_bookings' => 0,
    'cancelled' => 0,
    'admissions_new' => 0,
];
$upcomingSlots = [];
$recentBookings = [];
$error = '';

$admissionConfig = require __DIR__ . '/../admission/inc/config.php';
$admissionStorageFile = (string)($admissionConfig['admin']['storage_file'] ?? '');
if ($admissionStorageFile !== '' && is_file($admissionStorageFile)) {
    $admissionJson = json_decode((string)file_get_contents($admissionStorageFile), true);
    $admissionRecords = is_array($admissionJson) ? $admissionJson : [];
    $summary['admissions_new'] = count(array_filter(
        $admissionRecords,
        static fn($record): bool => is_array($record) && ($record['status'] ?? 'new') === 'new'
    ));
}

if ($dbStatus['ok']) {
    try {
        $pdo = db();
        $weekSlots = admin_slot_instances_between($weekStart, $weekEnd);
        $todaySlots = array_values(array_filter($weekSlots, static fn(array $slot): bool => (string)$slot['booking_date'] === (new DateTimeImmutable('today'))->format('Y-m-d')));
        $summary['today_slots'] = count($todaySlots);
        $summary['week_slots'] = count($weekSlots);
        $summary['week_open_capacity'] = array_sum(array_map(static fn(array $slot): int => (int)$slot['remaining'], array_filter($weekSlots, static fn(array $slot): bool => (string)$slot['status'] === 'open')));
        $summary['full_slots'] = count(array_filter($weekSlots, static fn(array $slot): bool => !empty($slot['full'])));
        $summary['changed_slots'] = count(array_filter($weekSlots, static fn(array $slot): bool => (string)$slot['exception_type'] !== ''));

        $summary['bookings_new'] = (int)$pdo->query("SELECT COUNT(*) FROM trial_bookings WHERE status = 'new'")->fetchColumn();
        $summary['today_visits'] = (int)$pdo->query("SELECT COUNT(*) FROM trial_bookings WHERE booking_date = CURDATE() AND status <> 'cancelled'")->fetchColumn();
        $summary['recent_bookings'] = (int)$pdo->query("SELECT COUNT(*) FROM trial_bookings WHERE created_at >= " . $pdo->quote($sevenDaysAgo))->fetchColumn();
        $summary['cancelled'] = (int)$pdo->query("SELECT COUNT(*) FROM trial_bookings WHERE status = 'cancelled'")->fetchColumn();
        if (dashboard_column_exists('trial_bookings', 'contact_required')) {
            $summary['contact_required'] = (int)$pdo->query('SELECT COUNT(*) FROM trial_bookings WHERE contact_required = 1')->fetchColumn();
        }

        $upcomingSlots = array_slice(admin_slot_instances_between($today, (new DateTimeImmutable('today'))->modify('+14 days')->format('Y-m-d')), 0, 8);
        $stmt = $pdo->query("SELECT * FROM trial_bookings ORDER BY created_at DESC LIMIT 8");
        $recentBookings = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error = APP_DEBUG ? $e->getMessage() : 'ダッシュボードの読み込みに失敗しました。';
    }
}

$cards = [
    ['label' => '今日の開催枠数', 'value' => $summary['today_slots'], 'href' => base_path('/admin/slots.php?week=' . rawurlencode($today) . '&view=day')],
    ['label' => '今週の開催枠数', 'value' => $summary['week_slots'], 'href' => base_path('/admin/slots.php?week=' . rawurlencode($weekStart))],
    ['label' => '今週の空き枠数', 'value' => $summary['week_open_capacity'], 'href' => base_path('/admin/slots.php?week=' . rawurlencode($weekStart) . '&status=open')],
    ['label' => '満員枠数', 'value' => $summary['full_slots'], 'href' => base_path('/admin/slots.php?week=' . rawurlencode($weekStart) . '&status=full')],
    ['label' => '休講・変更あり枠数', 'value' => $summary['changed_slots'], 'href' => base_path('/admin/slots.php?week=' . rawurlencode($weekStart) . '&status=changed')],
    ['label' => '体験申込 未対応', 'value' => $summary['bookings_new'], 'href' => base_path('/admin/bookings.php?quick=pending')],
    ['label' => '要連絡数', 'value' => $summary['contact_required'], 'href' => base_path('/admin/bookings.php?quick=contact_required')],
    ['label' => '本日の来店予定', 'value' => $summary['today_visits'], 'href' => base_path('/admin/bookings.php?quick=today')],
    ['label' => '直近7日間の申込数', 'value' => $summary['recent_bookings'], 'href' => base_path('/admin/bookings.php?sort=created_desc')],
    ['label' => 'キャンセル数', 'value' => $summary['cancelled'], 'href' => base_path('/admin/bookings.php?status=cancelled')],
    ['label' => '入会受付 未対応', 'value' => $summary['admissions_new'], 'href' => base_path('/admin/admissions.php')],
];

render_admin_header('ダッシュボード');
?>
<section class="admin-card">
  <h2>ダッシュボード</h2>
  <p class="admin-note">DB接続: <strong><?= $dbStatus['ok'] ? 'OK' : 'NG' ?></strong> / <?= h($dbStatus['message']) ?></p>
  <?php if ($error !== ''): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
  <div class="stats-grid dashboard-stats">
    <?php foreach ($cards as $card): ?>
      <a class="stat-box stat-box--link" href="<?= h((string)$card['href']) ?>">
        <span class="stat-label"><?= h((string)$card['label']) ?></span>
        <strong><?= h((string)$card['value']) ?></strong>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <div class="section-row">
    <h2>入会受付</h2>
    <a class="button-link" href="<?= h(base_path('/admin/admissions.php')) ?>">入会受付管理へ</a>
  </div>
  <p class="admin-note">Web入会申込の確認、ステータス更新、SLIM転記用コピーはこちらから行えます。</p>
</section>

<section class="admin-card">
  <div class="section-row">
    <h2>直近の体験枠</h2>
    <a class="button-link" href="<?= h(base_path('/admin/slots.php')) ?>">体験予約スケジュールへ</a>
  </div>
  <table class="admin-table">
    <thead><tr><th>日程</th><th>メニュー</th><th>枠名</th><th>担当</th><th>残数</th><th>状態</th></tr></thead>
    <tbody>
      <?php if ($upcomingSlots === []): ?>
        <tr><td colspan="6">表示できる体験枠はありません。</td></tr>
      <?php endif; ?>
      <?php foreach ($upcomingSlots as $slot): ?>
        <tr>
          <td><?= h($slot['booking_date_label'] . ' ' . $slot['booking_time_label']) ?></td>
          <td><?= h($slot['genre_label']) ?></td>
          <td><?= h($slot['lesson_name']) ?></td>
          <td><?= h($slot['instructor_name']) ?></td>
          <td><?= h((string)$slot['remaining']) ?> / <?= h((string)$slot['capacity']) ?></td>
          <td><?= h(status_label($slot['status'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="admin-card">
  <div class="section-row">
    <h2>最新の申込</h2>
    <a class="button-link" href="<?= h(base_path('/admin/bookings.php')) ?>">申込一覧へ</a>
  </div>
  <table class="admin-table">
    <thead><tr><th>受付日時</th><th>氏名</th><th>メニュー</th><th>希望日</th><th>ステータス</th></tr></thead>
    <tbody>
      <?php if ($recentBookings === []): ?>
        <tr><td colspan="5">申込はまだありません。</td></tr>
      <?php endif; ?>
      <?php foreach ($recentBookings as $booking): ?>
        <tr>
          <td><?= h((string)$booking['created_at']) ?></td>
          <td><a href="<?= h(base_path('/admin/booking-edit.php?id=' . (int)$booking['id'])) ?>"><?= h((string)$booking['customer_name']) ?></a></td>
          <td><?= h(genre_label((string)$booking['genre'])) ?></td>
          <td><?= h(format_date_jp((string)$booking['booking_date']) . ' ' . format_time_range((string)$booking['start_time'], (string)$booking['end_time'])) ?></td>
          <td><?= h(booking_status_label((string)$booking['status'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_admin_footer(); ?>
