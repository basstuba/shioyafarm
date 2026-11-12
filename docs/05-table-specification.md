# テーブル仕様書

ER図は [ER図](06-er-diagram.md) を参照。

## users（ユーザーデータ）

退会は論理削除で、退会した会員は deleted_at に退会日時が入る。退会した会員は、ログイン・会員登録・登録情報変更のいずれでもメールアドレスを再利用できない。購入履歴・予約・配送先住所は消さない。物理削除は、取引の終了後に管理者が管理画面の会員一覧から行い、会員の行と、その会員の購入履歴（と明細・振込先）・予約（と振込先）・配送先住所が、外部キーの cascade で完全に消える。あわせて、その会員のメールアドレスのパスワード再設定のトークン（password_reset_tokens）も、同じトランザクションで消す（外部キーがないため、アプリで消す。同じメールアドレスで再登録した人に古いトークンが使われないようにする）。セッション（sessions）は消さない（sessions.user_id は既定のガードのIDで、管理者のセッションにも管理者のIDが入り、同じ番号の管理者のセッションを消してしまうため。退会済みの会員は既に認証できない）。未発送の購入履歴・予約が残っている会員は削除できない。

二段階認証のカラム（two_factor_*）は Fortify 標準のカラムで、会員には二段階認証を付けない方針のため使っていない（二段階認証は全会員の個人情報・削除・取消を扱う管理者だけに付けている。コードは [admin_two_factor_codes](#admin_two_factor_codes管理者の二段階認証コードデータ) に保存する）。remember_token は Laravel 標準のカラムで、ログイン状態の保持は使っていない。

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| last_name | varchar(255) | | | ○ | |
| first_name | varchar(255) | | | ○ | |
| prefecture | varchar(255) | | | ○ | |
| city | varchar(255) | | | ○ | |
| tell | varchar(15) | | | ○ | |
| email | varchar(30) | | ○ | ○ | |
| password | varchar(255) | | | ○ | |
| stripe_customer_id | varchar(255) | | ○ | | |
| two_factor_secret | text | | | | |
| two_factor_recovery_codes | text | | | | |
| two_factor_confirmed_at | timestamp | | | | |
| remember_token | varchar(100) | | | | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |
| deleted_at | timestamp | | | | |

## admins（管理者データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| name | varchar(255) | | | ○ | |
| email | varchar(30) | | ○ | ○ | |
| password | varchar(255) | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## admin_password_reset_tokens（管理者のパスワード再設定トークンデータ）

管理者のパスワード再設定（`/admin/forgot-password`。設計書にはなかった追加機能）で発行するトークンを保存する。会員の再設定のトークン（`password_reset_tokens`）とは別のテーブルで、Laravel のパスワードブローカー `admins` が使う。管理者1人につき1件で、再発行すると上書きされる。token はハッシュ化して保存し、有効期限は60分。使用後・送信失敗時は削除する。

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| email | varchar(255) | ○ | | ○ | |
| token | varchar(255) | | | ○ | |
| created_at | timestamp | | | | |

## admin_two_factor_codes（管理者の二段階認証コードデータ）

管理者の二段階認証（メールのワンタイムコード。`/admin/two-factor`。設計書にはなかった追加機能）で送る確認コードを保存する。管理者1人につき1件（admin_id は unique）。ログインし直す（パスワードを通る）たびに新しいコードに置き換わり（1回の upsert で行うので、ログインのダブルクリックでも重複エラーにならない）、前のコードは使えなくなり、失敗回数は0に戻る。「コードを再送する」でも新しいコードに置き換わり有効期限は数え直すが、**失敗回数は引き継ぐ**（再送で失敗回数を戻せると、コードを何度でも推測できるため）。code は数字6桁を、アプリケーションキーを鍵にした HMAC-SHA256（16進の64文字。bcrypt ではない。6桁は総当たりを防げないので、防ぐのは失敗回数の上限）にして保存する。照合・期限の確認・削除は、入力のハッシュを条件にした1回の DELETE（code = ハッシュ かつ expires_at > 今）で行い、削除できた件数が1のときだけ正しいコードとする（再送で置き換えられた前のコードは通らず、新しいコードも消えない）。有効期限は送ってから10分、間違えられるのは5回まで（5回目の間違いで削除。失敗回数は、コードを比べる前に、DB の UPDATE（attempts < 5 かつ expires_at > 今 が条件）で1つ使うので、同時に何通送られても5回を超えて試せない。期限切れは数えない）。ログインし直すたびに失敗回数は0に戻るが、同じ管理者の間違いは、ログインし直しても別のIPからでも、1時間に合計20回まで（RateLimiter。キーは管理者ID。テーブルには保存しない。コードの入力に成功しても消さない）。20回に達したらコードを削除し、1時間（数え始めから）がたつまでは、正しいパスワードでもコードを送らない。使用後・送信失敗・管理者のパスワード再設定の完了時にも削除する。sent_at は、再送を前回の送信から1分あけるための判定に使い、判定と更新は1回の UPDATE（sent_at が条件）で行う（同時に2回押されてもメールは1通）。管理者を削除すると cascade で消える。オン・オフは `.env` の `ADMIN_TWO_FACTOR_ENABLED`（既定はオン。公開デモはオフ）で切り替える。

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| admin_id | bigint unsigned | | ○ | ○ | admins.id（cascade） |
| code | varchar(255) | | | ○ | |
| attempts | tinyint unsigned | | | ○ | |
| expires_at | timestamp | | | ○ | |
| sent_at | timestamp | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## items（掲載商品データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| name | varchar(255) | | | ○ | |
| weight | integer | | | ○ | |
| price | integer | | | ○ | |
| img_url | varchar(255) | | | ○ | |
| detail | text | | | ○ | |
| recommend | boolean | | | ○ | |
| is_reserved_only | boolean | | | ○ | |
| reservation_limit | integer | | | | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |
| deleted_at | timestamp | | | | |

索引：`name` と `weight` の複合索引（`items_name_weight_index`。管理画面の登録・修正で、同じ商品名・重さの商品を探してロックするとき用。論理削除した商品と同じ組み合わせは登録できるため、一意制約にはしない）。`img_url` は、シードの画像は `image/product/xxx.webp`、管理画面でアップロードした画像は `image/product/uploads/xxx.webp`（長辺1200px以下の webp）。

`deleted_at` は論理削除の日時（NULL 可。削除していない商品は NULL）。管理画面の掲載商品一覧の「削除」で記録する（設計書にはなかった追加カラム。モデルは `SoftDeletes`）。削除した商品は、公開側（商品一覧・トップのおすすめ・商品詳細・カート・購入・予約）と管理画面の一覧に出ない。元に戻す機能と物理削除は作らない。購入明細・予約の外部キー（`item_id`）は restrictOnDelete（注文・予約のある商品は物理削除できない）。

## notices（お知らせ内容データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| title | varchar(255) | | | ○ | |
| text | text | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## inquiries（お問い合わせデータ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| last_name | varchar(255) | | | ○ | |
| first_name | varchar(255) | | | ○ | |
| email | varchar(30) | | | ○ | |
| detail | text | | | ○ | |
| addressed | boolean | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## reservations（予約データ）

予約は1件につき1商品。支払方法はクレジットカード（`card`）と銀行振込（`customer_balance`）のみで、代引手数料の列はない。

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| user_id | bigint unsigned | | | ○ | users(id) |
| item_id | bigint unsigned | | | ○ | items(id) |
| item_name | varchar(255) | | | ○ | |
| item_weight | integer | | | ○ | |
| delivery_name | varchar(255) | | | ○ | |
| delivery_prefecture | varchar(255) | | | ○ | |
| delivery_city | varchar(255) | | | ○ | |
| delivery_tell | varchar(15) | | | ○ | |
| quantity | integer | | | ○ | |
| price | integer | | | ○ | |
| subtotal | integer | | | ○ | |
| shipping_fee | integer | | | ○ | |
| total_price | integer | | | ○ | |
| payment_method | varchar(25) | | | ○ | |
| shipped | boolean | | | ○ | |
| canceled_at | timestamp | | | | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

`item_name`・`item_weight` は予約した時点の商品名と重さ（設計書にはなかった追加カラム）。マイページ・管理画面の詳細・エクスポート・サンクスメールの表示に使う。商品を修正・削除しても、過去の予約の表示は変わらない。既存の予約は、商品の今の値で埋めてから NOT NULL にした。

`shipped` は発送済みかどうか（既定値 false）。管理画面の予約一覧のチェックで切り替える（設計書にはなかった追加カラム）。管理者だけが見て、マイページ・メールには出さない。日時は持たない。

`canceled_at` は取消日時（NULL 可。取り消されていない予約は NULL）。管理画面の予約の取消（`POST /admin/reservations/cancel`）で記録する（設計書にはなかった追加カラム）。`shipped` と同じく一括代入の対象にしない。取消済みの予約は発送済みにできず、予約可能数の合計に含めない。マイページには「取消済」と表示し、管理画面の詳細には取消日として表示する。

## purchase_histories（購入履歴データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| user_id | bigint unsigned | | | ○ | users(id) |
| delivery_name | varchar(255) | | | ○ | |
| delivery_prefecture | varchar(255) | | | ○ | |
| delivery_city | varchar(255) | | | ○ | |
| delivery_tell | varchar(15) | | | ○ | |
| payment_method | varchar(25) | | | ○ | |
| shipping_fee | integer | | | ○ | |
| cod_fee | integer | | | ○ | |
| total_price | integer | | | ○ | |
| shipped | boolean | | | ○ | |
| canceled_at | timestamp | | | | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

`shipped` は発送済みかどうか（既定値 false）。管理画面の注文一覧のチェックで切り替える（設計書にはなかった追加カラム）。管理者だけが見て、マイページ・メールには出さない。日時は持たない。

`canceled_at` は取消日時（NULL 可。取り消されていない注文は NULL）。管理画面の注文の取消（`POST /admin/purchase/cancel`）で記録する（設計書にはなかった追加カラム）。`shipped` と同じく一括代入の対象にしない。取消済みの注文は発送済みにできない。マイページには「取消済」と表示し、管理画面の詳細には取消日として表示する。

## purchase_history_items（購入商品データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| purchase_history_id | bigint unsigned | | | ○ | purchase_histories(id) |
| item_id | bigint unsigned | | | ○ | items(id) |
| item_name | varchar(255) | | | ○ | |
| item_weight | integer | | | ○ | |
| quantity | integer | | | ○ | |
| price | integer | | | ○ | |
| subtotal | integer | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

`item_name`・`item_weight` は注文した時点の商品名と重さ（設計書にはなかった追加カラム）。マイページ・管理画面の詳細・エクスポート・サンクスメールの表示に使う。商品を修正・削除しても、過去の注文の表示は変わらない。既存の明細は、商品の今の値で埋めてから NOT NULL にした。

## purchase_bank_transfers（銀行振込の振込先データ）

銀行振込（customer_balance）の注文だけ、購入履歴1件につき1件作る。マイページの購入履歴と注文確認メールで振込先を表示するために保存する。

Stripe上の顧客残高で注文時に支払い済みになった場合は振込先が発行されないため、振込先の5項目（bank_name〜account_holder_name）は null になる。

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| purchase_history_id | bigint unsigned | | ○ | ○ | purchase_histories(id) |
| stripe_payment_intent_id | varchar(255) | | ○ | ○ | |
| bank_name | varchar(255) | | | | |
| branch_name | varchar(255) | | | | |
| account_type | varchar(25) | | | | |
| account_number | varchar(25) | | | | |
| account_holder_name | varchar(255) | | | | |
| amount | integer | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## reservation_bank_transfers（予約の銀行振込の振込先データ）

銀行振込（customer_balance）の予約だけ、予約1件につき1件作る。構成は purchase_bank_transfers と同じ（purchase_history_id の代わりに reservation_id）。

Stripe上の顧客残高で予約時に支払い済みになった場合は振込先が発行されないため、振込先の5項目（bank_name〜account_holder_name）は null になる。

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| reservation_id | bigint unsigned | | ○ | ○ | reservations(id) |
| stripe_payment_intent_id | varchar(255) | | ○ | ○ | |
| bank_name | varchar(255) | | | | |
| branch_name | varchar(255) | | | | |
| account_type | varchar(25) | | | | |
| account_number | varchar(25) | | | | |
| account_holder_name | varchar(255) | | | | |
| amount | integer | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## delivery_addresses（配送先住所データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| user_id | bigint unsigned | | | ○ | users(id) |
| delivery_prefecture | varchar(255) | | | ○ | |
| delivery_city | varchar(255) | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## areas（セレクトボックス用都道府県データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| prefecture | varchar(255) | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |
