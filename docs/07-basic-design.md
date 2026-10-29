# 基本設計書

## Route / Controller

### ユーザー機能

| パス | メソッド | コントローラー | アクション | 認証必須 | 説明 |
|---|---|---|---|:---:|---|
| `/` | GET | HomeController | index | | トップページ表示 |
| `/items` | GET | ItemController | index | | 商品一覧ページ表示 |
| `/items/{item}` | GET | ItemController | show | | 商品詳細ページ表示 |
| `/inquiry` | GET | InquiryController | create | | お問い合わせフォームページ表示 |
| `/inquiry/create` | POST | InquiryController | store | | お問い合わせフォーム送信機能 |
| `/inquiry/thanks` | GET | InquiryController | thanks | | お問い合わせサンクスページ表示 |
| `/terms-of-service` | VIEW | Routeからviewを呼び出し | | | 利用規約ページ表示 |
| `/privacy-policy` | VIEW | Routeからviewを呼び出し | | | プライバシーポリシーページ表示 |
| `/commercial-transaction` | VIEW | Routeからviewを呼び出し | | | 特定商取引法に基づく表記ページ表示 |
| `/shipping-fee` | VIEW | Routeからviewを呼び出し | | | 送料案内ページ表示 |
| `/cart` | GET | CartController | index | 必須 | カートページ表示 |
| `/purchase/{item?}` | GET | PurchaseController | index | 必須 | 購入ページ表示 |
| `/purchase/success` | GET | PurchaseController | purchaseSuccess | 必須 | 購入履歴作成機能（クレジットカード：Stripeの決済後に戻る先） |
| `/purchase/bank-transfer` | GET | PurchaseController | bankTransfer | 必須 | 銀行振込の振込先のご案内ページ表示 |
| `/purchase/bank-transfer/cancel` | POST | PurchaseController | cancelBankTransfer | 必須 | 銀行振込の取り消し機能（購入ページへ戻る） |
| `/purchase/bank-transfer/confirm` | POST | PurchaseController | confirmBankTransfer | 必須 | 銀行振込の購入履歴作成機能 |
| `/purchase/thanks` | GET | PurchaseController | thanks | 必須 | 購入完了ページ表示（銀行振込・代金引換） |
| `/reservation/{item}` | GET | ReservationController | index | 必須 | 予約ページ表示（予約限定の商品のみ。予約可能数に達していたら商品詳細へ戻す） |
| `/reservation/payment` | POST | ReservationController | payment | 必須 | 予約の決済機能（クレジットカード：決済ページへ、銀行振込：振込先のご案内へ） |
| `/reservation/success` | GET | ReservationController | reservationSuccess | 必須 | 予約履歴作成機能（クレジットカード：Stripeの決済後に戻る先） |
| `/reservation/bank-transfer` | GET | ReservationController | bankTransfer | 必須 | 予約の銀行振込の振込先のご案内ページ表示 |
| `/reservation/bank-transfer/cancel` | POST | ReservationController | cancelBankTransfer | 必須 | 予約の銀行振込の取り消し機能（予約ページへ戻る） |
| `/reservation/bank-transfer/confirm` | POST | ReservationController | confirmBankTransfer | 必須 | 予約の銀行振込の予約履歴作成機能 |
| `/reservation/thanks` | GET | ReservationController | thanks | 必須 | 予約完了ページ表示（銀行振込） |
| `/payment` | POST | PaymentController | payment | 必須 | 購入の決済機能（代金引換はここで購入履歴を作成） |
| `/add-address` | POST | AddAddressController | store | 必須 | 配送先住所追加機能 |
| `/mypage` | GET | MyPageController | index | 必須 | マイページ表示（ルート名 `shop.mypage.index`。お届け先住所一覧・購入履歴・予約履歴は Livewire で5件ずつページ送り、詳細はモーダル） |
| `/mypage/edit` | GET | MyPageController | edit | 必須 | 登録情報変更ページ表示（ルート名 `shop.mypage.edit`） |
| `/mypage/update` | POST | MyPageController | update | 必須 | 登録情報更新機能（ルート名 `shop.mypage.update`。成功したらマイページへ。変更がない場合は保存せず登録情報変更ページに戻り「変更された項目はありません」と表示） |
| `/mypage/delete` | DELETE | MyPageController | delete | 必須 | 退会機能（ルート名 `shop.mypage.delete`。論理削除してログアウトし、トップページへ） |

