# Find Pilates 体験予約管理画面 改修メモ

## 反映順序

1. 本番ファイルをバックアップする
2. MySQLをバックアップする
3. `config/findpilates.example.php` を参考に、公開ディレクトリ外の `config/findpilates.php` にDB接続情報を設定する
4. `database/migrations/20260623_trial_schedule_upgrade.sql` をphpMyAdminなどで実行する
5. `admin_seed.sql` を実行し、管理ユーザーを作成する
6. `public_html/admin/`、`public_html/app/`、`public_html/assets/css/admin.css`、`public_html/assets/js/admin.js` を反映する
7. `https://findpilates.jp/admin/` にログインし、体験枠管理を確認する

## 設定ファイル

DB接続情報は `public_html/app/config.php` に直接書かず、次のどちらかで設定します。

- 推奨: `config/findpilates.php`
- 代替: 環境変数 `FINDPILATES_DB_HOST`、`FINDPILATES_DB_NAME`、`FINDPILATES_DB_USER`、`FINDPILATES_DB_PASS`

`config/findpilates.php` は `public_html` の外側に配置してください。過去にDBパスワードをソースへ記載していた場合は、本番DBパスワードを変更してから反映してください。

## 管理ログイン

固定ID/PASSによるフォールバックログインは廃止しています。ログインは `admin_users` テーブルのみを参照します。テスト初期アカウントは `admin_seed.sql` を実行した場合のみ有効です。

## SQL実行前確認

```sql
SHOW TABLES LIKE 'trial_%';
SHOW COLUMNS FROM trial_slot_templates;
SHOW COLUMNS FROM trial_bookings;
```

## SQL実行後確認

```sql
SHOW TABLES LIKE 'trial_occurrences';
SHOW TABLES LIKE 'trial_audit_logs';
SHOW TABLES LIKE 'trial_closures';
SHOW COLUMNS FROM trial_slot_templates LIKE 'version';
SHOW COLUMNS FROM trial_bookings LIKE 'contact_required';
```

マイグレーションは `CREATE TABLE IF NOT EXISTS` と存在確認付きALTERで構成しているため、再実行しても同じ列を重複追加しません。

## 互換方針

- 公開側予約フォームは引き続き `trial_slot_templates`、`trial_slot_exceptions`、`trial_bookings` を使用します。
- 新しい週間カレンダーも既存データから表示します。
- 毎週・複数曜日は既存の繰り返しテンプレートとして保存します。
- 隔週、セルフエステ自動分割、前週コピー、CSV取込、テンプレート適用は既存スキーマでも正確に扱えるよう単発枠として保存します。
- 新設の `trial_occurrences` は将来的な実開催枠移行用です。今回の公開予約処理はまだこのテーブルを必須にしていません。

## ロールバック

ファイルを戻す場合は、バックアップした `public_html/admin/`、`public_html/app/`、`public_html/assets/` を復元してください。

DBは既存列を削除していないため、通常はロールバック不要です。新テーブルを使わない状態に戻すだけなら、アプリ側の旧ファイル復元で公開フォームは従来構造のまま動きます。
