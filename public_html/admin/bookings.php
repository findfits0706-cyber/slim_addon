<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../app/trial-schedule.php';

require_admin();

function table_columns(string $table): array
{
    try {
        $stmt = db()->prepare(
            'SELECT COLUMN_NAME
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $table]);

        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
            $columns[(string)$column] = true;
        }

        return $columns;
    } catch (Throwable) {
        return [];
    }
}

function booking_value(array $booking, string $column, string $default = ''): string
{
    if (!array_key_exists($column, $booking) || $booking[$column] === null) {
        return $default;
    }

    return (string)$booking[$column];
}

function booking_date_time_label(array $booking): string
{
    $date = booking_value($booking, 'booking_date');
    $startTime = booking_value($booking, 'start_time');
    $endTime = booking_value($booking, 'end_time');
    $label = $date !== '' && valid_date_string($date) ? format_date_jp($date) : '-';

    if ($startTime !== '' && $endTime !== '') {
        $label .= ' ' . format_time_range($startTime, $endTime);
    }

    return $label;
}

$bookingColumns = table_columns('trial_bookings');
$hasColumn = static fn(string $column): bool => isset($bookingColumns[$column]);
$hasContactRequired = $hasColumn('contact_required');
$hasStatus = $hasColumn('status');
$hasGenre = $hasColumn('genre');
$hasInstructorName = $hasColumn('instructor_name');
$hasBookingDate = $hasColumn('booking_date');
$hasStartTime = $hasColumn('start_time');
$hasEndTime = $hasColumn('end_time');
$hasCreatedAt = $hasColumn('created_at');

