# Find Pilates 本番アップロード完全手順

この手順は、専門知識がなくても上から順に進められるように書いています。

作業日: 2026-06-23

## Xserver前提で使う画面

今回使うXserverの画面は主に3つです。

```text
1. Xserverアカウント
   契約サーバーを選ぶ入口です。

2. サーバーパネル
   MySQL設定、phpMyAdmin、バックアップ確認などを行う画面です。

3. ファイルマネージャ
   サーバー上のファイルをアップロード・ダウンロード・編集する画面です。
```

ログインURLが分からない場合:

1. Googleなどで `Xserver ログイン` と検索します
2. `Xserverアカウント ログイン` を開きます
3. XserverアカウントIDまたはメールアドレスでログインします

Xserverの画面でよく使うボタン名:

```text
サーバー管理
ファイル管理
サーバーパネル
MySQL設定
phpMyAdmin
ファイルマネージャ
アップロード
ダウンロード
新規作成
フォルダ作成
ファイル作成
編集
保存
インポート
エクスポート
実行
```

ボタン名が少し違う場合があります。

```text
実行 = Go
ファイルを選択 = 参照
保存 = Save
インポート = 読み込み
エクスポート = 書き出し
```

## Xserverでのフォルダ位置

Xserverでは、対象ドメインの中に `public_html` があります。

今回の想定:

```text
findpilates.jp/
  public_html/
    admin/
    app/
    trial/
    admission/
```

本番DB設定ファイルは、次の場所に作ります。

```text
findpilates.jp/
  config/
    findpilates.php
  public_html/
```

重要:

- `config/findpilates.php` は `public_html` の中ではありません。
- `findpilates.jp` フォルダの直下に `config` フォルダを作ります。
- つまり `public_html` と `config` が横並びになります。

## Xserver作業の全体順序

Xserverでは、次の順で作業します。

```text
1. Xserverアカウントへログイン
2. ファイル管理を開く
3. 本番ファイルをPCへバックアップ
4. サーバーパネルを開く
5. phpMyAdminを開く
6. DBをエクスポートしてバックアップ
7. SQLで現在件数をメモ
8. ファイル管理で config/findpilates.php を作成
9. phpMyAdminでマイグレーションSQLをインポート
10. phpMyAdminで admin_seed.sql をインポート
11. ファイル管理またはFTPで必要ファイルをアップロード
12. 管理画面へログイン確認
13. 旧データ確認
```

## Xserver 1. Xserverアカウントへログインする

1. ブラウザを開きます
2. `Xserver ログイン` と検索します
3. `Xserverアカウント ログイン` を開きます
4. メールアドレスまたはXserverアカウントIDを入力します
5. パスワードを入力します
6. `ログインする` ボタンを押します
7. 契約一覧が表示されます
8. 対象サーバーの行を探します

対象サーバーが分からない場合:

- ドメイン `findpilates.jp` が設定されているサーバーを選びます。
- 複数ある場合は、Xserverの「ドメイン設定」で `findpilates.jp` があるサーバーです。

## Xserver 2. ファイルマネージャを開く

1. Xserverアカウントにログインした状態にします
2. 対象サーバーの右側または下にある `ファイル管理` を押します
3. ファイルマネージャが開きます
4. 左側または中央の一覧から `findpilates.jp` を探します
5. `findpilates.jp` をクリックします
6. 中に `public_html` があることを確認します

ここで注意:

- まだファイルは削除しません。
- まだアップロードもしません。
- まずバックアップします。

## Xserver 3. 本番ファイルをバックアップする

### 方法A: Xserverファイルマネージャでバックアップする

ファイルマネージャに `ダウンロード` ボタンがある場合の方法です。

1. ファイルマネージャで `findpilates.jp` を開きます
2. `public_html` にチェックを入れます
3. `ダウンロード` ボタンを押します
4. ZIPで保存できる場合は、PCへ保存します
5. 保存先は分かりやすい場所にします

保存先フォルダ名例:

```text
backup_20260623_before_admin_upgrade
```

もし `public_html` フォルダごとのダウンロードができない場合は、方法Bを使います。