#### 認証（Fortify）

Fortify が登録するルート。ビューは `FortifyServiceProvider` で指定し、入力の検証・応答の一部はアプリ側のクラスに差し替えている（`FortifyServiceProvider` でバインド）。ログイン中に開いた場合（ログアウトを除く）はトップページへ移動する。二段階認証は画面ができるまで無効にしている（`config/fortify.php`）。

| パス | メソッド | コントローラー | アクション | 認証必須 | 説明 |
|---|---|---|---|:---:|---|
| `/register` | GET | Fortify の RegisteredUserController | create | | 会員登録ページ表示（ルート名 `register`） |
| `/register` | POST | RegisteredUserController（Fortify を継承） | store | | 新規会員登録機能（ルート名 `register.store`） |
| `/login` | GET | Fortify の AuthenticatedSessionController | create | | ログインページ表示（ルート名 `login`） |
| `/login` | POST | Fortify の AuthenticatedSessionController | store | | ログイン機能（ルート名 `login.store`。入力の検証は LoginRequest に差し替え。回数制限あり） |
| `/logout` | POST | Fortify の AuthenticatedSessionController | destroy | 必須 | ログアウト機能（ルート名 `logout`） |
| `/forgot-password` | GET | Fortify の PasswordResetLinkController | create | | パスワード再設定メールの送信ページ表示（ルート名 `password.request`） |
| `/forgot-password` | POST | Fortify の PasswordResetLinkController | store | | パスワード再設定メール送信機能（ルート名 `password.email`。入力の検証は PasswordResetLinkRequest に差し替え。未登録・退会済みのメールアドレス、1分以内の再送信でも、送れたときと同じ案内を表示する。IPごとに1分5回まで） |
| `/reset-password/{token}` | GET | NewPasswordController（Fortify を継承） | create | | 新しいパスワードの設定ページ表示（ルート名 `password.reset`。再設定メールのリンクから開く） |
| `/reset-password` | POST | NewPasswordController（Fortify を継承） | store | | パスワード再設定機能（ルート名 `password.update`。成功したらログインページへ。使用済み・期限切れ（60分）・改ざんされたリンクはエラーにして、送信ページへのリンクを表示する。IPごとに1分5回まで） |

### 管理者機能

