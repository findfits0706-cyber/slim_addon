# Find Pilates × SLIM SNG
# ChatGPT 5.5 Codex向け 分割実装プロンプト集

## 使用方法

- プロンプト0から順番に、同じリポジトリへ適用する。
- 各プロンプトの終了時に、Codexの報告とテスト結果を確認してから次へ進む。
- 新しいCodexセッションで続ける場合も、先行工程が作成した `docs/slim-integration-spec.md` と `docs/slim-integration-progress.md` を最初に読ませる。
- 本番DBダンプ、秘密設定、顧客データをプロンプト本文や回答へ貼り付けない。
- 初期版はSLIMの最終「登録」を人が押す。完全自動化はプロンプト7まで行わない。

---

# プロンプト0：現状固定・設計仕様・安全な作業基盤

```text
あなたは、既存PHPシステム、MySQL、Microsoft Edge拡張機能、業務自動化を担当するシニアフルスタックエンジニアです。

Find Pilatesの公開入会フォーム、管理画面、Xserver API、Edge拡張機能、SLIM SNG転記連携を段階的に実装します。

この工程では、拙速に機能実装を始めず、現行リポジトリと提供されたSLIM保存HTMLを読み、以後の全工程が参照する設計仕様と安全な作業基盤を作ってください。ただし調査報告だけで終わらず、指定のドキュメント、テスト入口、設定テンプレートまでリポジトリへ実際に追加してください。

【環境】
- PHP 8.3.30
- MySQL 5.7
- Xserver
- 現行は独自PHPアプリ
- Microsoft Edge / Windows 11
- SLIM SNG：ISI Software
- SLIMログインURL：https://www.slim-sng.jp/slim/web/m/sng/login/
- WordPressへは移行しない

【最初に必ず行うこと】
1. git statusとリポジトリ構成を確認する。
2. 無関係な既存変更を巻き戻さない。
3. `.git`、`backups/`、本番ログ、メールデータ、秘密設定、実顧客データを出力・転載しない。
4. DBパスワード、管理者パスワードハッシュ、メール認証情報等を回答へ表示しない。
5. 現行コードの実行経路を確認し、バックアップファイルを修正対象にしない。
6. PHP 8.3とMySQL 5.7で動作しない機能を使用しない。
7. Composerや新フレームワークを前提にしない。既に存在する場合だけ既存方式を利用する。
8. WordPress化、大規模フレームワーク移行、SPA全面改修は行わない。

【現時点で確定している設計】
- 公開フォーム→MySQL→管理画面／専用API→Edgeサイドパネル→SLIMという構成。
- 初期版は、SLIM各画面で「この画面へ一括転記」を押し、最終登録ボタンはスタッフが押す。
- Edge拡張からMySQLへ直接接続しない。
- SLIMログインID・パスワードは保存しない。
- 申込候補一覧は、申込IDと申込日時を中心に表示する。
- 選択した申込者を明示的に固定し、別の対象へ誤転記しない。
- 空欄だけ自動入力し、既存値が異なる場合は上書きしない。
- 失敗時は項目を強調し、管理画面を「要確認」にして、個人情報値を含まない操作ログを残す。
- 顔写真は初期版では Downloads/FIND-SLIM/ へ保存する。
- 保存HTMLで確認できたファイル入力は公的身分証明書用であり、顔写真を設定してはいけない。

【SLIMページ】
提供済みの保存HTMLを確認し、次のページを識別してください。
- /slim/web/m/sng/front/admission_procedure/ ：入会受付
- /slim/web/m/sng/front/view_basic_user/ ：会員情報
- /slim/web/m/sng/front/view_image_survey/ ：写真・アンケート・その他
- /slim/web/m/sng/front/addition_notification/ ：追加届

保存HTMLではiframeが確認できなくても、実画面ではフレームを検査できる設計にしてください。
Nuxt/Vueの自動生成属性 `data-v-*` を安定セレクタとして使用しないでください。

【確定コース】
- 151 / FP：Find Pilatesベーシック単体
- 135 / FP2：Find Pilatesダブル単体
- 145 / P3：Pilatesベーシック併用オプション
- 146 / P3W：Pilatesダブル併用オプション
- 80 / MA：マスター会員
- 130 / DF：デイフリー会員
- 74 / GEH：ナイト＆ホリデイ会員
- 133 / A34：ナイト＆ホリデイ34才以下
- 140 / FM：ファインドメンバーズ
- 141 / P1：プール1
- 144 / S1：スタジオ1
- 追加届理由：9999 / その他
- 支払サイクル：月払い
- ウィークエンドは新規申込対象から削除

【登録操作モデル】
固定の4ステップではなく、申込ごとの順序付きoperationsとして設計してください。

1. Pilates単体
- basic：入会受付 151
- double：入会受付 135

2. 既存本館会員への追加
- basic：追加届 145
- double：追加届 146

3. 本館と同時入会
- find_master：入会受付80 → Pilates追加145/146
- day_free：入会受付130 → Pilates追加145/146
- night_holiday：入会受付74 → Pilates追加145/146
- night_holiday_u34：入会受付133 → Pilates追加145/146
- gym_free：入会受付140 → Pilates追加145/146
- gym_pool：入会受付140 → 追加届141 → Pilates追加145/146
- gym_studio：入会受付140 → 追加届144 → Pilates追加145/146

【この工程で作成するもの】
1. `docs/slim-integration-spec.md`
   - 目的
   - システム構成
   - 登録方式とoperations表
   - 料金ルール
   - データ境界
   - API境界
   - SLIMページ識別
   - セレクタ方針
   - エラー停止条件
   - 初期版と将来版の境界
2. `docs/slim-integration-progress.md`
   - 各フェーズのチェックリスト
   - 実装済み／未実装／実機確認待ち
3. `docs/slim-field-inventory.md`
   - 保存HTMLから確認できた画面、項目名、安定ID、入力種別、注意点
   - 未確定項目を推測で埋めない
4. `.gitignore`の確認・必要最小限の追記
   - 本番設定、申込写真、生成ログ、個人情報fixture、拡張機能の一時解析出力を追跡しない
5. テスト実行入口の確認
   - 既存テスト方式を記録
   - 後続工程で使うコマンドをドキュメント化
6. 秘密情報を含まない設定例
   - 既存 `config/findpilates.example.php` を壊さず、後続のAPI・トークン設定用キーを例示

【禁止】
- この工程で公開料金や保存方式を中途半端に変更しない。
- 生のSLIM保存HTML一式、vendor JS、実顧客情報を公開ディレクトリへコピーしない。
- 不明な「登録会員No.」の値を申込ID等から推測しない。
- 公的身分証明書file inputを顔写真欄と記述しない。

【完了報告】
- 調査した実行ファイル
- 作成・変更ファイル
- 現行の重大な不整合
- 未確定事項
- 次工程へ進める条件
- 実行したテスト／構文確認と結果
を簡潔に報告してください。
```

---

# プロンプト1：公開入会フォーム・料金エンジン・MySQL一本化