### 方法B: FTPソフトでバックアップする

FileZillaなどを使います。

1. FileZillaを開きます
2. 上部に次を入力します

```text
ホスト: XserverのFTPホスト名
ユーザー名: XserverのFTPユーザー名
パスワード: XserverのFTPパスワード
ポート: 21
```

3. `クイック接続` を押します
4. 右側で `findpilates.jp` を開きます
5. 右側で `public_html` を探します
6. 左側でPCのバックアップ保存先を開きます
7. 右側の `public_html` を左側へドラッグします
8. ダウンロード完了まで待ちます
9. 右側の `config` フォルダがある場合は、それも左側へドラッグします

確認:

```text
PC側に public_html のコピーがある
public_html/admin が入っている
public_html/trial が入っている
public_html/admission が入っている
```

## Xserver 4. サーバーパネルを開く

1. Xserverアカウントに戻ります
2. 対象サーバーの `サーバー管理` を押します
3. サーバーパネルが開きます

サーバーパネルで使う場所:

```text
データベース > MySQL設定
データベース > phpMyAdmin
```

## Xserver 5. 本番DB情報を確認する

1. サーバーパネルを開きます
2. `MySQL設定` をクリックします
3. `MySQL一覧` を開きます
4. `findpilates` に関係するDB名を探します
5. DB名をメモします
6. `MySQLユーザ一覧` を開きます
7. そのDBに権限があるユーザー名をメモします
8. `MySQL情報` があれば開きます
9. `MySQLホスト名` をメモします

メモ:

```text
DB名:
DBユーザー名:
DBパスワード:
MySQLホスト名:
```

DBパスワードが分からない場合:

- Xserverでは既存のMySQLユーザーのパスワードを画面で確認できないことがあります。
- 分からない場合は、Xserverの `MySQLユーザ設定` でパスワードを再設定する必要があります。
- パスワードを変更すると、既存サイトのDB接続設定も同じパスワードに変更する必要があります。
- 不安な場合は、ここで作業を止めてください。

## Xserver 6. phpMyAdminを開く

1. サーバーパネルを開きます
2. `phpMyAdmin` をクリックします
3. ログイン画面が出たら、MySQLユーザー名を入力します
4. MySQLパスワードを入力します
5. `実行` または `ログイン` を押します
6. 左側にDB一覧が出ます
7. 対象DBをクリックします

注意:

- ここで入力するのは管理画面の `find / Find0419` ではありません。
- MySQLユーザー名とMySQLパスワードです。

## Xserver 7. DBをバックアップする

phpMyAdminで行います。

1. 左側で対象DBをクリックします
2. 上部メニューの `エクスポート` をクリックします
3. `詳細` または `カスタム` を選びます
4. テーブルは全部選択されたままにします
5. 形式は `SQL` を選びます
6. 可能なら次をオンにします

```text
DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT コマンドを追加する
CREATE TABLE コマンドを追加する
INSERT コマンドを追加する
```

7. 画面下の `実行` ボタンを押します
8. SQLファイルがダウンロードされます
9. ファイル名を分かりやすく変更します

ファイル名例:

```text
findpilates_db_20260623_before_admin_upgrade.sql
```

## Xserver 8. DB件数をメモする

1. phpMyAdminで対象DBをクリックします
2. 上部の `SQL` をクリックします
3. 入力欄の中を空にします
4. 次をそのまま貼り付けます

```sql
SELECT COUNT(*) AS trial_slot_templates_count FROM trial_slot_templates;
SELECT COUNT(*) AS trial_slot_exceptions_count FROM trial_slot_exceptions;
SELECT COUNT(*) AS trial_bookings_count FROM trial_bookings;
SELECT COUNT(*) AS admin_users_count FROM admin_users;
```

5. `実行` ボタンを押します
6. 表示された数字をメモします

メモ欄:

```text
trial_slot_templates:
trial_slot_exceptions:
trial_bookings:
admin_users:
```

## Xserver 9. config/findpilates.php を作る

Xserverのファイルマネージャで作ります。