| パス | メソッド | コントローラー | アクション | 認証必須 | 説明 |
|---|---|---|---|:---:|---|
| `/admin/login` | GET | AdminAuthController | showLoginForm | | ログインページ表示 |
| `/admin/login` | POST | AdminAuthController | login | 必須 | ログイン機能 |
| `/admin/logout` | POST | AdminAuthController | logout | 必須 | ログアウト機能 |
| `/admin` | GET | AdminHomeController | index | 必須 | 管理画面トップページ表示 |
| `/admin/users` | GET | AdminUserController | index | 必須 | 管理画面ユーザー一覧ページ表示機能 |
| `/admin/users/search` | GET | AdminUserController | search | 必須 | ユーザー検索機能 |
| `/admin/purchase` | GET | AdminPurchaseController | index | 必須 | 管理画面注文一覧ページ表示機能 |
| `/admin/purchase/search` | GET | AdminPurchaseController | search | 必須 | 注文検索機能 |
| `/admin/reservations` | GET | AdminReservationController | index | 必須 | 管理画面予約一覧ページ表示機能 |
| `/admin/reservations/search` | GET | AdminReservationController | search | 必須 | 予約検索機能 |
| `/admin/notices` | GET | AdminNoticeController | index | 必須 | 管理画面お知らせ一覧ページ表示 |
| `/admin/notices/search` | GET | AdminNoticeController | search | 必須 | お知らせ検索機能 |
| `/admin/notices/create` | GET | AdminNoticeController | create | 必須 | お知らせ作成ページ表示 |
| `/admin/notices/store` | POST | AdminNoticeController | store | 必須 | お知らせ作成機能 |
| `/admin/inquiries` | GET | AdminInquiryController | index | 必須 | 管理画面お問い合わせ一覧ページ表示 |
| `/admin/inquiries/search` | GET | AdminInquiryController | search | 必須 | お問い合わせ検索機能 |
| `/admin/inquiries/update` | POST | AdminInquiryController | update | 必須 | お問い合わせ対応チェック機能 |
| `/admin/items` | GET | AdminItemController | index | 必須 | 管理画面掲載商品一覧ページ表示 |
| `/admin/items/create` | GET | AdminItemController | create | 必須 | 掲載商品登録ページ表示 |
| `/admin/items/store` | POST | AdminItemController | store | 必須 | 掲載商品登録機能 |
| `/admin/items/edit` | GET | AdminItemController | edit | 必須 | 掲載商品修正ページ表示 |
| `/admin/items/update` | POST | AdminItemController | update | 必須 | 掲載商品修正機能 |
| `/admin/items/delete` | DELETE | AdminItemController | delete | 必須 | 掲載商品削除機能 |
| `/admin/export` | POST | AdminExportController | export | 必須 | エクスポート機能 |
| `/admin/mail/user/{user_id}` | GET | AdminMailController | createUserMail | 必須 | メール作成ページ表示 |
| `/admin/mail/inquiry/{inquiry_id}` | GET | AdminMailController | createInquiryMail | 必須 | メール作成ページ表示 |
| `/admin/mail/send` | POST | AdminMailController | send | 必須 | メール送信機能 |

## Model

| 説明 |
|---|
| Usersテーブル操作用モデル |
| Adminsテーブル操作用モデル |
| Itemsテーブル操作用モデル |
| Noticesテーブル操作用モデル |
| Inquiriesテーブル操作用モデル |
| Reservationsテーブル操作用モデル |
| Purchase_historiesテーブル操作用モデル |
| Purchase_history_itemsテーブル操作用モデル |
| Purchase_bank_transfersテーブル操作用モデル |
| Reservation_bank_transfersテーブル操作用モデル |
| Delivery_addressesテーブル操作用モデル |
| Areasテーブル操作用モデル |

## View

各ページの内容は [ページ一覧](03-page-list.md) を参照。

### ユーザー画面

- トップページ
- 商品一覧ページ
- 商品詳細ページ
- カートページ
- 購入ページ
- 決済ページ
- 振込先のご案内ページ（shop/bank_transfer。購入・予約で共通）
- 支払い確定処理中の案内ページ（shop/payment_pending。購入・予約で共通）
- 購入サンクスページ
- 予約ページ
- 予約サンクスページ
- お問い合わせフォームページ
- お問い合わせサンクスページ
- 利用規約ページ
- プライバシーポリシーページ
- 特定商取引法に基づく表記ページ
- 送料案内ページ
- 会員登録ページ
- ログインページ
- パスワード再設定メールの送信ページ
- 新しいパスワードの設定ページ
- マイページ
- 登録情報変更ページ

### 管理画面

- 管理者用ログインページ
- 管理画面トップページ
- 管理画面ユーザー一覧ページ
- 管理画面注文一覧ページ
- 管理画面予約一覧ページ
- 管理画面お知らせ一覧ページ
- 管理画面お知らせ作成ページ
- 管理画面お問い合わせ一覧ページ
- 管理画面掲載商品一覧ページ
- 管理画面掲載商品登録ページ
- 管理画面掲載商品修正ページ
- 管理画面メール作成ページ