```text
リポジトリ内の `docs/slim-integration-spec.md` と `docs/slim-integration-progress.md` を最初に読み、内容を正として作業してください。

この工程では、Find Pilates公開入会フォームを確定仕様へ修正し、入会申込の保存先をJSONからMySQLへ一本化してください。調査だけで止まらず、入力、確認、送信、メール、写真、管理画面の読取互換まで実装し、テストしてください。

【環境と制約】
- PHP 8.3.30
- MySQL 5.7
- 既存独自PHP構成を維持
- WordPressへ移行しない
- PDOのネイティブプリペアドステートメントを使う
- utf8mb4
- 外部CDN依存を増やさない
- 無関係な体験予約機能を変更しない
- 本番設定値や秘密を出力しない
- `backups/` 内は修正しない

【現行で必ず確認するファイル】
- `public_html/admission/index.php`
- `public_html/admission/confirm.php`
- `public_html/admission/send.php`
- `public_html/admission/inc/config.php`
- `public_html/admission/inc/functions.php`
- `public_html/admission/js/app.js`
- `public_html/admission/css/style.css`
- `public_html/admin/admissions.php`
- `public_html/app/config.php`
- `public_html/app/db.php`
- `public_html/app/auth.php`
- `public_html/app/csrf.php`
- 既存schemaとmigration

【最優先の修正】
1. 現在の `admission/tmp/admissions.json` を本番保存先として使用しない。
2. 申込をMySQLへ保存してからメール送信する。
3. 管理者メール送信失敗で申込自体が消える現行挙動を廃止する。
4. 再送信や戻る操作で二重申込を作らないidempotencyキーを実装する。
5. JSON読書き関数が他から使われている場合は、MySQL Repositoryを呼ぶ互換ラッパーにするか、全呼出元を安全に置換する。
6. 未運用だが既存JSONが存在する場合に備え、dry-run可能な一回限りインポートCLIを用意する。原JSONは削除しない。

【料金の確定値】
Find Pilates単体：
- basic：8,800円、月8回
- double：12,650円、月16回

本館併用追加：
- basic：3,850円、月8回
- double：7,700円、月16回

Pilates部分の初月週割：
- 1～7日：100%、8回／16回
- 8～14日：75%、6回／12回
- 15～21日：50%、4回／8回
- 22日～月末：25%、2回／4回
- 1円未満は `PHP_ROUND_HALF_UP`

期待値：
- 8,800 × 75% = 6,600
- 12,650 × 75% = 9,488
- 12,650 × 25% = 3,163
- 3,850 × 75% = 2,888
- 3,850 × 25% = 963
- 7,700 × 75% = 5,775

本館部分：
- 既存本館会員：本館初月会費を請求合計へ加算しない
- 本館へ同時入会：本館月会費だけ既存の日割り計算を維持
- 本館会費とPilates追加料金を別コンポーネントとして保存・表示

料金計算は、PHP側の一つの純粋な料金サービス／関数を正としてください。JavaScriptはPHPから出力された設定を使ってプレビューし、POSTされた金額や割合を信用しないでください。

【公開プラン】
表示する本館種別：
- find_master
- day_free
- night_holiday
- night_holiday_u34
- gym_free
- gym_pool
- gym_studio

`weekend` は以下から削除してください。
- 公開UI
- 設定の有効プラン
- バリデーション許可リスト
- 料金計算
- メール
- 確認画面
- 新規保存
- JavaScript
- テストデータ

ただし旧データを破壊しないため、読み込み時にweekendがあれば「旧プラン・要確認」として表示できる互換性を残してください。新規送信は拒否してください。

【公開フォーム項目】
SLIMへ安全に転記できるよう次を実装してください。

- surname：姓、必須、最大28文字
- given_name：名、必須、最大28文字
- surname_kana：セイ、必須、全角カタカナ、最大28文字
- given_name_kana：メイ、必須、全角カタカナ、最大28文字
- name、kanaは派生値として後方互換用に生成可能
- birth：`input type=date` を第一入力方法とする
- gender：必須、男性／女性／その他
- phone_type：必須、mobile／home
- phone：必須、正規化保存
- email：必須
- postal_code
- prefecture
- city_area
- street_address
- building
- emergency_name
- emergency_relationship
- emergency_phone
- guardian_name：未成年時に条件必須

住所はSLIMの住所1～3へ対応できるよう、以下の最大長をサーバー側でも検証してください。
- city_area：30文字相当
- street_address：30文字相当
- building：30文字相当

【年齢】
- 未来の生年月日は不可
- 15歳未満は不可
- 15歳前後の学校区分確認を既存仕様に合わせて維持
- ナイト＆ホリデー34才以下は、既存の正式ルールがコード内にない場合、利用開始日時点で34歳以下を初期ルールとし、設定値として一か所に置く
- 35歳以上なら送信前に明確なエラーを表示し、別コースへ自動変更しない

【初月利用回数】
- 申込者が選ぶ `initial_visits` UIを削除する
- 利用開始日から自動算出する
- 保存時は計算スナップショットとして回数・割合・区分を保存する
- 旧POST値を信用しない

【日付】
- 利用開始希望日と第1～第3来店希望を区別する
- 実際の店頭手続日は公開フォームで取得しない
- 日曜日は公開されている受付終了時刻を超える枠を表示しない
- 現行の19:30～20:30等、営業時間を超える枠を修正する
- 休館日設定を一か所で管理する

【MySQLテーブル】
既存命名・migration方針を確認した上で、最低限次を作成してください。

1. `admissions`
- bigint主キー
- application_id varchar 一意
- application_status
- slim_status
- use_type
- main_member_status
- main_member_number
- slim_member_number
- actual_procedure_date nullable
- start_date
- course_key / main_membership_key / addon_key
- surname / given_name / surname_kana / given_name_kana
- birth / gender / phone_type / phone / email
- 住所・緊急連絡先・保護者
- original_payload JSON
- normalized_payload JSON
- fee_snapshot JSON
- admin_note
- version int default 1
- created_at / updated_at

2. `admission_sensitive`
- admission_id 一意FK相当
- health_payload JSON
- terms_snapshot JSON
- created_at / updated_at

3. `admission_preferences`
- admission_id
- preference_order 1～3
- preferred_date
- preferred_time
- 一意制約

4. `admission_photos`
- admission_id
- storage_path
- original_filename
- mime_type
- file_size
- sha256
- created_at

MySQL 5.7で実効性のないCHECK制約へ依存せず、PHP側でも必ず検証してください。外部キーを既存方針上使わない場合は、削除整合性をRepositoryで保証し、理由を記録してください。

【写真】
- 保存先をpublic_html外または直接配信不能な保護領域にする
- 画像実体、MIME、容量、寸法を検証
- ランダムな保存名
- パスをHTMLへ露出しない
- 既存の一時写真を確実に掃除する
- 申込DB保存と写真メタデータ保存の整合性を保つ

【送信順序】
推奨順：
1. CSRF・入力・写真検証
2. サーバー料金再計算
3. DBトランザクションで申込、希望日、機微情報、写真メタデータを保存
4. commit
5. 管理者メール・控えメール送信
6. mail_statusを更新
7. メール失敗でも「申込は保存済み」と明示
8. 同じidempotencyキーで再POSTされた場合は既存申込IDを返し、重複作成しない

【管理画面互換】
この工程の終了時点で、既存 `admin/admissions.php` がMySQLの申込を一覧・編集できる最低限の互換性を持たせてください。詳細なSLIM UIは次工程で実装します。

【テスト】
既存のテスト方式に合わせて、最低限次を自動テストしてください。

料金：
- 単体basic／double × 1,8,15,22日
- 併用basic／double × 1,8,15,22日
- 既存本館会員で本館初月料金が0
- 同時入会で本館だけ日割り
- 四捨五入境界
- 改ざんPOST金額が無視される

入力：
- 姓名・カナ分割
- gender未入力
- phone_type未入力
- 15歳未満
- 未来日
- 34歳以下コース境界
- weekend新規拒否
- 住所長超過
- 重複希望日時

保存：
- 申込保存後にメール失敗してもDBに残る
- 同一idempotencyキーで重複しない
- JSONインポートdry-run
- 写真不正MIME／大容量

【UI】
既存の暖色系ベージュ／ブラウン、白いカード、6段階フォームというデザインコンセプトは維持してください。全面的なブランド変更は不要です。

- スマホ1列
- PCは料金サマリーsticky
- 必須／任意を常時表示
- エラーは項目直下＋上部概要
- 最初のエラーへフォーカス
- 戻っても入力を維持
- 健康情報・顔写真をlocalStorageへ保存しない

【完了条件】
- JSONを本番保存先として使っていない
- 公開申込→確認→DB保存→完了→管理画面表示が通る
- 料金期待値が全て自動テストで通る
- ウィークエンドが新規UI・送信から消えている
- 旧料金文字列をリポジトリの実行対象から検索し、意図しない残存がない
- PHP構文エラー、ブラウザコンソールの重大エラーがない

【完了報告】
- migrationファイル
- 変更ファイル
- DB反映手順とロールバック
- 料金計算例
- JSON互換／移行方法
- テストコマンドと結果
- 人が確認すべき画面
を報告し、`docs/slim-integration-progress.md` を更新してください。
```