1. Xserverアカウントへ戻ります
2. 対象サーバーの `ファイル管理` を押します
3. `findpilates.jp` を開きます
4. `public_html` の中には入らず、`findpilates.jp` の直下にいることを確認します
5. `新規フォルダ` または `フォルダ作成` を押します
6. フォルダ名に次を入力します

```text
config
```

7. `作成` を押します
8. 作成した `config` フォルダを開きます
9. `新規ファイル` または `ファイル作成` を押します
10. ファイル名に次を入力します

```text
findpilates.php
```

11. `作成` を押します
12. `findpilates.php` を選択します
13. `編集` を押します
14. 次を貼り付けます
15. `本番DB...` の部分を、XserverのMySQL情報に置き換えます

```php
<?php
declare(strict_types=1);

return [
    'db_host' => '本番DBホスト名',
    'db_name' => '本番DB名',
    'db_user' => '本番DBユーザー名',
    'db_pass' => '本番DBパスワード',
    'admin_email' => 'findsportsclub@outlook.jp',
    'from_email' => 'info@findsports.jp',
    'from_name' => 'Find Pilates',
];
```

入力イメージ:

```php
'db_host' => 'mysql999.xserver.jp',
'db_name' => 'xs123456_findpilates',
'db_user' => 'xs123456_find',
'db_pass' => 'MySQLユーザーのパスワード',
```

16. `保存` を押します

注意:

- `Find0419` はここに入れません。
- `Find0419` は管理画面ログイン用です。
- `db_pass` にはXserverのMySQLユーザーのパスワードを入れます。

## Xserver 10. マイグレーションSQLを実行する

1. phpMyAdminへ戻ります
2. 左側で対象DBをクリックします
3. 上部の `インポート` をクリックします
4. `ファイルを選択` を押します
5. PC側の次のファイルを選びます

```text
database/migrations/20260623_trial_schedule_upgrade.sql
```

6. 画面下の `実行` を押します
7. 完了するまで待ちます

成功時の表示例:

```text
インポートは正常に終了しました
SQL は正常に実行されました
```

エラーが出た場合:

1. 次へ進みません
2. エラー文をコピーします
3. 画面のスクリーンショットを撮ります
4. こちらへエラー文を渡してください

## Xserver 11. admin_seed.sql を実行する

1. phpMyAdminで対象DBをクリックします
2. 上部の `インポート` をクリックします
3. `ファイルを選択` を押します
4. PC側の次のファイルを選びます

```text
admin_seed.sql
```

5. `実行` を押します
6. 完了メッセージを確認します

作成される管理ログイン:

```text
ID: find
PASS: Find0419
```

## Xserver 12. SQL実行後の件数確認

1. phpMyAdminで対象DBをクリックします
2. 上部の `SQL` をクリックします
3. 次を貼り付けます

```sql
SELECT COUNT(*) AS trial_slot_templates_count FROM trial_slot_templates;
SELECT COUNT(*) AS trial_slot_exceptions_count FROM trial_slot_exceptions;
SELECT COUNT(*) AS trial_bookings_count FROM trial_bookings;

SHOW COLUMNS FROM trial_slot_templates LIKE 'version';
SHOW COLUMNS FROM trial_slot_templates LIKE 'archived_at';
SHOW COLUMNS FROM trial_bookings LIKE 'version';
SHOW COLUMNS FROM trial_bookings LIKE 'contact_required';

SHOW TABLES LIKE 'trial_closures';
SHOW TABLES LIKE 'trial_audit_logs';
SHOW TABLES LIKE 'trial_schedule_templates';

SELECT id, username, display_name FROM admin_users ORDER BY id;
```

4. `実行` を押します

確認:

```text
trial_slot_templates の件数が作業前と同じ
trial_slot_exceptions の件数が作業前と同じ
trial_bookings の件数が作業前と同じ
admin_users に find がある
admin_users に admin がない
```

## Xserver 13. ファイルをアップロードする

Xserverファイルマネージャ、またはFTPソフトで行います。

初心者の方は、FTPソフトよりXserverファイルマネージャの方が画面上で分かりやすいです。ただし、フォルダごとの一括アップロードがやりにくい場合はFTPソフトを使ってください。