### 共通パーツ

- 共通ヘッダーページ
- 管理画面用ヘッダーページ

## Mail

購入・予約のデータを保存したときに、会員登録のメールアドレスへサンクスメールを送る（Markdownメール。HTML版とテキスト版）。送信はキューを使わず、レスポンスを返した後に同じリクエストの中で行う。送信に失敗しても購入・予約は成立させ、ログに残す。保存しなかった場合（戻る・カードの拒否・再読み込み・二重送信）は送らない。差出人は `config('mail.from')`（しおや農園 / shioyafarm@example.com）。

| Mailable | 送るタイミング | 件名 |
|---|---|---|
| PurchaseThanksMail | 購入履歴の作成時（`/purchase/success`、`/purchase/bank-transfer/confirm`、`/payment` の代金引換・顧客残高で支払い済みの銀行振込） | 【しおや農園】ご注文ありがとうございます（注文番号：{purchase_histories.id}） |
| ReservationThanksMail | 予約の作成時（`/reservation/success`、`/reservation/bank-transfer/confirm`、`/reservation/payment` の顧客残高で支払い済みの銀行振込） | 【しおや農園】ご予約ありがとうございます（予約番号：{reservations.id}） |

本文の構成：宛名（会員の氏名）、あいさつ、■ご注文内容／■ご予約内容（番号・日時・お支払方法・商品明細・商品小計・送料・代引手数料（代金引換のみ）・合計。予約は9月からの発送の案内を付ける）、■お届け先（お届け先氏名・住所・電話番号）、■お振込先（銀行振込のみ。銀行名・支店名・口座種別・口座番号・口座名義・お振込金額。顧客残高で支払い済みのときは「お客様の残高からお支払いが完了しています」）、マイページで確認できる案内、送信専用の注意とお問い合わせページのURL、署名。

農園（管理者）への新規注文の通知、入金確認メール、発送通知メールは作らない（農園への通知は管理画面の実装時に検討する）。

### パスワード再設定メール

| Mailable | 送るタイミング | 件名 |
|---|---|---|
| PasswordResetMail | パスワード再設定メールの送信ページで、登録済みのメールアドレスを送信したとき（`/forgot-password`。`User::sendPasswordResetNotification` から送る） | 【しおや農園】パスワード再設定のご案内 |

本文の構成（Markdownメール。HTML版とテキスト版）：宛名（会員の氏名）、再設定の手順、「パスワードを再設定する」ボタン、リンクの有効期限（60分）、心当たりがなければ破棄してよい旨、署名。Laravel 標準の英語の通知は使わない。送信に失敗したときは、発行したトークンを削除してからエラーにする（使えないトークンを残さない）。未登録・退会済みのメールアドレスと、1分以内の再送信では送らない。

## バリデーション

### CartRequest.php（カートに保存）

| フォーム | ルール |
|---|---|
| 商品ID | 入力必須、idの有無 |
| 商品数量 | 入力必須、整数値、1以上、10以下 |

### PurchaseRequest.php（購入ページの決済）

user_idと合計金額はcontrollerで取得

| フォーム | ルール |
|---|---|
| 購入元 | 入力必須、文字列、指定の値のみ（`cart`：カートから、`item`：商品詳細から） |
| 注文トークン（order_token） | 入力必須、文字列、100文字以下（購入ページの表示ごとに発行する1回限りの値。使用済み・不正な値では注文を作らない） |
| 商品（items） | 入力必須、配列、1件以上 |
| 商品ID（items.*.item_id） | 入力必須、整数値、重複不可、idの有無（予約限定の商品は不可） |
| 商品数量（items.*.quantity） | 入力必須、整数値、1以上、10以下 |
| 配送先氏名 | 入力必須、文字列、255文字以下 |
| 配送先住所 | 入力必須、文字列、接頭辞と正規表現の値 |
| 配送先電話番号 | 入力必須、数字のみ、10〜11桁、15文字以下 |
| 支払方法 | 入力必須、文字列、25文字以下、指定の値のみ |