---

# プロンプト2：管理画面・正規化データ・SLIM操作キュー

```text
`docs/slim-integration-spec.md`、`docs/slim-integration-progress.md`、前工程のmigrationとRepositoryを読んでから作業してください。

この工程では、管理画面を「申込内容を見る画面」から「SLIM登録の準備・補正・進捗管理を行う業務画面」へ改善し、申込ごとの順序付きSLIM操作キューを実装してください。

調査だけで終わらず、DB、PHP、管理画面UI、監査ログ、テストまで実装してください。

【重要原則】
- 申込対応ステータスとSLIMステータスを分離する。
- 固定のSTEP 1～4を全員へ表示しない。
- 申込内容から必要なoperationsを決定する。
- コースIDをテンプレートへ散在させない。
- SLIMへ送る値と申込原本を区別する。
- 健康情報をSLIM転記データへ含めない。
- 不明値を推測で補わない。

【申込対応ステータス】
例：
- new
- contacting
- scheduled
- visited
- cancelled

既存値との互換性を維持し、表示ラベルは日本語にしてください。

【SLIMステータス】
- not_started
- preparing
- in_progress
- needs_review
- completed

単一の既存 `registered` へ統合しないでください。

【転記用データ】
- `original_payload` は申込時の原本として通常編集しない。
- `normalized_payload` はSLIM転記用として管理画面で修正可能。
- 姓、名、カナ、電話種別、住所分割等を明示的に持つ。
- 修正時はbefore/after、担当者、日時を監査する。
- 値全文を通常のアプリログへ出さない。

【実手続日】
- `actual_procedure_date` を管理画面で必須設定する。
- 初期表示は当日候補でもよいが、自動保存しない。
- 第1～第3希望日は候補日に過ぎず、SLIMの入会日・追加届申込日に自動採用しない。
- 実手続日が未確定ならoperationをreadyにしない。

【電話番号種別】
- mobile：SLIMの携帯TELへ
- home：SLIMの自宅TELへ
- 未設定なら転記準備不可
- 同じ番号を両方へ入れない

【コース設定】
1か所の設定またはCatalogクラスに以下を持たせてください。

- internal_key
- course_id
- code
- business_label
- slim_option_texts（完全一致候補の配列）
- page_type
- active

初期候補：
- 151, FP, business=ピラティスベーシック会員, SLIM候補=`FP：ファインドピラティス`
- 135, FP2, business=ピラティスダブル会員, SLIM候補=`FP2：ピラティスダブル会員`
- 145, P3, business=ピラティスベーシック併用, SLIM候補=`P３：ピラティス３`
- 146, P3W, business=ピラティスダブル併用, SLIM候補=`P３W：ピラティス３W`
- 80, MA, `MA：マスター会員`
- 130, DF, `DF：デイフリー会員`
- 74, GEH, `GEH：ナイト＆ホリデイ会員`
- 133, A34, `A34：ナイト＆ホリデイ(34才以下)`
- 140, FM, `FM：ファインドメンバーズ`
- 141, P1, `P1：プール１`
- 144, S1, `S1：スタジオ１`

全角数字や空白差異を勝手に正規化して曖昧一致しないでください。追加候補は明示的な許可リストとして登録してください。

【操作キュー生成】
純粋関数またはサービスとして次を実装してください。

`build_slim_operations(array $normalizedAdmission): array`

ケース：
A. Pilates単体basic
- 1: admission_procedure / course 151

B. Pilates単体double
- 1: admission_procedure / course 135

C. 既存本館＋basic
- 1: addition_notification / course 145

D. 既存本館＋double
- 1: addition_notification / course 146

E. 同時入会 find_master/day_free/night_holiday/night_holiday_u34/gym_free
- 1: admission_procedure / 対応する本館コース
- 2: addition_notification / 145または146

F. 同時入会 gym_pool
- 1: admission_procedure / 140
- 2: addition_notification / 141
- 3: addition_notification / 145または146

G. 同時入会 gym_studio
- 1: admission_procedure / 140
- 2: addition_notification / 144
- 3: addition_notification / 145または146

weekend等の旧プラン：
- operationsを自動生成しない
- needs_review
- 理由を表示

【追加届固定値】
- application_date = actual_procedure_date
- reason_id = 9999
- reason_label = その他
- start_date = 利用開始希望日
- payment_cycle = monthly
- payment_cycle_label = 月払い

【DB】
既存migration方針に合わせて、最低限次を追加してください。

1. `admission_slim_operations`
- id
- admission_id
- sequence_no
- operation_key
- operation_type
- page_type
- course_id
- course_code
- business_label
- slim_option_texts JSON
- reason_id nullable
- payment_cycle nullable
- status
- attempts
- last_error_code
- last_error_summary
- started_at/by
- filled_at/by
- completed_at/by
- created_at/updated_at
- admission_id + sequence_no 一意

2. `admission_slim_events`
- request_id
- admission_id
- operation_id nullable
- actor_admin_id nullable
- extension_installation_id nullable
- action
- result_json（件数・コードのみ。PII値を保存しない）
- page_profile_version nullable
- created_at

3. 必要なら `admission_locks`
- admission_id一意
- owner_admin_id
- installation_id
- acquired_at
- heartbeat_at
- expires_at

【キュー再生成】
管理画面でプランや登録方式を修正した時：
- 未着手operationは安全に再生成可能
- 既にfilled/completedのoperationがある場合は自動削除・自動置換しない
- 差異を提示し、要確認にする
- トランザクションとversionを使う

【管理画面一覧】
既存デザインを維持しつつ以下を追加してください。
- SLIMステータス
- 完了数／総operation数
- 未着手／登録中／要確認／登録済みフィルター
- 処理中ロック
- 未完了件数

【詳細上部】
- 申込ID
- 申込日時
- 氏名
- 登録方式
- 実手続日
- 利用開始日
- 既存本館会員番号
- SLIM会員番号
- 電話種別
- 申込対応ステータス
- SLIMステータス
- 不足情報

【原本と転記値UI】
通常は転記値を表示し、原本との差異がある項目だけ次の形式で表示してください。

申込者入力：山﨑
SLIM転記値：山崎
変更者・変更日時

全項目を二重に並べて画面を長くしないでください。

【操作キューUI】
例：
1. 本館入会受付 FM / 140
2. 本館オプション追加 P1 / 141
3. Pilates追加 P3 / 145

各カードに：
- 対象ページ
- コース
- 前提条件
- 状態
- 転記日時／担当者
- 登録完了日時／担当者
- 最終エラー
- 再実行回数

「コピーして次へ」中心の旧UIは補助として残してもよいが、主導線はEdge拡張連携にしてください。

【事前検証】
`validate_slim_readiness()` を実装し、最低限次を検査してください。
- 姓・名
- セイ・メイ
- 生年月日
- 性別
- 電話
- 電話種別
- 利用開始日
- 実手続日
- 有効なoperations
- 既存本館会員の会員番号
- 同時入会の本館コース
- コース候補設定
- 写真有無は警告。必須停止にするかは設定可能

【SLIM会員番号】
- `main_member_number` と `slim_member_number` を別フィールドとして維持
- 意味が同じと確認できるまで自動統合しない
- 同時入会後の追加届は、必要な会員番号が保存されるまでreadyにしない

【監査】
既存 `trial_audit_logs` へ無理に混在させず、入会・SLIM用イベントへ分離してください。既存共通関数を再利用する場合も、テーブル名と機微情報の扱いを明確にしてください。

【テスト】
以下のoperations配列を厳密にテストしてください。
- 単体basic：151のみ
- 単体double：135のみ
- 既存＋basic：145のみ
- 既存＋double：146のみ
- 同時find_master＋basic：80,145
- 同時day_free＋double：130,146
- 同時night_holiday＋basic：74,145
- 同時u34＋double：133,146
- 同時gym_free＋basic：140,145
- 同時gym_pool＋basic：140,141,145
- 同時gym_pool＋double：140,141,146
- 同時gym_studio＋basic：140,144,145
- 同時gym_studio＋double：140,144,146
- weekend：生成せずneeds_review
- 実手続日なし：ready不可
- 既存会員番号なし：追加届ready不可
- 完了済operationがある状態でプラン変更：自動破壊しない

【完了条件】
- 申込ごとに正しい1～3件のoperationsがDBへ保存される
- 管理画面で順序と状態が一目で分かる
- 原本と転記値を区別できる
- 申込対応とSLIM状態が独立
- 不足情報を開始前に表示
- 旧weekendを誤登録できない

変更ファイル、migration、操作キュー例、テスト結果、未確定事項を報告し、進捗ドキュメントを更新してください。
```

---

# プロンプト3：Xserver専用API・短期トークン・ロック・写真配信

```text
先行工程の設計書、DB、Repository、operations実装を読み、この工程ではEdge拡張機能専用APIと安全なペアリング認証を実装してください。

Edge拡張からMySQLへ直接接続してはいけません。固定APIキーやDB接続情報を拡張ソースへ埋め込んではいけません。

【環境】
- PHP 8.3.30
- MySQL 5.7
- Xserver
- 既存管理者認証を再利用
- APIはJSONのみ
- WordPress化しない

【設計】
管理画面でログイン済みのスタッフが、一回限り・5分有効のペアリングコードを発行する。
Edge拡張は、コードとinstallation_idを送って8時間有効のBearer tokenへ交換する。

DBへ保存するのはコード・トークンのSHA-256等のハッシュだけとし、平文トークンを再表示できない設計にしてください。

【必要テーブル】
1. `extension_pairing_codes`
- id
- code_hash
- admin_user_id
- expires_at
- used_at
- created_at
- requester情報は最小限

2. `extension_access_tokens`
- id
- token_hash
- admin_user_id
- installation_id
- extension_version
- expires_at
- revoked_at
- last_used_at
- created_at

installation_idは拡張側で生成するランダムUUIDであり、端末固有ハードウェアIDを収集しないでください。

【管理画面】
- 「Edgeを接続」ボタン
- 5分有効のコードを一度だけ表示
- 発行者、期限を表示
- 有効トークン一覧
- 端末名ではなくinstallation_id末尾等を安全に表示
- 個別失効
- 全端末失効
- CSRF必須
- 発行・失効を監査

【API基本パス】
既存ルーティングに合わせて、例として `/api/v1/extension/` 配下に実装してください。

必要エンドポイント：

1. `POST /pair`
入力：pairing_code, installation_id, extension_version
出力：access_token, expires_at, staff display name
- コードは一回限り
- 期限切れ・使用済み・総当たりを拒否
- 成功時にused_at

2. `GET /me`
- 接続スタッフ
- token期限
- APIバージョン

3. `GET /admissions`
query：
- scope=today|unregistered|in_progress|search
- q
- cursorまたはpage

一覧レスポンスは最小限：
- application_id
- submitted_at
- slim_status
- operation_progress

ユーザー指定により、一覧の主表示は申込IDと申込日時とする。氏名・電話番号等は一覧レスポンスへ不要に含めない。

4. `GET /admissions/{applicationId}/transfer`
対象選択後だけ詳細を返す。
- application_id
- submitted_at
- display_name
- workflow label
- actual_procedure_date
- start_date
- main_member_number
- slim_member_number
- normalized transfer fields
- operations
- photo availability
- readiness errors/warnings
- version

返さない：
- health_payload
- terms詳細
- admin_note
- DB内部パス
- SLIM認証情報
- 不要な料金・キャンペーン内部情報

5. `POST /admissions/{applicationId}/lock`
- versionを受け取る
- ロック取得
- 競合時409
- 既存ロック担当の表示名と期限だけを返す

6. `POST /admissions/{applicationId}/heartbeat`
- ロック延長

7. `DELETE /admissions/{applicationId}/lock`
- 自分のロックだけ解除

8. `POST /operations/{operationId}/fill-result`
入力：
- request_id
- page_type
- page_profile_version
- counts: filled, matched, skipped_nonempty, differences, missing, errors
- error_codes
- extension_version

入力値全文やPIIをログへ送らせない。
同じrequest_idは冪等に処理する。

9. `POST /operations/{operationId}/complete`
- スタッフがSLIM登録完了を確認した時だけ
- 前提operation未完了なら拒否
- actor、時刻を保存

10. `POST /admissions/{applicationId}/member-number`
- 候補値の形式検証
- version/lock確認
- 変更履歴
- 既存値と異なる場合は409または明示確認フラグを要求

11. `POST /admissions/{applicationId}/photo-token`
- 1回限り、短時間有効の署名トークンまたはランダムトークン

12. `GET /photos/{token}`
- 期限・使用回数・権限確認
- Content-Type
- Content-Disposition filenameは申込IDまたはSLIM会員番号のみ
- `Cache-Control: no-store`
- 実パスを返さない
- path traversal不可

【HTTP仕様】
- JSON UTF-8
- 成功・失敗とも一貫したenvelope
- 400：形式不正
- 401：未認証／期限切れ
- 403：権限なし
- 404：対象なし
- 409：ロック／version競合／状態競合
- 422：業務前提不足
- 429：試行過多
- 500：内部エラー。詳細をクライアントへ漏らさない
- 全APIに `Cache-Control: no-store`
- リクエストIDを返す

【CORSと配信元】
- 拡張ID確定後は許可する `chrome-extension://<id>` を設定で限定できる構造
- Originだけを認証手段にしない
- 開発時の許可配信元を本番へ残さない
- host_permissionsとBearer tokenを併用する

【トークン】
- 十分なランダム長
- 平文をログへ出さない
- Authorizationヘッダー以外のURL queryへ入れない
- last_used_at更新は過剰な書込みを避ける
- 管理者無効化・退職時に失効可能

【ロック】
- 初期ロック期限は例として10分
- heartbeatで延長
- ブラウザ終了等で残っても期限切れになる
- ロックは誤登録防止であり、DBトランザクション・version検証も併用

【検索】
検索qで氏名・電話・申込IDを内部検索できても、返却一覧には申込ID・申込日時中心の最小情報だけを返してください。ログへ検索語をそのまま残さないでください。

【セキュリティ】
- SQL injection対策
- 認可を各endpointで共通middleware化
- JSON body size上限
- ペアリング総当たり制限
- API例外に秘密を含めない
- health/admin_noteを返すテストを作る
- 写真tokenの期限・再利用・他申込アクセスをテスト
- HTTPログにAuthorizationを出さない設定／注意事項を文書化

【テスト】
- 正常pair
- 期限切れcode
- code再利用
- 間違いcode連続
- token期限切れ／失効
- 別installation
- 一覧最小レスポンス
- 詳細にhealth/admin_noteがない
- ロック競合
- heartbeat
- version競合
- operation順序違反
- fill-result冪等
- member number差異
- photo token期限、再利用、path traversal
- no-storeヘッダー

【ドキュメント】
- `docs/extension-api.md`
- endpoint、request/response例
- error code
- ペアリング手順
- token失効手順
- curl例には架空値のみ

【完了報告】
- migration
- endpoint一覧
- 認証フロー
- セキュリティ対策
- テスト結果
- Xserver配備時の設定項目
- 拡張実装へ渡すAPI base URLと契約
を報告し、進捗を更新してください。
```

---

# プロンプト4：Edge拡張基盤・サイドパネルUI・SLIM画面解析モード

```text
リポジトリの設計書と `docs/extension-api.md` を読み、Microsoft Edge向けManifest V3拡張機能を新規実装してください。

この工程では、まだ本番入力を自動実行しません。先に安全なサイドパネル、API接続、対象者固定、SLIMページ判定、DOM解析モード、dry-runまで完成させてください。

【対象】
- Windows 11
- Microsoft Edge
- 初期検証は展開して読み込み
- サイドパネル方式
- SLIMホスト：https://www.slim-sng.jp/
- APIホスト：https://findpilates.jp/ の既存設定から取得

【ディレクトリ例】
`edge-extension/`
- manifest.json
- service-worker.js
- sidepanel/
- content/
- core/
- mappings/
- styles/
- icons/
- tests/
- fixtures/
- README.md

既存のリポジトリ規則に合わせて調整可能ですが、UI、API client、page detector、inspector、state、mappingを分離してください。

【Manifest V3】
必要最小限の権限：
- sidePanel
- scripting
- storage
- tabsまたはactiveTabの必要な方
- downloadsは次工程で使用するが、先に追加する場合は理由をREADMEへ記載

host_permissions：
- `https://www.slim-sng.jp/*`
- Xserver APIの正確なorigin

`<all_urls>` は使用しないでください。
外部CDN・remote code・evalは禁止です。

【サイドパネルの状態】
1. 未接続
- ペアリングコード入力
- 接続

2. 接続済み・未選択
- スタッフ名
- token期限
- タブ：今日予定／未登録／登録中／検索
- 一覧行は申込ID、申込日時、進捗程度

3. 対象選択済み
- 対象会員を固定
- 氏名
- 申込ID
- 登録方式
- 実手続日
- 利用開始日
- operation進捗
- 現在のSLIM画面
- readiness warning
- この工程では「解析」「dry-run」を主ボタンにする

4. ログイン切れ
- SLIMログインページを検知
- 再ログイン案内
- パスワード入力機能を作らない

5. API期限切れ
- 個人情報を画面から消す
- 再ペアリングへ誘導

【トークン保存】
- access tokenは永続local storageへ保存しない
- 利用可能なら `chrome.storage.session` を使う
- installation_idや非機微設定だけlocal storage可
- 選択中のPIIを永続保存しない
- side panel再描画時は必要に応じAPIから再取得
- logout／失効時に全て消去

【対象者固定とロック】
- 申込を選択したらAPI lockを取得
- タブ・SLIM画面遷移でも固定
- 「対象を変更」で明示解除
- heartbeat
- 競合時は担当者と期限だけ表示
- ロックなしでは将来の転記ボタンを有効にしない

【ページ判定】
URL pathnameと見出しを組み合わせ、最低限次を判定してください。
- login
- admission_procedure
- view_basic_user
- view_image_survey
- addition_notification
- unknown

保存HTMLで確認したパス：
- `/slim/web/m/sng/login/`
- `/slim/web/m/sng/front/admission_procedure/`
- `/slim/web/m/sng/front/view_basic_user/`
- `/slim/web/m/sng/front/view_image_survey/`
- `/slim/web/m/sng/front/addition_notification/`

URLだけでなくtitle、h1/h2の正規化文字列も検証し、矛盾時はunknownにしてください。

【iframe】
保存HTMLでは0件でも、解析モードはframeId、URL、入力数を収集できるようにしてください。`allFrames` を無条件に信用せず、権限のある同一／対象originだけを扱ってください。

【SLIM画面解析モード】
サイドパネルに「現在のSLIM画面を解析」ボタンを追加してください。

解析結果：
- timestamp
- extension version
- page URL/path
- title
- h1/h2/h3
- frame一覧
- input/select/textarea/button
- type
- id
- name
- placeholder
- readonly/disabled/required
- autocomplete
- maxLength
- classのうち安定候補
- 近傍ラベル文字列
- fieldset/accordion見出し
- custom selectの表示候補文字列
- file inputの周辺見出し
- 登録ボタン候補

除外：
- 入力済みのvalue
- password
- hidden token
- session cookie
- 個人情報
- `data-v-*` 等のビルドハッシュ
- ページ全HTML

結果は：
- パネル内で概要表示
- JSONをクリップボードへコピー
- JSONファイルとして保存

ファイル名例：
`slim-inspection-admission_procedure-20260625T120000.json`