### Xserverファイルマネージャの場合

1. Xserverアカウントへ戻ります
2. `ファイル管理` を押します
3. `findpilates.jp` を開きます
4. `public_html` を開きます
5. アップロード先のフォルダを開きます
6. `アップロード` を押します
7. PC側のファイルを選びます
8. `アップロード` または `実行` を押します

アップロードするもの:

```text
public_html/admin/
public_html/app/
public_html/trial/
public_html/assets/css/admin.css
public_html/assets/css/trial.css
public_html/assets/css/trial-mobile.css
public_html/assets/css/trial-info.css
public_html/assets/js/admin.js
public_html/assets/js/trial.js
public_html/admission/admin.php
public_html/admission/confirm.php
public_html/admission/photo-preview.php
public_html/admission/send.php
public_html/admission/inc/config.php
public_html/admission/inc/functions.php
public_html/admission/tmp/.htaccess
public_html/data/.htaccess
public_html/data/sessions/.htaccess
```

### FTPソフトの場合

1. FileZillaを開きます
2. Xserverへ接続します
3. 右側で `findpilates.jp/public_html` を開きます
4. 左側でローカルの `public_html` を開きます
5. 必要なフォルダ・ファイルだけを右側へドラッグします

絶対にやらないこと:

```text
ローカルの public_html を丸ごとサーバーへドラッグしない
ミラーリング同期を使わない
サーバー側にしかないファイルを削除しない
```

## まず結論

本番アップロードは、次の順番で行います。

1. 本番ファイルをバックアップする
2. 本番DBをバックアップする
3. 本番DBの現在件数をメモする
4. 本番用DB設定ファイルを作る
5. DBマイグレーションを実行する
6. 管理ログインユーザーを作る
7. ファイルをアップロードする
8. 旧本番データが残っているか確認する
9. 管理画面と公開フォームを確認する

重要:

- `schema.sql` は既存本番DBには実行しません。
- `database/migrations/20260623_trial_schedule_upgrade.sql` を実行します。
- 旧本番の体験枠、予約、入会申込データを消さないようにします。
- ローカルの `config/findpilates.php` が本番DB情報になっている場合だけ、本番の `findpilates.jp/config/findpilates.php` として使います。
- `db_host`、`db_name`、`db_user`、`db_pass` が仮値のままならアップロードしません。

## 作業前に用意するもの

次の情報を手元に用意してください。

```text
レンタルサーバー管理画面URL:
レンタルサーバー管理画面ID:
レンタルサーバー管理画面パスワード:

FTPホスト名:
FTPユーザー名:
FTPパスワード:

phpMyAdmin URL または開き方:
DB名:
DBユーザー名:
DBパスワード:
DBホスト名:
```

注意:

- 管理画面ログインの `find / Find0419` と、DBユーザー名・DBパスワードは別物です。
- `config/findpilates.php` に入れるのはDB接続情報です。
- `Find0419` は管理画面ログイン用パスワードです。

## 絶対に上書きしない本番データ

次は本番側の実データです。ローカルのファイルで上書きしないでください。

```text
public_html/admission/tmp/admissions.json
public_html/admission/tmp/archive/
public_html/data/contact_log.csv
public_html/data/reminder_list.csv
public_html/data/sessions/sess_*
```

FTPソフトで「ローカルにないファイルをサーバーから削除する」「ミラーリング」「同期して削除」のような機能は使わないでください。

## 今回アップロードする主なファイル

アップロードするもの:

```text
public_html/admin/
public_html/app/
public_html/trial/
public_html/assets/css/admin.css
public_html/assets/css/trial.css
public_html/assets/css/trial-mobile.css
public_html/assets/css/trial-info.css
public_html/assets/js/admin.js
public_html/assets/js/trial.js
public_html/admission/admin.php
public_html/admission/confirm.php
public_html/admission/photo-preview.php
public_html/admission/send.php
public_html/admission/inc/config.php
public_html/admission/inc/functions.php
public_html/admission/tmp/.htaccess
public_html/data/.htaccess
public_html/data/sessions/.htaccess
```

通常アップロードしないもの:

```text
config/findpilates.php
public_html/data/sessions/sess_*
public_html/data/contact_log.csv
public_html/data/reminder_list.csv
public_html/admission/tmp/admissions.json
public_html/admission/tmp/archive/
```

## 1. 本番ファイルをバックアップする

### FTPソフトを使う場合

FileZillaなどのFTPソフトを開きます。

1. FTPソフトを起動します
2. 画面上部の入力欄に次を入力します

```text
ホスト: FTPホスト名
ユーザー名: FTPユーザー名
パスワード: FTPパスワード
ポート: 空欄、または 21
```

3. 「クイック接続」または「接続」ボタンを押します
4. 右側が本番サーバー、左側が自分のPCです
5. 右側で `public_html` を探します
6. 左側でバックアップ保存先フォルダを作ります

フォルダ名例:

```text
backup_20260623_before_admin_upgrade
```

7. 右側の `public_html` フォルダを、左側のバックアップフォルダへドラッグします
8. ダウンロードが終わるまで待ちます
9. 右側に `config` フォルダがある場合は、同じようにダウンロードします

確認:

- バックアップフォルダ内に `public_html` がある
- `public_html/admin` や `public_html/trial` が中にある
- エラー一覧が出ていない

### サーバーのファイルマネージャーを使う場合

1. レンタルサーバー管理画面へログインします
2. 「ファイルマネージャー」を開きます
3. `public_html` を選択します
4. 「圧縮」「ZIP作成」「アーカイブ」などのボタンを押します
5. ZIP名を次のようにします

```text
public_html_backup_20260623.zip
```

6. ZIPを作成します
7. 作成したZIPをPCへダウンロードします

## 2. 本番DBをバックアップする

phpMyAdminを使います。

1. レンタルサーバー管理画面へログインします
2. 「データベース」または「MySQL設定」を開きます
3. 「phpMyAdmin」を開きます
4. phpMyAdminにログインします
5. 左側の一覧から本番DB名をクリックします
6. 画面上部の「エクスポート」をクリックします
7. 「詳細」または「カスタム」を選びます
8. テーブルは「全選択」のままにします
9. 形式は `SQL` を選びます
10. 可能なら次にチェックを入れます

```text
DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT コマンドを追加する
CREATE TABLE コマンドを追加する
INSERT コマンドを追加する
```

11. 「実行」「エクスポート」「Go」のいずれかのボタンを押します
12. SQLファイルをPCに保存します

ファイル名例:

```text
findpilates_db_20260623_before_admin_upgrade.sql
```

確認:

- SQLファイルがPCに保存されている
- ファイルサイズが0KBではない

## 3. DBの現在件数をメモする

phpMyAdminで行います。

1. phpMyAdminで本番DBを選択します
2. 画面上部の「SQL」をクリックします
3. 入力欄に次をそのまま貼り付けます

```sql
SELECT COUNT(*) AS trial_slot_templates_count FROM trial_slot_templates;
SELECT COUNT(*) AS trial_slot_exceptions_count FROM trial_slot_exceptions;
SELECT COUNT(*) AS trial_bookings_count FROM trial_bookings;
SELECT COUNT(*) AS admin_users_count FROM admin_users;
```

4. 「実行」または「Go」ボタンを押します
5. 表示された数字をメモします

メモ欄:

```text
trial_slot_templates:
trial_slot_exceptions:
trial_bookings:
admin_users:
```

この数字は、マイグレーション後も基本的に変わりません。

## 4. 本番用DB設定ファイルを作る

ここがログインできるかどうかの重要ポイントです。

ファイルを作る場所:

```text
public_html の1つ上の階層
```

理想の配置:

```text
findpilates.jp/
  config/
    findpilates.php
  public_html/
    admin/
    app/
    trial/
```

### ファイルマネージャーで作る場合

1. レンタルサーバー管理画面を開きます
2. 「ファイルマネージャー」を開きます
3. `public_html` が見える階層へ移動します
4. `public_html` の中には入らず、同じ階層に `config` フォルダを作ります
5. `config` フォルダを開きます
6. 「新規ファイル」「ファイル作成」を押します
7. ファイル名を次にします

```text
findpilates.php
```

8. 作成した `findpilates.php` を編集します
9. 次の内容を貼り付けます
10. `本番DBホスト名` などの部分を実際の値へ置き換えます

```php
<?php
declare(strict_types=1);

return [
    'db_host' => '本番DBホスト名',
    'db_name' => '本番DB名',
    'db_user' => '本番DBユーザー名',
    'db_pass' => '本番DBパスワード',
    'admin_email' => 'findsportsclub@outlook.jp',
    'from_email' => 'info@findsports.jp',
    'from_name' => 'Find Pilates',
];
```

入力例:

```php
'db_host' => 'mysql999.example.ne.jp',
'db_name' => 'example_findpilates',
'db_user' => 'example_find',
'db_pass' => '実際のDBパスワード',
```

11. 「保存」ボタンを押します

注意:

- `config/findpilates.php` の `db_host`、`db_name`、`db_user`、`db_pass` が本番DB情報になっているか確認してください。
- 仮値のファイルはアップロードしないでください。
- 管理画面ログイン用パスワードとDBパスワードは別物です。ただし、今回DBパスワードとして指定された値は `db_pass` に入れます。

## 5. DBマイグレーションを実行する

既存データを残したまま、必要な列と新しい管理用テーブルを追加します。

1. phpMyAdminを開きます
2. 左側で本番DBをクリックします
3. 画面上部の「インポート」をクリックします
4. 「ファイルを選択」ボタンを押します
5. ローカルの次のファイルを選びます

```text
database/migrations/20260623_trial_schedule_upgrade.sql
```

6. 文字セットが選べる場合は `utf8mb4` または `utf-8` を選びます
7. 「実行」「インポート」「Go」のいずれかのボタンを押します
8. 完了メッセージが出るまで待ちます

成功時の目安:

```text
インポートは正常に終了しました
SQL は正常に実行されました
```

エラーが出た場合:

1. その場で作業を止めます
2. エラー文をコピーします
3. スクリーンショットを撮ります
4. 次のファイルアップロードへ進まないでください

## 6. 管理ログインユーザーを作る

続けて `admin_seed.sql` を実行します。

1. phpMyAdminで本番DBを選択したままにします
2. 画面上部の「インポート」をクリックします
3. 「ファイルを選択」ボタンを押します
4. ローカルの次のファイルを選びます

```text
admin_seed.sql
```

5. 「実行」「インポート」「Go」のいずれかのボタンを押します

このSQLで作られる管理ログイン:

```text
ID: find
PASS: Find0419
```

このSQLの動き:

- 旧 `admin` ユーザーがあれば `find` に変更します
- `find` がなければ作成します
- `find` のパスワードを `Find0419` に更新します
- 旧 `admin` ユーザーを削除します

体験予約データや入会申込データは削除しません。

## 7. DB反映後の確認

phpMyAdminで確認します。

1. 本番DBを選択します
2. 画面上部の「SQL」をクリックします
3. 次を貼り付けます

```sql
SELECT COUNT(*) AS trial_slot_templates_count FROM trial_slot_templates;
SELECT COUNT(*) AS trial_slot_exceptions_count FROM trial_slot_exceptions;
SELECT COUNT(*) AS trial_bookings_count FROM trial_bookings;

SHOW COLUMNS FROM trial_slot_templates LIKE 'version';
SHOW COLUMNS FROM trial_slot_templates LIKE 'archived_at';
SHOW COLUMNS FROM trial_bookings LIKE 'version';
SHOW COLUMNS FROM trial_bookings LIKE 'contact_required';

SHOW TABLES LIKE 'trial_closures';
SHOW TABLES LIKE 'trial_audit_logs';
SHOW TABLES LIKE 'trial_schedule_templates';

SELECT id, username, display_name FROM admin_users ORDER BY id;
```

4. 「実行」または「Go」を押します

確認すること:

