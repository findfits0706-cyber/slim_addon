<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../app/trial-schedule.php';

require_admin();

$view = (string)($_GET['view'] ?? 'week');
if (!in_array($view, ['week', 'day'], true)) {
    $view = 'week';
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$requestedDate = (string)($_GET['week'] ?? '');
$baseDate = valid_date_string($requestedDate) ? $requestedDate : $today;
$weekStart = week_start_monday($baseDate);

$displayDays = $view === 'day'
    ? [[
        'date' => $baseDate,
        'label' => (new DateTimeImmutable($baseDate))->format('n/j'),
        'weekday' => weekday_label((int)(new DateTimeImmutable($baseDate))->format('w')),
        'is_today' => $baseDate === $today,
    ]]
    : week_days($weekStart);

$rangeStart = (string)$displayDays[0]['date'];
$rangeEnd = (string)$displayDays[array_key_last($displayDays)]['date'];
$filters = [
    'genre' => trim((string)($_GET['genre'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'instructor' => trim((string)($_GET['instructor'] ?? '')),
];
$activeFilterCount = count(array_filter($filters, static fn(string $value): bool => $value !== ''));

$hours = trial_admin_business_hours();
$timeRows = [];
for (
    $minute = time_to_minutes((string)$hours['open']);
    $minute < time_to_minutes((string)$hours['close']);
    $minute += (int)$hours['step_minutes']
) {
    $timeRows[] = minutes_to_time($minute);
}

$slots = [];
$slotsByDateTime = [];
$error = '';

try {
    $slots = admin_slot_instances_between($rangeStart, $rangeEnd, $filters);
    foreach ($slots as $slot) {
        $key = (string)$slot['booking_date'] . '|' . substr((string)$slot['start_time'], 0, 5);
        $slotsByDateTime[$key][] = $slot;
    }
} catch (Throwable $e) {
    $error = APP_DEBUG ? $e->getMessage() : '体験予約スケジュールの読み込みに失敗しました。';
}

$moveUnit = $view === 'day' ? '1 day' : '7 days';
$previousDate = (new DateTimeImmutable($baseDate))->modify('-' . $moveUnit)->format('Y-m-d');
$nextDate = (new DateTimeImmutable($baseDate))->modify('+' . $moveUnit)->format('Y-m-d');

$scheduleUrl = static function (array $overrides = []) use ($baseDate, $view, $filters): string {
    $query = array_merge([
        'week' => $baseDate,
        'view' => $view,
        'genre' => $filters['genre'],
        'status' => $filters['status'],
        'instructor' => $filters['instructor'],
    ], $overrides);

    $query = array_filter($query, static fn(mixed $value): bool => $value !== '');
    return base_path('/admin/slots.php?' . http_build_query($query));
};

$rangeLabel = $view === 'day'
    ? format_date_jp($baseDate)
    : (new DateTimeImmutable($rangeStart))->format('Y年n月j日') . ' — ' . (new DateTimeImmutable($rangeEnd))->format('n月j日');

render_admin_header('体験予約スケジュール', 'admin-page--schedule');
?>
<section class="schedule-toolbar-card" aria-label="スケジュール操作">
  <div class="schedule-toolbar">
    <div class="schedule-toolbar__navigation">
      <a class="schedule-button schedule-button--quiet" href="<?= h($scheduleUrl(['week' => $today])) ?>">今日</a>
      <div class="schedule-arrow-group" aria-label="表示期間の移動">
        <a class="schedule-icon-button" href="<?= h($scheduleUrl(['week' => $previousDate])) ?>" aria-label="<?= $view === 'day' ? '前日' : '前週' ?>">‹</a>
        <a class="schedule-icon-button" href="<?= h($scheduleUrl(['week' => $nextDate])) ?>" aria-label="<?= $view === 'day' ? '翌日' : '翌週' ?>">›</a>
      </div>

      <form method="get" class="schedule-date-form" data-auto-date-form>
        <input type="date" name="week" value="<?= h($baseDate) ?>" aria-label="表示する日付">
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <?php if ($filters['genre'] !== ''): ?><input type="hidden" name="genre" value="<?= h($filters['genre']) ?>"><?php endif; ?>
        <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= h($filters['status']) ?>"><?php endif; ?>
        <?php if ($filters['instructor'] !== ''): ?><input type="hidden" name="instructor" value="<?= h($filters['instructor']) ?>"><?php endif; ?>
        <button type="submit" class="schedule-date-submit">表示</button>
      </form>

      <div class="schedule-period-label" aria-live="polite"><?= h($rangeLabel) ?></div>
    </div>

    <div class="schedule-toolbar__actions">
      <div class="schedule-segmented" role="group" aria-label="表示形式">
        <a class="<?= $view === 'day' ? 'is-active' : '' ?>" href="<?= h($scheduleUrl(['view' => 'day'])) ?>" aria-current="<?= $view === 'day' ? 'page' : 'false' ?>">日</a>
        <a class="<?= $view === 'week' ? 'is-active' : '' ?>" href="<?= h($scheduleUrl(['view' => 'week'])) ?>" aria-current="<?= $view === 'week' ? 'page' : 'false' ?>">週</a>
      </div>

      <button
        type="button"
        class="schedule-button schedule-button--quiet"
        data-toggle-filters
        aria-expanded="false"
        aria-controls="schedule-filter-panel"
      >絞り込み<?php if ($activeFilterCount > 0): ?><span class="schedule-count-badge"><?= h((string)$activeFilterCount) ?></span><?php endif; ?></button>

      <button type="button" class="schedule-button schedule-button--primary" data-open-drawer>＋ 新しい枠</button>

      <div class="schedule-more" data-more-menu-root>
        <button type="button" class="schedule-icon-button" data-more-menu-button aria-expanded="false" aria-controls="schedule-more-menu" aria-label="その他の操作">⋯</button>
        <div class="schedule-more__menu" id="schedule-more-menu" data-more-menu hidden>
          <a href="<?= h(base_path('/admin/schedule-copy.php?target_week=' . rawurlencode($weekStart->format('Y-m-d')))) ?>">前週をコピー</a>
          <a href="<?= h(base_path('/admin/schedule-templates.php?week=' . rawurlencode($weekStart->format('Y-m-d')))) ?>">テンプレートから作成</a>
          <a href="<?= h(base_path('/admin/schedule-import.php')) ?>">CSV取込</a>
          <a href="<?= h(base_path('/admin/schedule-import.php?export=slots&week=' . rawurlencode($weekStart->format('Y-m-d')))) ?>">CSV出力</a>
          <a href="<?= h(base_path('/admin/closures.php')) ?>">休館日・利用停止</a>
        </div>
      </div>
    </div>
  </div>

  <div class="schedule-filter-summary">
    <?php if ($activeFilterCount === 0): ?>
      <span class="schedule-filter-chip">すべての枠</span>
    <?php else: ?>
      <?php if ($filters['genre'] !== ''): ?>
        <span class="schedule-filter-chip"><strong>種別</strong><?= h(genre_label($filters['genre'])) ?></span>
      <?php endif; ?>
      <?php if ($filters['status'] !== ''): ?>
        <span class="schedule-filter-chip"><strong>状態</strong><?= h($filters['status'] === 'full' ? '満員' : ($filters['status'] === 'changed' ? '変更あり' : ($filters['status'] === 'substitute' ? '代行あり' : status_label($filters['status'])))) ?></span>
      <?php endif; ?>
      <?php if ($filters['instructor'] !== ''): ?>
        <span class="schedule-filter-chip"><strong>担当</strong><?= h($filters['instructor']) ?></span>
      <?php endif; ?>
      <a class="schedule-filter-reset" href="<?= h($scheduleUrl(['genre' => '', 'status' => '', 'instructor' => ''])) ?>">すべて解除</a>
    <?php endif; ?>
    <span class="schedule-result-count">表示中 <?= h((string)count($slots)) ?>枠</span>
  </div>

  <form method="get" class="schedule-filters" id="schedule-filter-panel" data-filter-panel hidden>
    <input type="hidden" name="week" value="<?= h($baseDate) ?>">
    <input type="hidden" name="view" value="<?= h($view) ?>">
    <label>種別
      <select name="genre">
        <option value="">すべて</option>
        <?php foreach (genre_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= $filters['genre'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>状態
      <select name="status">
        <option value="">すべて</option>
        <?php foreach (slot_status_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
        <option value="full" <?= $filters['status'] === 'full' ? 'selected' : '' ?>>満員</option>
        <option value="changed" <?= $filters['status'] === 'changed' ? 'selected' : '' ?>>変更あり</option>
        <option value="substitute" <?= $filters['status'] === 'substitute' ? 'selected' : '' ?>>代行あり</option>
      </select>
    </label>
    <label>担当者
      <input name="instructor" value="<?= h($filters['instructor']) ?>" placeholder="担当者名">
    </label>
    <div class="schedule-filter-actions">
      <button type="submit" class="schedule-button schedule-button--primary">適用</button>
      <a class="schedule-button schedule-button--quiet" href="<?= h($scheduleUrl(['genre' => '', 'status' => '', 'instructor' => ''])) ?>">解除</a>
    </div>
  </form>
</section>

<?php if ($error !== ''): ?><p class="error"><?= h($error) ?></p><?php endif; ?>

<section class="schedule-calendar-card" aria-label="体験予約カレンダー">
  <div class="schedule-calendar-shell">
    <div class="schedule-calendar<?= $view === 'day' ? ' schedule-calendar--day' : '' ?>" style="--schedule-day-count: <?= h((string)count($displayDays)) ?>">
      <div class="schedule-corner">時間</div>
      <?php foreach ($displayDays as $day): ?>
        <div class="schedule-day-head<?= $day['is_today'] ? ' is-today' : '' ?>">
          <strong><?= h($day['label']) ?></strong>
          <span><?= h($day['weekday']) ?></span>
          <?php if ($day['is_today']): ?><small>今日</small><?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php foreach ($timeRows as $time): ?>
        <div class="schedule-time"><?= h($time) ?></div>
        <?php foreach ($displayDays as $day): ?>
          <?php
          $date = (string)$day['date'];
          $cellSlots = $slotsByDateTime[$date . '|' . $time] ?? [];
          ?>
          <div class="schedule-cell<?= $day['is_today'] ? ' is-today' : '' ?>">
            <button
              type="button"
              class="schedule-empty-button"
              data-open-drawer
              data-date="<?= h($date) ?>"
              data-time="<?= h($time) ?>"
              aria-label="<?= h(format_date_jp($date) . ' ' . $time . ' に枠を作成') ?>"
            ><span aria-hidden="true">＋</span></button>

            <?php foreach ($cellSlots as $slot): ?>
              <?php
              $genre = (string)($slot['genre'] ?? '');
              $status = (string)($slot['status'] ?? '');
              $exceptionType = (string)($slot['exception_type'] ?? '');
              $classes = ['schedule-slot', 'schedule-slot--' . $genre, 'schedule-slot--status-' . $status];
              if (!empty($slot['full'])) {
                  $classes[] = 'is-full';
              }
              if ($exceptionType !== '') {
                  $classes[] = 'has-exception';
              }
              $resourceParts = array_values(array_filter([
                  trim((string)($slot['instructor_name'] ?? '')),
                  trim((string)($slot['booth_name'] ?? '')),
                  trim((string)($slot['equipment_name'] ?? '')),
              ], static fn(string $value): bool => $value !== ''));
              ?>
              <article class="<?= h(implode(' ', $classes)) ?>">
                <div class="schedule-slot__head">
                  <strong title="<?= h((string)$slot['lesson_name']) ?>"><?= h((string)$slot['lesson_name']) ?></strong>
                  <span><?= h(format_time_range((string)$slot['start_time'], (string)$slot['end_time'])) ?></span>
                </div>
                <?php if ($resourceParts !== []): ?>
                  <div class="schedule-slot__meta"><?= h(implode('・', $resourceParts)) ?></div>
                <?php endif; ?>
                <div class="schedule-slot__status-row">
                  <span class="schedule-slot__capacity"><?= h((string)($slot['booked_count'] ?? 0)) ?>/<?= h((string)$slot['capacity']) ?>名</span>
                  <?php if (!empty($slot['full'])): ?><span class="schedule-slot__badge">満員</span><?php endif; ?>
                  <?php if ($status !== 'open'): ?><span class="schedule-slot__badge"><?= h(status_label($status)) ?></span><?php endif; ?>
                  <?php if ($exceptionType !== ''): ?><span class="schedule-slot__badge"><?= h(exception_type_label($exceptionType)) ?></span><?php endif; ?>
                </div>
                <div class="schedule-slot__links">
                  <a href="<?= h(base_path('/admin/slot-edit.php?id=' . (int)$slot['template_id'])) ?>">編集</a>
                  <a href="<?= h(base_path('/admin/slot-exception.php?template_id=' . (int)$slot['template_id'] . '&date=' . rawurlencode((string)$slot['booking_date']))) ?>">この回を変更</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="schedule-legend">
    <span><i class="schedule-legend__mark schedule-legend__mark--pilates"></i>ピラティス体験</span>
    <span><i class="schedule-legend__mark schedule-legend__mark--self-esthe"></i>セルフエステ体験</span>
    <span><i class="schedule-legend__mark schedule-legend__mark--visit"></i>見学</span>
    <span><i class="schedule-legend__mark schedule-legend__mark--closed"></i>休講・停止</span>
    <span class="schedule-legend__help"><?= h((string)$hours['step_minutes']) ?>分単位／空白を選択して新規作成</span>
  </div>
</section>

<div class="schedule-drawer-backdrop" data-drawer-backdrop hidden></div>
<aside class="schedule-drawer" data-schedule-drawer aria-label="予約枠作成" aria-hidden="true">
  <div class="schedule-drawer__head">
    <div>
      <p class="admin-topbar__eyebrow">CREATE</p>
      <h2>新しい体験枠</h2>
    </div>
    <button type="button" class="schedule-icon-button" data-close-drawer aria-label="ドロワーを閉じる">×</button>
  </div>

  <form method="post" action="<?= h(base_path('/admin/schedule-save.php')) ?>" class="schedule-drawer__form" data-schedule-form>
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="schedule_action" value="preview">
    <input type="hidden" name="return_week" value="<?= h($baseDate) ?>">

    <div class="schedule-drawer__body">
      <div class="admin-form-grid schedule-form">
        <label class="is-full">何を作成するか
          <select name="create_kind" data-kind-select>
            <option value="pilates">ピラティス体験</option>
            <option value="self_esthe">セルフエステ体験</option>
            <option value="visit">見学</option>
            <option value="closed">予約受付停止</option>
          </select>
        </label>

        <label class="is-full">開催方法
          <select name="repeat_mode" data-repeat-select>
            <option value="single">1回のみ</option>
            <option value="weekly">毎週</option>
            <option value="biweekly">隔週</option>
            <option value="multi_weekday">複数曜日</option>
            <option value="self_esthe_bulk">営業時間から一括生成</option>
          </select>
        </label>

        <label class="is-full" data-closed-genre hidden>受付停止の対象
          <select name="closed_genre">
            <?php foreach (genre_options() as $value => $label): ?>
              <option value="<?= h($value) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="is-full">枠名
          <input name="lesson_name" maxlength="120" placeholder="例：リフォーマー体験">
        </label>

        <label data-single-field>開催日
          <input type="date" name="single_date" value="<?= h($baseDate) ?>" min="<?= h($today) ?>">
        </label>

        <label data-normal-time>開始時刻
          <input type="time" name="start_time" value="10:00" step="1800">
        </label>

        <label data-normal-time>終了時刻
          <input type="time" name="end_time" value="10:50" step="300">
        </label>

        <label data-range-field hidden>開始日
          <input type="date" name="repeat_start_date" value="<?= h(max($baseDate, $today)) ?>" min="<?= h($today) ?>">
        </label>

        <label data-range-field hidden>終了日
          <input type="date" name="repeat_end_date" value="<?= h(max($baseDate, $today)) ?>" min="<?= h($today) ?>">
        </label>

        <fieldset class="weekday-picker is-full" data-range-field hidden>
          <legend>開催曜日</legend>
          <?php foreach (weekday_options() as $value => $label): ?>
            <label><input type="checkbox" name="weekdays[]" value="<?= h((string)$value) ?>"> <?= h($label) ?></label>
          <?php endforeach; ?>
        </fieldset>

        <label data-bulk-field hidden>受付開始
          <input type="time" name="bulk_start_time" value="10:00" step="1800">
        </label>

        <label data-bulk-field hidden>受付終了
          <input type="time" name="bulk_end_time" value="20:00" step="1800">
        </label>

        <label data-bulk-field hidden>利用時間（分）
          <input type="number" name="duration_minutes" value="50" min="10" max="240" step="5">
        </label>

        <label data-bulk-field hidden>入替・清掃時間（分）
          <input type="number" name="cleanup_minutes" value="10" min="0" max="120" step="5">
        </label>

        <label>担当者
          <input name="instructor_name" maxlength="100" placeholder="担当者名">
        </label>

        <label>定員
          <input type="number" name="capacity" value="10" min="1" max="99">
        </label>

        <label>開催場所
          <input name="location_name" maxlength="100" placeholder="例：Cスタジオ">
        </label>

        <label>ブース
          <input name="booth_name" maxlength="100" placeholder="例：エステブース1">
        </label>

        <label>機器
          <input name="equipment_name" maxlength="100" placeholder="例：CELL ZERO SMART 1">
        </label>

        <label>公開状態
          <select name="status">
            <?php foreach (slot_status_options() as $value => $label): ?>
              <option value="<?= h($value) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="is-full">公開側に表示する説明
          <textarea name="description" maxlength="2000" placeholder="体験内容や注意事項"></textarea>
        </label>
      </div>
    </div>

    <div class="schedule-drawer__footer">
      <button type="button" class="schedule-button schedule-button--quiet" data-close-drawer>キャンセル</button>
      <button type="submit" class="schedule-button schedule-button--primary">作成内容を確認</button>
    </div>
  </form>
</aside>
<?php render_admin_footer(); ?>
