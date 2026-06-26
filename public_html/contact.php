<?php
/**
 * FIND PILATES - お問い合わせフォーム処理
 * contact.php
 *
 * - 入力値のバリデーション
 * - CSVにサーバー保存（/data/contact_log.csv）
 * - findsportsclub@outlook.jp にメール転送
 * - JSON レスポンスを返す（JS側でハンドリング）
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// ── 設定 ─────────────────────────────────────
define('TO_EMAIL',    'findsportsclub@outlook.jp');
define('FROM_EMAIL',  'noreply@findpilates.jp');
define('FROM_NAME',   'FIND PILATES ウェブサイト');
define('SAVE_DIR',    __DIR__ . '/data');
define('CONTACT_CSV', SAVE_DIR . '/contact_log.csv');
// ─────────────────────────────────────────────

// POSTのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// CSRF 簡易チェック（Origin / Referer）
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

function hasTooManyUrls(string $text): bool {
    return preg_match_all('/https?:\/\/|www\./i', $text, $matches) >= 2;
}

function containsSpamKeyword(string $text): bool {
    return preg_match('/\b(casino|crypto|loan|seo|whatsapp|telegram|backlink|viagra)\b/i', $text) === 1;
}

function isEnglishOnlyLongText(string $text): bool {
    $plain = trim(preg_replace('/https?:\/\/\S+|www\.\S+/i', ' ', $text));
    if ($plain === '' || mb_strlen($plain, 'UTF-8') < 80) {
        return false;
    }

    return preg_match('/[\p{Hiragana}\p{Katakana}\p{Han}々]/u', $plain) !== 1;
}

$name    = sanitize($_POST['name']    ?? '');
$tel     = sanitize($_POST['tel']     ?? '');
$email   = sanitize($_POST['email']   ?? '');
$message = sanitize($_POST['message'] ?? '');
$website = trim((string)($_POST['website'] ?? ''));
$form_started_at = (int)($_POST['form_started_at'] ?? 0);

// 必須チェック
if ($name === '' || $email === '' || $message === '') {
    echo json_encode(['ok' => false, 'message' => '必須項目が未入力です。']);
    exit;
}

// メールアドレス形式チェック
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'メールアドレスの形式が正しくありません。']);
    exit;
}

// ヘッダーインジェクション対策
foreach ([$name, $tel, $email] as $field) {
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

if (preg_match('/https?:\/\/|www\./i', $name)) {
    echo json_encode(['ok' => false, 'message' => 'お名前欄にURLは入力できません。']);
    exit;
}

if (hasTooManyUrls($message)) {
    echo json_encode(['ok' => false, 'message' => '本文にURLを2つ以上含めることはできません。']);
    exit;
}

$spamSource = $name . ' ' . $message;
if (containsSpamKeyword($spamSource) || isEnglishOnlyLongText($message)) {
    echo json_encode(['ok' => false, 'message' => '送信内容を確認してください。']);
    exit;
}

$now = date('Y-m-d H:i:s');
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';

// ── CSVに保存 ─────────────────────────────────
if (!is_dir(SAVE_DIR)) {
    mkdir(SAVE_DIR, 0750, true);
    // .htaccess でディレクトリへの直接アクセスを禁止
    file_put_contents(SAVE_DIR . '/.htaccess', "Deny from all\n");
}

$write_header = !file_exists(CONTACT_CSV);
$fp = fopen(CONTACT_CSV, 'a');
if ($fp) {
    if ($write_header) {
        fputcsv($fp, ['日時', 'お名前', 'メールアドレス', '電話番号', 'お問い合わせ内容', 'IPアドレス']);
    }
    fputcsv($fp, [$now, $name, $email, $tel, $message, $ip]);
    fclose($fp);
}

// ── メール送信 ────────────────────────────────
$subject = '[FIND PILATES] お問い合わせがありました';

$body  = "FIND PILATESウェブサイトからお問い合わせがありました。\r\n";
$body .= str_repeat('─', 40) . "\r\n";
$body .= "受信日時　: {$now}\r\n";
$body .= "お名前　　: {$name}\r\n";
$body .= "メール　　: {$email}\r\n";
$body .= "電話番号　: " . ($tel ?: '未記入') . "\r\n";
$body .= str_repeat('─', 40) . "\r\n";
$body .= "お問い合わせ内容:\r\n{$message}\r\n";
$body .= str_repeat('─', 40) . "\r\n";
$body .= "※このメールはウェブサイトから自動送信されています。\r\n";

$headers  = "From: " . mb_encode_mimeheader(FROM_NAME, 'UTF-8') . " <" . FROM_EMAIL . ">\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: base64\r\n";

$encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$encoded_body    = chunk_split(base64_encode($body));

$sent = mail(TO_EMAIL, $encoded_subject, $encoded_body, $headers);

// ── 自動返信メール（送信者へ） ────────────────
$reply_subject = '=?UTF-8?B?' . base64_encode('[FIND PILATES] お問い合わせを受け付けました') . '?=';
$reply_body    = "{$name} 様\r\n\r\n";
$reply_body   .= "お問い合わせいただきありがとうございます。\r\n";
$reply_body   .= "以下の内容でお問い合わせを受け付けました。\r\n";
$reply_body   .= "通常2営業日以内にご返信いたします。\r\n\r\n";
$reply_body   .= str_repeat('─', 40) . "\r\n";
$reply_body   .= "お問い合わせ内容:\r\n{$message}\r\n";
$reply_body   .= str_repeat('─', 40) . "\r\n\r\n";
$reply_body   .= "FIND PILATES\r\n";
$reply_body   .= "〒329-2754 栃木県那須塩原市西大和1-8 そすいスクエアAQUAS内\r\n";
$reply_body   .= "TEL: 0287-36-0419\r\n";
$reply_body   .= "https://findpilates.jp\r\n";

$reply_headers  = "From: " . mb_encode_mimeheader(FROM_NAME, 'UTF-8') . " <" . FROM_EMAIL . ">\r\n";
$reply_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$reply_headers .= "Content-Transfer-Encoding: base64\r\n";

mail($email, $reply_subject, chunk_split(base64_encode($reply_body)), $reply_headers);

// ── レスポンス ────────────────────────────────
if ($sent) {
    echo json_encode(['ok' => true, 'message' => 'お問い合わせを受け付けました。']);
} else {
    // メール失敗でもCSV保存はできているので受付扱いにする
    echo json_encode(['ok' => true, 'message' => 'お問い合わせを受け付けました。（メール送信に問題が発生した場合はお電話ください）']);
}