- `trial_slot_templates` の件数が作業前と同じ
- `trial_slot_exceptions` の件数が作業前と同じ
- `trial_bookings` の件数が作業前と同じ
- `trial_slot_templates` に `version` がある
- `trial_bookings` に `version` がある
- `trial_closures` がある
- `trial_audit_logs` がある
- `admin_users` に `find` がある
- `admin_users` に `admin` がない

件数が減っていた場合:

- そこで作業を止めます。
- ファイルアップロードへ進まないでください。
- DBバックアップから戻す必要がある可能性があります。

## 8. ファイルをアップロードする

FTPソフトで行います。

1. FTPソフトを開きます
2. 本番サーバーへ接続します
3. 右側で `public_html` を開きます
4. 左側でローカルの `findpilates.jp` フォルダを開きます
5. 以下を1つずつアップロードします

### フォルダごとアップロードするもの

左側から右側へドラッグします。

```text
public_html/admin/
public_html/app/
public_html/trial/
```

上書き確認が出た場合:

- 「上書き」
- 「常にこの動作を使用」
- 「現在のキューにのみ適用」

のように選びます。

ただし、`public_html` 全体を丸ごとドラッグしないでください。

### ファイル単位でアップロードするもの

次のファイルをそれぞれ同じ場所へアップロードします。

```text
public_html/assets/css/admin.css
public_html/assets/css/trial.css
public_html/assets/css/trial-mobile.css
public_html/assets/css/trial-info.css
public_html/assets/js/admin.js
public_html/assets/js/trial.js
public_html/admission/admin.php
public_html/admission/confirm.php
public_html/admission/photo-preview.php
public_html/admission/send.php
public_html/admission/inc/config.php
public_html/admission/inc/functions.php
public_html/admission/tmp/.htaccess
public_html/data/.htaccess
public_html/data/sessions/.htaccess
```

### アップロードしてはいけないもの

次はアップロードしないでください。

```text
public_html/data/sessions/sess_*
public_html/data/contact_log.csv
public_html/data/reminder_list.csv
public_html/admission/tmp/admissions.json
public_html/admission/tmp/archive/
config/findpilates.php
```

## 9. 管理画面へログインする

ブラウザで開きます。

```text
https://findpilates.jp/admin/login.php
```

入力します。

```text
ユーザー名: find
パスワード: Find0419
```

「ログイン」ボタンを押します。

成功:

- ダッシュボードが表示されます。

失敗した場合:

### 「ログイン情報が正しくありません」

原因候補:

- `admin_seed.sql` が未実行
- 入力ミス

確認:

phpMyAdminで次を実行します。

```sql
SELECT id, username, display_name FROM admin_users ORDER BY id;
```

`find` がなければ、`admin_seed.sql` をもう一度実行します。

### 「ログイン処理に失敗しました」

原因候補:

- `config/findpilates.php` のDB情報が違う
- DBホスト名が違う
- DBユーザー名またはパスワードが違う
- DBマイグレーションが未実行

確認:

- `config/findpilates.php` の `db_host`
- `db_name`
- `db_user`
- `db_pass`

を見直します。

## 10. 旧本番データが反映されているか確認する

管理画面ログイン後に確認します。

### 体験予約

1. 左メニューまたは画面内の「体験予約」をクリックします
2. 旧本番の予約者が表示されるか確認します
3. 件数が0になっていないか確認します

### 体験枠管理

1. 「体験枠管理」または「スケジュール」をクリックします
2. 旧本番の体験枠が表示されるか確認します
3. 今後の日付に枠が出るか確認します

### 入会申込管理

1. 「入会申込管理」をクリックします
2. 旧本番の入会申込が表示されるか確認します
3. 申込詳細が開けるか確認します

表示されない場合:

- `public_html/admission/tmp/admissions.json` をローカルで上書きしていないか確認します
- 本番バックアップ内の `admissions.json` を確認します

## 11. 公開フォームを確認する

ブラウザで開きます。

```text
https://findpilates.jp/trial/
```

確認:

1. ページが表示される
2. カレンダーが表示される
3. 旧本番の体験枠が表示される
4. 日付を押せる
5. 時間枠を選べる

テスト送信する場合:

1. 運用に影響しにくい枠を選びます
2. テスト用の名前で申し込みます
3. 管理画面で予約が増えたことを確認します
4. そのテスト予約を「キャンセル」に変更します

## 12. 入会フォームを確認する

ブラウザで開きます。

```text
https://findpilates.jp/admission/
```

確認:

1. 入会フォームが表示される
2. 写真アップロードまたは撮影欄が表示される
3. 確認画面へ進める
4. 確認画面で顔写真プレビューが表示される

本番で実送信テストする場合は、店舗側に通知メールが届く可能性があります。

## 13. セキュリティ確認

ブラウザで次のURLを開きます。

```text
https://findpilates.jp/app/config.php
https://findpilates.jp/data/contact_log.csv
https://findpilates.jp/admission/tmp/admissions.json
```

正常な結果:

- 403 Forbidden
- アクセスできません
- 真っ白
- Not Found

危険な結果:

- PHPコードが見える
- CSVの中身が見える
- `admissions.json` の中身が見える

中身が見えた場合は、すぐに作業を止めてください。

## 14. 反映完了後の確認メモ

作業後にここへ記録します。

```text
作業日:
作業者:

DBバックアップファイル名:
ファイルバックアップフォルダ名:

マイグレーション実行: OK / NG
admin_seed.sql 実行: OK / NG

trial_slot_templates 件数:
trial_slot_exceptions 件数:
trial_bookings 件数:

管理ログイン: OK / NG
体験予約表示: OK / NG
体験枠表示: OK / NG
入会申込表示: OK / NG
公開体験フォーム表示: OK / NG
入会フォーム表示: OK / NG
```

## 15. トラブル時の戻し方

重大な問題が出た場合は、次の順に戻します。

### まず現在状態を保存する

障害発生後に新しい予約や申込が入っている可能性があります。

1. 現在の本番DBを追加でエクスポートします
2. 現在の `public_html/admission/tmp/admissions.json` をダウンロードします
3. 現在の `public_html/admission/tmp/archive/` をダウンロードします

### ファイルを戻す

1. FTPソフトを開きます
2. バックアップした `public_html` を開きます
3. 本番の `public_html` へ戻します
4. 上書き確認が出たら「上書き」を選びます

### DBを戻す

DBも戻す必要がある場合だけ実施します。

1. phpMyAdminを開きます
2. 本番DBを選択します
3. 「インポート」をクリックします
4. 作業前に保存したDBバックアップSQLを選びます
5. 「実行」を押します

注意:

- DBを戻すと、作業後に入った新しい予約が消える可能性があります。
- DB復元前に必ず現在DBを追加バックアップしてください。

## 16. よくあるエラー

### 管理ログインできない

確認する順番:

1. `config/findpilates.php` が本番DB情報になっているか
2. `admin_seed.sql` を実行したか
3. `admin_users` に `find` があるか
4. パスワードを `Find0419` と入力しているか

### 体験枠が表示されない

確認する順番:

1. `trial_slot_templates` に旧データが残っているか
2. `status` が `open` になっている枠があるか
3. 日付が予約受付期間内か
4. `trial_closures` で休館扱いになっていないか

### 入会申込が表示されない

確認する順番:

1. 本番の `public_html/admission/tmp/admissions.json` が残っているか
2. ローカルの空ファイルで上書きしていないか
3. ファイルの権限でPHPから読めるか

### `pdo_mysql` エラーが出る

意味:

PHPのMySQL接続ドライバが有効ではありません。

本番サーバーでは通常有効ですが、出た場合はサーバー管理画面でPHP設定を確認します。

## 17. 最終判定

次が全部OKなら、本番反映完了です。

- ファイルバックアップ済み
- DBバックアップ済み
- `config/findpilates.php` 作成済み
- マイグレーション実行済み
- `admin_seed.sql` 実行済み
- 旧体験枠が残っている
- 旧予約が残っている
- 旧入会申込が残っている
- `find / Find0419` でログインできる
- 公開体験フォームが表示される
- 入会フォームが表示される
- `app`、`data`、`admission/tmp` の中身が直接見えない