### InquiryRequest.php（お問い合わせ送信）

| フォーム | ルール |
|---|---|
| 姓 | 入力必須、文字列、255文字以下 |
| 名 | 入力必須、文字列、255文字以下 |
| メールアドレス | 入力必須、正しいメールアドレス形式、30文字以下 |
| お問い合わせ内容 | 入力必須、文字列、2000文字以下 |

### ReservationRequest.php（予約ページの決済）

user_idと合計金額はcontrollerで取得

| フォーム | ルール |
|---|---|
| 注文トークン（order_token） | 入力必須、文字列、100文字以下（予約ページの表示ごとに発行する1回限りの値。使用済み・不正な値では予約を作らない） |
| 商品ID | 入力必須、整数値、idの有無（予約限定の商品のみ） |
| 商品数量 | 入力必須、整数値、1以上、10以下（確定時に、予約可能数の残数を超える場合はエラーにする） |
| 配送先氏名 | 入力必須、文字列、255文字以下 |
| 配送先住所 | 入力必須、文字列、接頭辞と正規表現の値 |
| 配送先電話番号 | 入力必須、数字のみ、10〜11桁、15文字以下 |
| 支払方法 | 入力必須、文字列、25文字以下、指定の値のみ（`card`、`customer_balance`） |

### DeliveryAddressRequest.php（配送先住所追加）

| フォーム | ルール |
|---|---|
| 配送先住所（都道府県） | 入力必須、テーブルに存在の有無 |
| 配送先住所（市区町村） | 入力必須、文字列、255文字以下 |

### UserRequest.php（会員登録情報変更）

| フォーム | ルール |
|---|---|
| 姓 | 入力必須、文字列、255文字以下 |
| 名 | 入力必須、文字列、255文字以下 |
| 住所（都道府県） | 入力必須、テーブルに存在の有無 |
| 住所（市区町村） | 入力必須、文字列、255文字以下 |
| 電話番号 | 入力必須、数字のみ、10〜11桁、15文字以下 |
| メールアドレス | 入力必須、重複不可（自分を除く。退会済みの会員のメールアドレスも重複として扱う）、正しいメールアドレス形式、30文字以下 |
| パスワード | 必須ではない（空のときは変更しない）、入力したときは登録の際と同じFortifyのバリデーションを使用 |
| 現在のパスワード | メールアドレスまたはパスワードを変更するときだけ入力必須（メールアドレスが今と同じ、または大文字小文字の違いだけなら変更とみなさない）、現在のパスワードと一致 |

エラーメッセージは会員登録（`CreateNewUser`）と同じ文言を使う（現在のパスワードのメッセージを除く）。

### NoticeRequest.php（お知らせ作成）

| フォーム | ルール |
|---|---|
| お知らせタイトル | 入力必須、文字列、255文字以下 |
| お知らせ内容 | 入力必須、文字列 |

### ItemRequest.php（掲載商品登録・修正）

| フォーム | ルール |
|---|---|
| 商品名 | 入力必須、文字列、255文字以下 |
| 商品重量 | 入力必須、指定の値のみ |
| 商品価格 | 入力必須、整数値 |
| 商品画像 | 入力必須、文字列、255文字以下 |
| おすすめ商品 | 入力必須、真偽値 |
| 商品詳細 | 入力必須、文字列 |
| 予約限定 | 入力必須、真偽値 |
| 予約可能数 | 必須ではない、整数値 |

### ExportRequest.php（エクスポート）

| フォーム | ルール |
|---|---|
| 検索キーワード（注文者or予約者名） | 必須ではない、文字列、255文字以下 |
| 検索日付 | 必須ではない、日付 |

### MailRequest.php（メール作成）

| フォーム | ルール |
|---|---|
| メールタイトル | 入力必須、文字列、255文字以下 |
| メール本文 | 入力必須、文字列 |