【保存HTMLfixture】
提供されたSLIM保存HTMLを読み、必要なDOMだけを匿名化・縮小したfixtureを `edge-extension/tests/fixtures/` に作ってください。

禁止：
- 生のHTML一式を製品拡張へ同梱
- vendor JS/CSSを同梱
- 保存されている会員情報をfixtureへ残す
- 公的身分証明書file inputを顔写真欄と命名

【dry-run】
選択申込と現在ページから、次工程で入力する予定の項目を一覧化してください。ただしDOMへ値を書き込まない。

表示例：
- 姓：対象欄検出
- 名：対象欄検出
- 性別：候補検出
- コース：候補一致1件
- 登録会員No.：ルール未確定

結果：
- ready
- warning
- blocked

【確認済みの安定ID】
入会受付：
- `#sei_name`
- `#mei_name`
- `#kana_sei`
- `#kana_mei`
- `#entry_member_no`
- `#birthday`
- accordion `#entry`
- accordion `#basic`

会員情報：
- `#name_sei`
- `#name_mei`
- `#kana_sei`
- `#kana_mei`
- `#birthday`

IDがない欄は、近傍ラベルと局所コンテナを基準に解析してください。`nth-child`だけのselectorを保存しないでください。

【テスト】
- page detector全ページ
- URLと見出し矛盾でunknown
- login検知
- inspectorがvalue/password/tokenを出さない
- data-v属性を出さない
- file input周辺見出しを区別
- candidate listに不要なPIIを出さない
- token失効時に状態消去
- lock取得・競合・解除
- side panelのキーボード操作

Node環境を追加する場合、拡張本体はビルドなしでも展開読込できるか、再現可能なbuildを用意してください。依存を最小化してください。

【UIデザイン】
- 幅400～440pxで破綻しない
- 装飾より状態と次操作
- 最重要ボタンを1つだけ強調
- 状態を色だけで表さず、文字・アイコン・件数を併用
- 申込選択の誤りを防ぐ固定表示
- クリック領域44px以上
- 日本語

【完了条件】
- Edgeでサイドパネルが開く
- APIペアリングできる
- 申込一覧・選択・lockが動く
- SLIM各保存ページを判定できる
- DOM解析JSONが個人情報を含まず出力できる
- dry-runで対象欄／不足欄を一覧化できる
- DOMへ実値をまだ書き込まない

変更ファイル、導入手順、権限理由、テスト結果、実機で取得すべき解析項目を報告し、進捗を更新してください。
```

---

# プロンプト5：SLIM画面単位の一括転記・差異検出・写真DL

```text
先行工程のEdge拡張、API、解析JSON、SLIM保存HTMLfixtureを読み、この工程で初期版の本体機能である「現在のSLIM画面へ一括転記」を実装してください。

最終のSLIM登録ボタンは自動クリックしません。画面遷移も自動化しません。空欄入力、差異検出、検証、ログ、進捗、写真ダウンロードまで実装してください。

【絶対ルール】
- 対象申込が固定され、API lockがあり、現在ページが現在operationのpage_typeと一致する場合だけ実行可能
- 空欄だけ入力
- 既存値が正規化後に一致すればmatched
- 既存値が異なれば上書きしない
- 欄が見つからなければmissing
- 入力後に読み直して一致を検証
- 一件でも必須差異／missing／errorがあれば完全成功にしない
- コース候補は許可リストの完全一致だけ
- `data-v-*`、候補順、nth-childだけへ依存しない
- 健康情報を転記しない
- 登録ボタンを押さない
- SLIM認証情報を扱わない

【fill engine】
UI固有コードをページごとに複製せず、以下を分離してください。

- page profile
- field resolver
- value normalizer
- input adapter
- custom select adapter
- custom date adapter
- phone splitter
- verifier
- highlighter
- result reporter

結果型例：
- filled
- matched
- skipped_nonempty
- difference
- missing
- invalid_source
- invalid_target
- error

各項目はfield keyと結果だけをAPIログへ送り、PII値は送らない。

【Vue/Nuxt入力】
通常input：
- prototypeのネイティブvalue setter
- input/change/blurをbubblesで発火
- 必要ならfocus
- 直後とmicrotask／短い待機後に検証

独自readonly select：
- ラベル基準で対象を特定
- クリックして候補を開く
- 許可された完全一致候補を探す
- 0件または複数件なら停止
- クリック
- 表示値を検証

独自日付：
- readonlyを解除して直接代入するような壊し方をしない
- 解析した日付ピッカーUIを操作
- 年月日を選び、表示値を検証
- 実機で直接入力が正式に反映されることを確認できた場合だけprofile設定で許可

isolated worldでVueが更新されない場合だけ、最小限のMAIN world bridgeを検討してください。その場合：
- 固定メッセージschema
- tokenやPIIをwindowへ残さない
- 任意コード実行を作らない
- 実行後にbridgeを除去
- 理由と脅威モデルを文書化

【ページprofileのversion】
各profileにversionとfingerprintを持たせてください。
- path
- expected headings
- required stable IDs／labels
- optional fields
- mapping version

fingerprint不一致なら転記を無効化して解析モードへ誘導してください。

【入会受付ページ】
対象path：`/front/admission_procedure/`

入会項目accordionを開き、最低限次を処理してください。
- surname → `#sei_name`
- given_name → `#mei_name`
- surname_kana → `#kana_sei`
- given_name_kana → `#kana_mei`
- gender → 「性別」custom select
- actual_procedure_date → 「入会日」date
- phone_type=home の場合だけ「自宅TEL」3分割
- operation course → 「コース」custom select
- payment cycle → 「支払サイクル」custom selectで月払い
- start_date → 「利用開始日」date

`#entry_member_no`：
- 現時点では採番ルール未確定
- transfer bundleに確定値またはprofile ruleがない限り入力しない
- 空欄かつSLIM必須ならblockedとして表示
- 申込IDを入れない
- ランダム採番しない

基本項目accordion `#basic` を開き、最低限次を処理してください。
- birth → `#birthday`、SLIM表示例に合わせ `YYYY/MM/DD`
- email → 「PCメールアドレス」を初期転記先とする。別規則は設定化
- phone_type=mobile の場合だけ「携帯TEL」3分割
- postal_code
- prefecture custom select
- city_area → 住所1
- street_address → 住所2
- building → 住所3
- emergency_phone → 緊急連絡先TEL1
- emergency_name → 緊急連絡先名1
- guardian_name → 保護者名

続柄に対応するSLIM欄が確認できない場合：
- 氏名欄へ勝手に連結しない
- missing_optionalまたはunmappedとしてパネルへ表示

次は触らない：
- アンケート回答
- 疾病・健康情報
- DM送信設定
- 公的身分証明書
- 勤務先
- 割引
- 危険者区分
- 登録ボタン

【追加届ページ】
対象path：`/front/addition_notification/`

- 「新規」modeを選択
- actual_procedure_date → 申込日
- reason → 9999 / その他
- start_date → 利用開始日
- operation course → コース
- payment cycle → 月払い

会員番号は対象会員検索済み画面に紐づく可能性があるため、保存HTMLと実機解析に基づかず任意欄へ入力しない。対象会員表示とtransfer bundleのmember numberが一致するか検証できる場合は、転記前の安全確認に使ってください。

