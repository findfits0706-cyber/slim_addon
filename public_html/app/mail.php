<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function send_mail_message(string $to, string $subject, string $body): bool
{
    if (!function_exists('mb_send_mail')) {
        return false;
    }

    mb_language('Japanese');
    mb_internal_encoding('UTF-8');

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . mb_encode_mimeheader(FROM_NAME) . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'Content-Type: text/plain; charset=ISO-2022-JP',
        'Content-Transfer-Encoding: 7bit',
    ];

    return mb_send_mail($to, $subject, $body, implode("\r\n", $headers));
}

function send_customer_mail(array $booking): bool
{
    $subject = '【Find Pilates】体験申込を受け付けました';
    $body = <<<TEXT
{$booking['customer_name']} 様

Find Pilatesの体験申込を受け付けました。
以下の内容で確認を進めてまいります。

体験内容: {$booking['genre_label']}
日程: {$booking['booking_date_label']}
時間: {$booking['booking_time_label']}
担当: {$booking['instructor_name']}

【体験当日のご案内】

■ ご来館時間
ご予約時間の10分前を目安にお越しください。

■ 服装
伸縮性があり、身体にほどよくフィットする、運動しやすい服装でご参加ください。
ゆったりしすぎる服、裾の広い服、ジーンズなどの伸縮性がない服装はおすすめしておりません。
更衣スペースには数に限りがあるため、可能な方は運動できる服装でお越しください。

■ お持ち物
・グリップソックス（滑り止めのついた靴下
・スマートフォン
・タオル、飲み物（必要に応じて）

グリップソックスは店頭でも販売しています。
室内シューズは必要ありません。
館内に給水設備はありません。

担当より確認のご連絡を差し上げますので、少々お待ちください。

Find Pilates
TEXT;

    return send_mail_message((string)$booking['email'], $subject, $body);
}

function send_admin_mail(array $booking): bool
{
    $subject = '【Find Pilates】新しい体験申込がありました';
    $body = <<<TEXT
新しい体験申込がありました。

お名前: {$booking['customer_name']}
フリガナ: {$booking['customer_kana']}
電話番号: {$booking['phone']}
メール: {$booking['email']}
年齢: {$booking['age']}
連絡方法: {$booking['contact_method_label']}

体験内容: {$booking['genre_label']}
日程: {$booking['booking_date_label']}
時間: {$booking['booking_time_label']}
担当: {$booking['instructor_name']}

運動経験:
{$booking['experience']}

体験歴:
{$booking['trial_history']}

既往歴チェック項目:
{$booking['medical_history_summary']}

既往歴の補足:
{$booking['medical_history_note']}

お悩み・相談:
{$booking['concern']}
TEXT;

    return send_mail_message(ADMIN_EMAIL, $subject, $body);
}
