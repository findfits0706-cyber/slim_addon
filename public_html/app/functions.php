<?php
declare(strict_types=1);

function h(null|string|int|float $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function base_path(string $path = ''): string
{
    $base = rtrim(APP_BASE_PATH, '/');
    $suffix = '/' . ltrim($path, '/');

    if ($base === '') {
        return $suffix;
    }

    return $base . $suffix;
}

function asset_url(string $path): string
{
    return base_path($path);
}

function genre_options(): array
{
    return [
        'pilates' => 'マシンピラティス体験',
        'self_esthe' => 'セルフエステ体験',
        'visit' => '施設見学',
    ];
}

function genre_label(string $genre): string
{
    return genre_options()[$genre] ?? $genre;
}

function slot_type_options(): array
{
    return [
        'repeat' => '繰り返し',
        'single' => '単発',
    ];
}

function contact_method_options(): array
{
    return [
        'either' => '電話・メールどちらでも可',
        'phone' => '電話希望',
        'email' => 'メール希望',
    ];
}

function booking_status_options(): array
{
    return [
        'new' => '未対応',
        'confirmed' => '確認済み',
        'cancelled' => 'キャンセル',
        'visited' => '来店済み',
        'joined' => '入会済み',
    ];
}

function booking_status_label(string $status): string
{
    return booking_status_options()[$status] ?? $status;
}

function slot_status_options(): array
{
    return [
        'open' => '公開',
        'closed' => '受付停止',
        'hidden' => '非公開',
    ];
}

function status_label(string $status): string
{
    return slot_status_options()[$status]
        ?? booking_status_options()[$status]
        ?? $status;
}

function exception_type_options(): array
{
    return [
        'cancel' => '休講・受付停止',
        'change' => 'この日だけ変更',
        'substitute' => '代行',
    ];
}

function exception_type_label(string $type): string
{
    return exception_type_options()[$type] ?? $type;
}

function weekday_options(): array
{
    return [
        0 => '日',
        1 => '月',
        2 => '火',
        3 => '水',
        4 => '木',
        5 => '金',
        6 => '土',
    ];
}

function weekday_label(int $weekday): string
{
    return weekday_options()[$weekday] ?? '';
}

function format_date_jp(string $date): string
{
    $dt = new DateTimeImmutable($date);
    return $dt->format('Y年n月j日') . '（' . weekday_label((int)$dt->format('w')) . '）';
}

function format_time_range(string $startTime, string $endTime): string
{
    return substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5);
}

function preserve_form_data(array $input, array $defaults = []): array
{
    return array_merge($defaults, $input);
}