$status = trim((string)($_GET['status'] ?? ''));
$genre = trim((string)($_GET['genre'] ?? ''));
$instructor = trim((string)($_GET['instructor'] ?? ''));
$keyword = trim((string)($_GET['keyword'] ?? ''));
$bookingFrom = trim((string)($_GET['booking_from'] ?? ''));
$bookingTo = trim((string)($_GET['booking_to'] ?? ''));
$createdFrom = trim((string)($_GET['created_from'] ?? ''));
$createdTo = trim((string)($_GET['created_to'] ?? ''));
$quick = trim((string)($_GET['quick'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'priority'));
$perPage = (int)($_GET['per_page'] ?? 20);
$perPage = in_array($perPage, [20, 50], true) ? $perPage : 20;
$page = max(1, (int)($_GET['page'] ?? 1));

if ($quick === 'today') {
    $bookingFrom = $bookingTo = (new DateTimeImmutable('today'))->format('Y-m-d');
}
if ($quick === 'week') {
    $start = week_start_monday((new DateTimeImmutable('today'))->format('Y-m-d'));
    $bookingFrom = $start->format('Y-m-d');
    $bookingTo = $start->modify('+6 days')->format('Y-m-d');
}
if ($quick === 'month') {
    $today = new DateTimeImmutable('today');
    $bookingFrom = $today->modify('first day of this month')->format('Y-m-d');
    $bookingTo = $today->modify('last day of this month')->format('Y-m-d');
}

$conditions = [];
$params = [];

if ($status !== '' && $hasStatus && array_key_exists($status, booking_status_options())) {
    $conditions[] = 'status = :status';
    $params['status'] = $status;
}
if ($genre !== '' && $hasGenre && array_key_exists($genre, genre_options())) {
    $conditions[] = 'genre = :genre';
    $params['genre'] = $genre;
}
if ($instructor !== '' && $hasInstructorName) {
    $conditions[] = 'instructor_name LIKE :instructor';
    $params['instructor'] = '%' . $instructor . '%';
}
if ($keyword !== '') {
    $keywordColumns = [];
    foreach (['customer_name', 'customer_kana', 'email', 'phone'] as $column) {
        if ($hasColumn($column)) {
            $keywordColumns[] = $column . ' LIKE :keyword';
        }
    }
    if ($keywordColumns !== []) {
        $conditions[] = '(' . implode(' OR ', $keywordColumns) . ')';
        $params['keyword'] = '%' . $keyword . '%';
    }
}
if ($hasBookingDate && valid_date_string($bookingFrom)) {
    $conditions[] = 'booking_date >= :booking_from';
    $params['booking_from'] = $bookingFrom;
}
if ($hasBookingDate && valid_date_string($bookingTo)) {
    $conditions[] = 'booking_date <= :booking_to';
    $params['booking_to'] = $bookingTo;
}
if ($hasCreatedAt && valid_date_string($createdFrom)) {
    $conditions[] = 'created_at >= :created_from';
    $params['created_from'] = $createdFrom . ' 00:00:00';
}
if ($hasCreatedAt && valid_date_string($createdTo)) {
    $conditions[] = 'created_at <= :created_to';
    $params['created_to'] = $createdTo . ' 23:59:59';
}
if ($quick === 'pending' && $hasStatus) {
    $conditions[] = "status = 'new'";
}
if ($quick === 'overdue' && $hasBookingDate && $hasStatus) {
    $conditions[] = "booking_date < CURDATE() AND status IN ('new', 'confirmed')";
}
if ($quick === 'contact_required' && $hasContactRequired) {
    $conditions[] = 'contact_required = 1';
}

$where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

$defaultOrderParts = [];
if ($hasStatus) {
    $defaultOrderParts[] = "CASE WHEN status = 'new' THEN 0 ELSE 1 END ASC";
}
if ($hasBookingDate) {
    $defaultOrderParts[] = 'booking_date ASC';
}
if ($hasStartTime) {
    $defaultOrderParts[] = 'start_time ASC';
}
if ($hasCreatedAt) {
    $defaultOrderParts[] = 'created_at DESC';
}
$defaultOrderParts[] = 'id DESC';

$orderBy = implode(', ', $defaultOrderParts);
if ($sort === 'booking_desc' && $hasBookingDate) {
    $orderParts = ['booking_date DESC'];
    if ($hasStartTime) {
        $orderParts[] = 'start_time DESC';
    }
    $orderParts[] = 'id DESC';
    $orderBy = implode(', ', $orderParts);
} elseif ($sort === 'created_asc' && $hasCreatedAt) {
    $orderBy = 'created_at ASC, id ASC';
} elseif ($sort === 'created_desc' && $hasCreatedAt) {
    $orderBy = 'created_at DESC, id DESC';
} elseif ($sort === 'status' && $hasStatus) {
    $orderParts = ['status ASC'];
    if ($hasBookingDate) {
        $orderParts[] = 'booking_date ASC';
    }
    if ($hasStartTime) {
        $orderParts[] = 'start_time ASC';
    }
    $orderParts[] = 'id DESC';
    $orderBy = implode(', ', $orderParts);
}

$bookings = [];
$totalCount = 0;
$totalPages = 1;
$queryError = '';

try {
    $countStmt = db()->prepare('SELECT COUNT(*) FROM trial_bookings' . $where);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $sql = 'SELECT * FROM trial_bookings' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (Throwable $e) {
    $queryError = db_error_message($e);
    $page = 1;
}

$baseQuery = $_GET;
unset($baseQuery['page']);

render_admin_header('体験申込一覧');
?>
<section class="admin-card">
  <div class="section-row">
    <div>
      <h2>体験申込一覧</h2>
      <p class="admin-note">全 <?= h((string)$totalCount) ?> 件 / <?= h((string)$page) ?> ページ目</p>
    </div>
    <div class="actions">
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/bookings.php?quick=today')) ?>">今日</a>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/bookings.php?quick=week')) ?>">今週</a>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/bookings.php?quick=pending')) ?>">未対応のみ</a>
      <?php if ($hasContactRequired): ?><a class="button-link button-link--muted" href="<?= h(base_path('/admin/bookings.php?quick=contact_required')) ?>">要連絡のみ</a><?php endif; ?>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/bookings.php?quick=overdue')) ?>">予約日超過</a>
    </div>
  </div>
  <form method="get" class="filters-grid">
    <label>ステータス
      <select name="status">
        <option value="">すべて</option>
        <?php foreach (booking_status_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>メニュー
      <select name="genre">
        <option value="">すべて</option>
        <?php foreach (genre_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= $genre === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>担当者<input name="instructor" value="<?= h($instructor) ?>"></label>
    <label>検索<input name="keyword" value="<?= h($keyword) ?>" placeholder="氏名・カナ・メール・電話"></label>
    <label>予約日From<input type="date" name="booking_from" value="<?= h($bookingFrom) ?>"></label>
    <label>予約日To<input type="date" name="booking_to" value="<?= h($bookingTo) ?>"></label>
    <label>申込日From<input type="date" name="created_from" value="<?= h($createdFrom) ?>"></label>
    <label>申込日To<input type="date" name="created_to" value="<?= h($createdTo) ?>"></label>
    <label>並び順
      <select name="sort">
        <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>未対応優先・予約日時順</option>
        <option value="booking_desc" <?= $sort === 'booking_desc' ? 'selected' : '' ?>>予約日時が新しい順</option>
        <option value="created_desc" <?= $sort === 'created_desc' ? 'selected' : '' ?>>新しい申込順</option>
        <option value="created_asc" <?= $sort === 'created_asc' ? 'selected' : '' ?>>古い申込順</option>
        <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>ステータス順</option>
      </select>
    </label>
    <label>件数
      <select name="per_page">
        <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20件</option>
        <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50件</option>
      </select>
    </label>
    <div class="form-actions form-actions--compact">
      <button type="submit">絞り込む</button>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/bookings.php')) ?>">解除</a>
    </div>
  </form>
</section>

<section class="admin-card">
  <?php if ($queryError !== ''): ?><p class="error"><?= h($queryError) ?></p><?php endif; ?>
  <table class="admin-table booking-table">
    <thead><tr><th>予約日時</th><th>氏名</th><th>メニュー</th><th>担当</th><th>電話</th><th>メール</th><th>ステータス</th><?php if ($hasContactRequired): ?><th>要連絡</th><?php endif; ?><th>申込日時</th><th>操作</th></tr></thead>
    <tbody>
      <?php if ($bookings === []): ?>
        <tr><td colspan="<?= $hasContactRequired ? '10' : '9' ?>">該当する申込はありません。</td></tr>
      <?php endif; ?>
      <?php foreach ($bookings as $booking): ?>
        <?php
        $customerName = booking_value($booking, 'customer_name', '-');
        $customerKana = booking_value($booking, 'customer_kana');
        $genreValue = booking_value($booking, 'genre');
        $lessonName = booking_value($booking, 'lesson_name');
        $instructorName = booking_value($booking, 'instructor_name', '-');
        $phone = booking_value($booking, 'phone');
        $email = booking_value($booking, 'email');
        $statusValue = booking_value($booking, 'status');
        $createdAt = booking_value($booking, 'created_at', '-');
        ?>
        <tr>
          <td><?= h(booking_date_time_label($booking)) ?></td>
          <td><?= h($customerName) ?><?php if ($customerKana !== ''): ?><br><span class="muted"><?= h($customerKana) ?></span><?php endif; ?></td>
          <td><?= h($genreValue !== '' ? genre_label($genreValue) : '-') ?><?php if ($lessonName !== ''): ?><br><span class="muted"><?= h($lessonName) ?></span><?php endif; ?></td>
          <td><?= h($instructorName) ?></td>
          <td><?php if ($phone !== ''): ?><a href="tel:<?= h($phone) ?>"><?= h($phone) ?></a><?php else: ?>-<?php endif; ?></td>
          <td><?php if ($email !== ''): ?><a href="mailto:<?= h($email) ?>"><?= h($email) ?></a><?php else: ?>-<?php endif; ?></td>
          <td><?= h($statusValue !== '' ? booking_status_label($statusValue) : '-') ?></td>
          <?php if ($hasContactRequired): ?><td><?= !empty($booking['contact_required']) ? '要連絡' : '-' ?></td><?php endif; ?>
          <td><?= h($createdAt) ?></td>
          <td><?php if (!empty($booking['id'])): ?><a href="<?= h(base_path('/admin/booking-edit.php?id=' . (int)$booking['id'])) ?>">詳細・更新</a><?php else: ?>-<?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <nav class="pagination" aria-label="ページネーション">
    <?php if ($page > 1): ?>
      <?php $query = http_build_query($baseQuery + ['page' => $page - 1]); ?>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/bookings.php?' . $query)) ?>">前へ</a>
    <?php endif; ?>
    <span><?= h((string)$page) ?> / <?= h((string)$totalPages) ?></span>
    <?php if ($page < $totalPages): ?>
      <?php $query = http_build_query($baseQuery + ['page' => $page + 1]); ?>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/bookings.php?' . $query)) ?>">次へ</a>
    <?php endif; ?>
  </nav>
</section>
<?php render_admin_footer(); ?>