【会員情報ページ】
初期ワークフローの必須operationにはしない。
- 登録後の補正・確認用profileとして実装可能
- operationsから明示された場合だけ一括転記
- 入会受付と同様に空欄限定

【写真・アンケート・その他ページ】
- 保存HTMLのfile inputは公的身分証明書用
- 顔写真を設定しない
- target user imageが表示されることは検知可能
- 顔写真自動設定機能は無効状態で保持

【電話分割】
日本の電話番号をsourceで正規化した後、SLIMのTEL1/TEL2/TEL3へ安全に分割してください。
- 070/080/090
- 市外局番の主要形式
- 判定不能時は推測分割せずblocked
- APIが既に明示的partsを返せるならサーバー側正規化結果を優先
- 同じ番号をhome/mobile双方へ入れない

【既存値比較】
正規化して比較可能なもの：
- 全角／半角空白
- 電話のハイフン
- 日付表示区切り
- 郵便番号ハイフン

氏名、カナ、コース名は過度な正規化で別値を同一扱いしない。

【ハイライト】
SLIM DOMへ一時的クラスを付ける。
- filled：控えめな成功枠
- difference：警告枠
- missing/error：エラー枠または関連見出し

色だけでなくtitleまたは拡張パネルの対応リストで説明する。SLIM本体レイアウトを崩さない。対象変更時・再判定時に除去する。

【サイドパネル結果】
主ボタン：`この画面へ一括転記`

実行前：
- 対象氏名
- 申込ID
- operation
- 実手続日
- 利用開始日
- コース
- 入力予定数
- warning

実行後：
- 入力件数
- 一致件数
- 既存値差異
- 見つからない欄
- エラー
- 各差異のfield label
- 次にスタッフが行う操作

全必須項目がfilled/matchedの場合：
`内容を確認し、SLIMの「登録」を押してください。`

スタッフが押した後に：
`SLIM登録完了として記録`
を押す。これがAPIのoperation completeを呼ぶ。

【再実行】
- 許可する
- filled済み値はmatchedになる
- 異なる既存値は上書きしない
- attempt countと結果を記録
- 同じrequest_idの二重ログを防止

【次operation】
operation完了後：
- 次のoperation名
- 必要なSLIMページ
- コース
- 会員番号前提
を表示する。
固定URLが安全に開ける場合だけ「次のSLIM画面を開く」を提供する。ログイン状態・対象会員選択を壊すURL遷移を推測しない。

【写真ダウンロード】
- APIから短期photo tokenを取得
- `chrome.downloads.download`
- filename：`FIND-SLIM/<slim_member_number or application_id>.<safe extension>`
- 氏名を含めない
- Downloads以外の絶対パスを指定しない
- overwriteせずuniquifyまたは安全な競合方針
- 完了／失敗を表示・ログ
- 「フォルダで表示」はユーザー操作時だけ
- file inputへローカルpath文字列を設定しない

【エラー処理】
- SLIMログイン切れ
- profile fingerprint違い
- custom select候補0／複数
- date picker失敗
- Vue反映失敗
- API期限切れ
- lock喪失
- version競合
- network失敗
- required field missing

いずれも無理に継続せず、APIへneeds_reviewを記録できるようにする。

【テスト】
匿名化fixtureを使って最低限：
- 入会受付の安定ID解決
- accordion展開
- phone_type別の電話欄
- 空欄入力
- 同値matched
- 異値differenceで非上書き
- custom select完全一致
- 候補0／複数
- date adapter
- address1～3
- optional続柄未割当
- `#entry_member_no`未確定でblocked
- 追加届145/146/141/144
- operation順序
- fingerprint不一致
- 公的身分証明書file inputへ触れない
- 登録ボタンをクリックしない
- photo filename
- APIログにPII値がない

【実機確認モード】
本番転記前に、開発者設定で `dryRunOnly` を有効にできるようにしてください。初期配布時の既定は、検証完了までdry-runにできる構成が望ましいです。

【完了条件】
- 対象ページ1画面につき1ボタンで空欄転記できる
- 差異を上書きしない
- 全入力を再検証する
- 登録ボタンを押さない
- operations進捗と要確認が管理画面へ戻る
- 写真がDownloads/FIND-SLIMへ保存される
- 顔写真を公的身分証明書欄へ設定しない

変更ファイル、page profile、mapping、テスト結果、実機で未確定の項目を報告し、進捗を更新してください。
```

---

# プロンプト6：結合試験・実機パイロット・配備・ロールバック

```text
ここまでの公開フォーム、MySQL、管理画面、API、Edge拡張を統合し、検証PCで安全にパイロット運用できる状態へ仕上げてください。

この工程では完全自動登録は実装しません。最終登録ボタンはスタッフが押す初期版を完成させます。

【最初に行うこと】
- `docs/slim-integration-progress.md` の未完了項目を列挙
- git diff、migration状態、テスト状態を確認
- 本番秘密を出力しない
- 未確定事項を推測で埋めない

【E2Eシナリオ】
匿名のテスト申込を作り、最低限次を検証してください。

1. Pilates単体basic：151
2. Pilates単体double：135
3. 既存本館＋basic：145
4. 既存本館＋double：146
5. 同時find_master＋basic：80→145
6. 同時day_free＋double：130→146
7. 同時night_holiday＋basic：74→145
8. 同時u34＋double：133→146
9. 同時gym_free＋basic：140→145
10. 同時gym_pool＋basic：140→141→145
11. 同時gym_pool＋double：140→141→146
12. 同時gym_studio＋basic：140→144→145
13. 同時gym_studio＋double：140→144→146
14. 旧weekend：停止・要確認

各シナリオで確認：
- 公開入力
- サーバー料金
- MySQL保存
- 管理画面表示
- operations生成
- Edge一覧
- 対象固定・lock
- SLIM page検知
- dry-run
- 一括転記
- 差異検出
- 手動登録後の完了記録
- 次operation解放
- 最終SLIM completed

【SLIM実機で取得するもの】
解析モードを使い、個人情報値を含めず次を取得・反映してください。

- 入会受付の動的select候補
- 支払サイクルの正確な候補
- 追加届理由9999の正確な表示
- 日付ピッカーDOM
- 登録会員No.の運用・必須性・採番方法
- 登録成功時のURL、見出し、メッセージ
- 代表的な必須エラー、重複エラーのDOM
- 実際の会員顔写真画面／操作
- ログアウト・自動タイムアウト時の状態

取得JSONをそのまま製品へ埋めず、必要なstable selectorと候補をpage profileへレビューして反映してください。profile versionを更新してください。

【登録会員No.】
実機確認でルールが確定するまで自動入力しない。
確定した場合：
- 仕様を `docs/slim-field-inventory.md` へ記録
- 値の生成元を明示
- 重複防止
- 入力後検証
- テスト
- 申込IDの流用は禁止 unless 正式仕様であると確認できた場合

【顔写真】
実際の会員顔写真欄が取得できるまで自動設定しない。
初期版はDL補助で完成扱いとする。
会員顔写真欄が通常のfile inputだと確認できても、まず別branch／feature flagで検証し、公的身分証明書欄との識別テストを必須にする。

