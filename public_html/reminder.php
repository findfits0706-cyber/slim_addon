<?php
/**
 * FIND PILATES - 受付開始メールリマインダー登録処理
 * reminder.php
 *
 * - 入力値のバリデーション
 * - 重複チェック（同一メールアドレスの二重登録防止）
 * - CSVにサーバー保存（/data/reminder_list.csv）
 * - findsportsclub@outlook.jp に通知メール転送
 * - 登録者に確認メール自動返信
 * - JSON レスポンスを返す
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// ── 設定 ─────────────────────────────────────
define('TO_EMAIL',     'findsportsclub@outlook.jp');
define('FROM_EMAIL',   'noreply@findpilates.jp');
define('FROM_NAME',    'FIND PILATES');
define('SAVE_DIR',     __DIR__ . '/data');
define('REMINDER_CSV', SAVE_DIR . '/reminder_list.csv');
// ─────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// CSRF 簡易チェック
$allowed_host = 'findpilates.jp';
$origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (
    strpos($origin,  $allowed_host) === false &&
    strpos($referer, $allowed_host) === false
) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

// ── 入力取得・サニタイズ ──────────────────────
function sanitize(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

function containsSpamKeyword(string $text): bool {
    return preg_match('/\b(casino|crypto|loan|seo|whatsapp|telegram|backlink|viagra)\b/i', $text) === 1;
}

$name      = sanitize($_POST['name']      ?? '');
$email     = sanitize($_POST['email']     ?? '');
$dob       = sanitize($_POST['dob']       ?? '');
$area      = sanitize($_POST['area']      ?? '');
$interests = sanitize($_POST['interests'] ?? '');
$website   = trim((string)($_POST['website'] ?? ''));
$form_started_at = (int)($_POST['form_started_at'] ?? 0);

// 必須チェック
if ($name === '' || $email === '') {
    echo json_encode(['ok' => false, 'message' => 'お名前とメールアドレスは必須です。']);
    exit;
}

// メールアドレス形式チェック
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'メールアドレスの形式が正しくありません。']);
    exit;
}

// ヘッダーインジェクション対策
foreach ([$name, $email, $dob, $area] as $field) {
    if (preg_match('/[\r\n]/', $field)) {
        echo json_encode(['ok' => false, 'message' => '不正な入力が含まれています。']);
        exit;
    }
}

if ($website !== '') {
    echo json_encode(['ok' => false, 'message' => '送信内容を確認してください。']);
    exit;
}

$elapsed = time() - $form_started_at;
if ($form_started_at <= 0 || $elapsed < 3) {
    echo json_encode(['ok' => false, 'message' => '入力後、3秒ほど待ってから送信してください。']);
    exit;
}

$spamSource = $name . ' ' . $area . ' ' . $interests;
if (preg_match('/https?:\/\/|www\./i', $name) || containsSpamKeyword($spamSource)) {
    echo json_encode(['ok' => false, 'message' => '送信内容を確認してください。']);
    exit;
}

$now = date('Y-m-d H:i:s');
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';

// ── CSV 準備・重複チェック ────────────────────
if (!is_dir(SAVE_DIR)) {
    mkdir(SAVE_DIR, 0750, true);
    file_put_contents(SAVE_DIR . '/.htaccess', "Deny from all\n");
}

// 既存メールアドレスを確認
$already_registered = false;
if (file_exists(REMINDER_CSV)) {
    $fp = fopen(REMINDER_CSV, 'r');
    if ($fp) {
        fgetcsv($fp); // ヘッダー行スキップ
        while ($row = fgetcsv($fp)) {
            if (isset($row[1]) && strtolower($row[1]) === strtolower($email)) {
                $already_registered = true;
                break;
            }
        }
        fclose($fp);
    }
}

if ($already_registered) {
    // 二重登録でも正常扱い（情報漏洩防止）
    echo json_encode(['ok' => true, 'message' => '登録が完了しました。受付開始時にメールでお知らせします。']);
    exit;
}

// ── CSVに保存 ─────────────────────────────────
$write_header = !file_exists(REMINDER_CSV);
$fp = fopen(REMINDER_CSV, 'a');
if ($fp) {
    if ($write_header) {
        fputcsv($fp, ['登録日時', 'メールアドレス', 'お名前', '生年月日', 'お住まいのエリア', 'ご興味', 'IPアドレス']);
    }
    fputcsv($fp, [$now, $email, $name, $dob, $area, $interests, $ip]);
    fclose($fp);
}

// ── 管理者通知メール ──────────────────────────
$subject = '[FIND PILATES] 受付開始メール登録がありました';

$body  = "受付開始メール登録がありました。\r\n";
$body .= str_repeat('─', 40) . "\r\n";
$body .= "登録日時　: {$now}\r\n";
$body .= "お名前　　: {$name}\r\n";
$body .= "メール　　: {$email}\r\n";
$body .= "生年月日　: " . ($dob       ?: '未記入') . "\r\n";
$body .= "エリア　　: " . ($area      ?: '未記入') . "\r\n";
$body .= "ご興味　　: " . ($interests ?: '未選択') . "\r\n";
$body .= str_repeat('─', 40) . "\r\n";

$headers  = "From: " . mb_encode_mimeheader(FROM_NAME, 'UTF-8') . " <" . FROM_EMAIL . ">\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: base64\r\n";

$enc_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
mail(TO_EMAIL, $enc_subject, chunk_split(base64_encode($body)), $headers);

// ── 登録者への確認メール ──────────────────────
$reply_subject = '=?UTF-8?B?' . base64_encode('[FIND PILATES] 受付開始のお知らせ登録を受け付けました') . '?=';
$reply_body    = "{$name} 様\r\n\r\n";
$reply_body   .= "FIND PILATESの受付開始メール登録をいただきありがとうございます。\r\n\r\n";
$reply_body   .= "体験レッスン・ご入会の受付が開始した際に、\r\n";
$reply_body   .= "このメールアドレス宛にお知らせをお送りします。\r\n\r\n";
$reply_body   .= "2025年6月のオープンをどうぞお楽しみに。\r\n\r\n";
$reply_body   .= str_repeat('─', 40) . "\r\n";
$reply_body   .= "FIND PILATES\r\n";
$reply_body   .= "〒329-2754 栃木県那須塩原市西大和1-8 そすいスクエアAQUAS内\r\n";
$reply_body   .= "TEL: 0287-36-0419\r\n";
$reply_body   .= "https://findpilates.jp\r\n\r\n";
$reply_body   .= "※ このメールは自動送信です。返信はできません。\r\n";
$reply_body   .= "※ 登録解除をご希望の場合は上記TELまたは問い合わせフォームよりご連絡ください。\r\n";

$reply_headers  = "From: " . mb_encode_mimeheader(FROM_NAME, 'UTF-8') . " <" . FROM_EMAIL . ">\r\n";
$reply_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$reply_headers .= "Content-Transfer-Encoding: base64\r\n";

mail($email, $reply_subject, chunk_split(base64_encode($reply_body)), $reply_headers);

// ── レスポンス ────────────────────────────────
echo json_encode(['ok' => true, 'message' => '登録が完了しました。受付開始時にメールでお知らせします。']);
