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
| `/purchase/?{item_id}` | GET | PurchaseController | index | 必須 | 購入ページ表示 |
| `/purchase/success` | GET | PurchaseController | purchaseSuccess | 必須 | 購入履歴作成機能 |
| `/reservation/{item_id}` | GET | ReservationController | index | 必須 | 予約ページ表示 |
| `/reservation/success` | GET | ReservationController | reservationSuccess | 必須 | 予約履歴作成機能 |
| `/payment` | POST | PaymentController | payment | 必須 | 決済機能 |
| `/add-address` | POST | AddAddressController | store | 必須 | 配送先住所追加機能 |
| `/mypage` | GET | MyPageController | index | 必須 | マイページ表示 |
| `/mypage/edit` | GET | MyPageController | edit | 必須 | 登録情報変更ページ表示 |
| `/mypage/update` | POST | MyPageController | update | 必須 | 登録情報更新機能 |
| `/mypage/delete` | DELETE | MyPageController | delete | 必須 | 退会機能 |

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
| 商品ID | 入力必須、idの有無 |
| 商品数量 | 入力必須、整数値、1以上、10以下 |
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
| 商品ID | 入力必須、idの有無 |
| 商品数量 | 入力必須、整数値、1以上、10以下 |
| 配送先氏名 | 入力必須、文字列、255文字以下 |
| 配送先住所 | 入力必須、文字列、接頭辞と正規表現の値 |
| 配送先電話番号 | 入力必須、数字のみ、10〜11桁、15文字以下 |
| 支払方法 | 入力必須、文字列、25文字以下、指定の値のみ |

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
| メールアドレス | 入力必須、重複不可、正しいメールアドレス形式、30文字以下 |
| パスワード | 必須ではない、登録の際と同じFortifyのバリデーションを使用 |

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