【セキュリティレビュー】
- APIレスポンスに健康情報・管理メモがない
- 拡張永続ストレージにtoken・PIIがない
- SLIM資格情報を扱わない
- 写真URLは短期・認証付き
- ログにPII値がない
- CORS許可origin
- 本番debug off
- エラー詳細非表示
- SQL injection、CSRF、XSS
- 拡張権限が最小
- remote codeなし
- 生SLIM HTML・顧客fixtureが配布物にない

【性能・操作性】
- 申込一覧初期取得をページング
- APIタイムアウトと再試行は安全なGETのみ
- POSTはrequest_idで冪等
- Side panelを開いてから対象選択まで少ない操作
- 主ボタンは1つ
- 400～440px幅
- SLIM画面と並べて操作可能
- キーボード操作
- 操作中の二重クリック防止

【Xserver配備】
次を作成または更新してください。
- DB migration実行手順
- DBバックアップ手順
- ファイル配備順
- private config項目
- API base path
- `.htaccess`
- 写真保存ディレクトリ権限
- PHP設定確認
- rollback手順
- health check

migrationは本番適用前にschema-onlyバックアップを取り、データ変更を伴う場合はデータバックアップも必要です。秘密値をドキュメントへ記載しない。

【Edge初期配布】
`docs/edge-extension-pilot.md` を作成：
- edge://extensions
- 開発者モード
- 展開して読み込み
- extension ID確認
- API allow origin設定
- ペアリング
- dry-run
- 本番転記有効化
- 更新方法
- 無効化・削除
- 問題時のログ取得

Edgeポリシー配布は次段階として、必要設定名・確認項目だけ別節へ記載してください。確証のないregistry値を推測で書かない。

【ロールバック】
- 公開フォームを旧JSON保存へ戻すことを通常のrollbackにしない
- DB migration rollbackまたは旧コードread-only表示を準備
- 拡張機能はEdge側で無効化可能
- API token全失効
- 一括転記機能のserver-side feature flag
- `extension_transfer_enabled=false` で即時停止可能

【運用UI】
管理画面に最低限：
- API／拡張機能の有効状態
- 有効token数
- 要確認件数
- ロック中件数
- 最終転記エラー概要
- 緊急停止スイッチ

【受入基準】
- 手入力項目の大半が画面単位一括転記される
- 既存値を誤上書きしない
- コースを曖昧選択しない
- 同時gym_pool/studioで3operationが正しい順序
- 別スタッフとの競合を防ぐ
- エラーが管理画面へ戻る
- 写真DLが安全
- 最終登録は人が確認
- 全機能を即時停止できる

【完了成果物】
- `docs/production-deploy-slim.md`
- `docs/edge-extension-pilot.md`
- `docs/slim-integration-runbook.md`
- 最終テスト一覧
- 未確定事項一覧
- rollback手順
- バージョン情報

【完了報告】
- 自動テスト結果
- 実機テスト結果と未実施項目を区別
- 配備ファイル
- migration
- 拡張バージョン
- profile version
- 本番前に人が行うチェック
を報告し、進捗ドキュメントを完了状態へ更新してください。
```

---

# プロンプト7：完全自動化（初期版安定後のみ・任意）

```text
このプロンプトは、画面単位の一括転記版が実運用で安定し、登録完了画面・エラー画面・会員番号取得方法が実機で確定した後だけ実行してください。

次の前提を満たしていない場合はコードを変更せず、不足条件だけ報告してください。

【開始条件】
- 登録完了時のURL・見出し・DOMが保存済み
- 代表的なエラーDOMが保存済み
- 登録会員No.の正式ルールが確定
- 会員番号取得方法が確定
- 全対象コースの画面単位転記が連続して成功
- operationsの冪等性と二重登録防止が確認済み
- 緊急停止feature flagが本番で動作
- テスト用SLIM会員で自動登録を試せる

【目的】
既存のoperations state machineを拡張し、任意設定で：
- 対象ページを開く
- 転記
- 検証
- 登録
- 完了検出
- 会員番号取得
- 次operationへ進む
を行えるようにする。

【初期値】
- `auto_navigation=false`
- `auto_submit=false`
- 本番では管理者が明示的に有効化しない限り動作しない
- operationごとのallowlist

【安全条件】
以下が1つでもあれば自動submitしない。
- difference
- missing
- error
- fingerprint mismatch
- lock喪失
- token期限切れ
- 会員番号不一致
- 対象会員表示不一致
- コース候補0／複数
- readiness warning
- 前operation未完了
- request id再利用の不整合

【登録ボタン】
- 見出し・ページfingerprint・button label・周辺コンテキストの全てが一致した場合だけ
- button順序やCSS hashだけへ依存しない
- クリック直前に転記値を再検証
- 二重クリック防止
- submit intentをAPIへ記録
- 成功・失敗が確定するまで次operationへ進まない
- timeout時に再クリックしない。状態不明として要確認

【完了検出】
- URLだけで成功扱いにしない
- 明示的な成功メッセージ／会員番号／状態を複数条件で検証
- エラーDOMを先に検出
- 成功時だけoperation completed
- 不明時はneeds_review

【会員番号】
- SLIM画面から取得する場合、形式・対象氏名等を検証
- API保存はversion/lock付き
- 既存値と異なる場合は停止
- 同時入会の後続追加届へ安全に引き継ぐ

【ナビゲーション】
- SLIMの正規メニュー操作を優先
- 固定URLでセッション／対象会員を失う場合は使用しない
- 画面遷移後にpage detectorと対象会員を再検証
- 自動ログインはしない

【操作開始UI】
「全工程を自動登録」1ボタンを追加する場合でも、直前に次を表示する。
- 対象氏名
- 申込ID
- 実手続日
- 利用開始日
- operations一覧
- コース
- 警告0件

明示確認後だけ開始し、中断ボタンと現在operationを常時表示する。

【復旧】
- ブラウザ終了後に勝手に再開しない
- API上の状態を読み、スタッフに「再開／要確認」を選ばせる
- 登録済みの可能性があるoperationを自動再submitしない
- 実行履歴とrequest idで二重登録を防ぐ

【テスト】
- 各1～3 operation workflow
- 成功
- SLIMエラー
- timeout
- ログイン切れ
- 対象会員不一致
- button fingerprint違い
- 二重クリック
- ブラウザ終了
- API一時障害
- success応答後のAPI保存失敗
- 既登録の可能性がある再開
- 緊急停止

【完了条件】
- feature flag offでは従来の手動最終登録版が完全に維持される
- 状態不明時に自動再試行しない
- 全操作が監査可能
- 二重登録防止テストが通る
- 緊急停止できる

実装・テスト後も、初回本番有効化は1台・1スタッフ・限定コースから段階的に行うrunbookを更新してください。
```

---

## 最初の実運用でCodexへ追加で渡すもの

プロンプト4以降では、次のファイルを同じ作業環境へ配置する。

- 現行リポジトリのクリーンな作業コピー
- SLIM保存HTML4画面
- 秘密値を除いた設定例
- schema-only SQL
- 匿名テスト申込

生の本番DBダンプ、実顧客写真、SLIMログイン情報は渡さない。
