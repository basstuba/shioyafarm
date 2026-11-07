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
| `/terms-of-service` | VIEW | Route::view | | | 利用規約ページ表示 |
| `/privacy-policy` | VIEW | Route::view | | | プライバシーポリシーページ表示 |
| `/commercial-transaction` | VIEW | Route::view | | | 特定商取引法に基づく表記ページ表示 |
| `/shipping-fee` | VIEW | Route::view | | | 送料案内ページ表示 |
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
| `/admin/login` | GET | AdminAuthController | create | | ログインページ表示 |
| `/admin/login` | POST | AdminAuthController | login | | ログイン機能 |
| `/admin/forgot-password` | GET | AdminPasswordResetController | create | | 管理者のパスワード再設定メールの送信ページ表示（ルート名 `admin.password.request`。管理者としてログイン中は `/admin` へ移動する。設計書にはなかった追加機能） |
| `/admin/forgot-password` | POST | AdminPasswordResetController | store | | 管理者のパスワード再設定メール送信機能（ルート名 `admin.password.email`。会員とは別のパスワードブローカー `admins`。管理者でないメールアドレス（会員を含む）、1分以内の再送信でも、送れたときと同じ案内を表示する。IPごとに1分5回まで） |
| `/admin/reset-password/{token}` | GET | AdminPasswordResetController | edit | | 管理者の新しいパスワードの設定ページ表示（ルート名 `admin.password.reset`。再設定メールのリンクから開く） |
| `/admin/reset-password` | POST | AdminPasswordResetController | update | | 管理者のパスワード再設定機能（ルート名 `admin.password.update`。成功したら管理者用ログインページへ。使用済み・期限切れ（60分）・改ざんされたリンクはエラーにして、送信ページへのリンクを表示する。IPごとに1分5回まで） |
| `/admin/logout` | POST | AdminAuthController | logout | 必須 | ログアウト機能 |
| `/admin` | GET | AdminHomeController | index | 必須 | 管理画面トップページ表示 |
| `/admin/users` | GET | AdminUserController | index | 必須 | 管理画面ユーザー一覧ページ表示機能（画面の表示は「会員一覧」。退会済みの会員も含め、新しく登録した順に1ページ10件） |
| `/admin/users/search` | GET | AdminUserController | search | 必須 | ユーザー検索機能（状態 `status`＝`member` 退会していない会員・`withdrawn` 退会済み、氏名 `name`。入力の誤りは `/admin/users` に戻してエラーを表示） |
| `/admin/users/delete` | DELETE | AdminUserController | delete | 必須 | 退会済みの会員の物理削除機能（設計書にはなかった追加機能。`id` を受け取り、成功・失敗とも削除の前に見ていた検索条件・ページの一覧（直前のページが会員一覧・検索結果のとき。それ以外は `/admin/users`）に戻る。会員と、その会員の注文・予約・振込情報・配送先住所、パスワード再設定のトークンを完全に削除する（セッションは消さない）。未発送の注文・予約が残っている会員、退会していない会員、存在しない会員は削除せずエラーにする（退会済みかの判定は、行をロックするリポジトリだけで行う）。成功の案内「会員を削除しました。」は、検索欄の下に緑の文字で表示する（flash の `list_status`。layout の案内には出さない）。データベースのエラー（ロック待ちのタイムアウトなど）のときはログに残し、「削除できませんでした。時間をおいてもう一度お試しください。」を表示する。判定と削除は同じトランザクションの中で行う。Stripe の顧客データは削除しない。物理削除で発送済みの予約が消えると、その分だけ予約可能数の残りが増える。物理削除は取引の終了後で予約の受付も終わっている想定のため許容する） |
| `/admin/purchase` | GET | AdminPurchaseController | index | 必須 | 管理画面注文一覧ページ表示機能 |
| `/admin/purchase/search` | GET | AdminPurchaseController | search | 必須 | 注文検索機能（発送状況 `shipped`＝0 未発送・1 発送済、注文日 `date`＝Y-m-d、注文者名 `name`。入力の誤りは `/admin/purchase` に戻してエラーを表示） |
| `/admin/purchase/update` | POST | AdminPurchaseController | update | 必須 | 注文発送済みチェック機能（設計書にはなかった追加機能。`id` と `shipped` を JSON で受け取り JSON を返す。存在しない注文は404） |
| `/admin/purchase/cancel` | POST | AdminPurchaseController | cancel | 必須 | 注文取消機能（設計書にはなかった追加機能。`id` を受け取り、成功・失敗とも取消の前に見ていた検索条件・ページの一覧（直前のページが注文一覧・検索結果のとき。それ以外は `/admin/purchase`）に戻る。行をロックして、存在しない・発送済み・取消済みを判定し、取り消せるときだけ `canceled_at` を記録する。銀行振込で振込先の行があるときは Stripe の PaymentIntent の状態を確認し、未入金は取り消してから、入金済み（succeeded）は Stripe を変えずに、すでに canceled のとき（前回、Stripe の取消だけ成功して保存に失敗した再実行。取り消したあとは一部入金の手がかりが残らない）は成功扱いにして、「入金があった場合は、返金はStripeの管理画面で行ってください。」と案内する。一部入金の判定は、requires_action のときの振込の残り（next_action の amount_remaining）が PaymentIntent の金額より小さいか（amount_received には反映されない）で行う。取り消せない状態・Stripe のエラーは取消済みにしない。クレジットカード・代金引換は Stripe を呼ばない。成功の案内「注文を取り消しました。」（クレジットカードは「クレジットカードの返金は、Stripeの管理画面で行ってください。」つき、入金済み・入金処理中・一部入金済みの銀行振込は「入金済みのため、返金はStripeの管理画面で行ってください。」つき）は flash の `list_status` で検索欄の下に緑の文字、失敗の理由（発送済みのため取り消せません。／すでに取り消されています。／取り消せませんでした。時間をおいて、もう一度お試しください。／注文が見つかりません。）と419は `errors` の `cancel` で同じ場所に赤字。取消のメールは送らない） |
| `/admin/reservations` | GET | AdminReservationController | index | 必須 | 管理画面予約一覧ページ表示機能 |
| `/admin/reservations/search` | GET | AdminReservationController | search | 必須 | 予約検索機能（発送状況 `shipped`＝0 未発送・1 発送済、予約日 `date`＝Y-m-d、予約者名 `name`。入力の誤りは `/admin/reservations` に戻してエラーを表示） |
| `/admin/reservations/update` | POST | AdminReservationController | update | 必須 | 予約発送済みチェック機能（設計書にはなかった追加機能。`id` と `shipped` を JSON で受け取り JSON を返す。存在しない予約は404） |
| `/admin/reservations/cancel` | POST | AdminReservationController | cancel | 必須 | 予約取消機能（設計書にはなかった追加機能。`id` を受け取る。処理・戻り先・案内は注文取消（`/admin/purchase/cancel`）と同じで、戻り先は `/admin/reservations` の一覧・検索結果、語は「予約」。取り消した予約の数量は予約可能数の残りに戻る） |
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
| `/admin/export` | POST | AdminExportController | export | 必須 | エクスポート機能（`type` で種類を指定する。`purchase`＝注文一覧、`reservation`＝予約一覧。不正な種類はエラー。検索条件 `shipped`・`date`・`name` は各一覧の検索と同じで、ページ番号は無視して全件を出力する。入力の誤りは、種類に応じて `/admin/purchase`・`/admin/reservations` に戻してエラーを表示する（種類が不正なときは `/admin/purchase`）。.xlsx・ファイル名 `注文一覧_YYYYMMDD.xlsx`・`予約一覧_YYYYMMDD.xlsx`） |
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
- 管理者のパスワード再設定メールの送信ページ
- 管理者の新しいパスワードの設定ページ
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
| AdminPasswordResetMail | 管理者のパスワード再設定メールの送信ページで、登録済みの管理者のメールアドレスを送信したとき（`/admin/forgot-password`。`Admin::sendPasswordResetNotification` から送る。宛名は管理者名。会員のメールとは別の仕組み） | 【しおや農園】管理画面のパスワード再設定のご案内 |

本文の構成（Markdownメール。HTML版とテキスト版。会員のメールと管理者のメールで共通）：宛名（「〇〇 様」。会員のメールは会員の氏名、管理者のメールは管理者名）、再設定のご依頼を受け付けた旨と手順、「パスワードを再設定する」ボタン、リンクの有効期限（60分）と、期限切れ・使用済みのときの再請求の案内、ボタンを押せないときのURL、心当たりがなければ破棄してよい旨、送信専用である旨、署名。違いは次のとおり。会員のメールは「パスワードの再設定」と書き、再請求の案内は「ログインページ」の「パスワードをお忘れの方」、送信専用の案内の後にお問い合わせフォームへのリンクを付ける。管理者のメールは「管理画面のパスワードの再設定」と書き、再請求の案内は「管理者用ログインページ」の「パスワードをお忘れの方」、お問い合わせフォームの案内とリンクは付けない（送信専用である旨のみ）。Laravel 標準の英語の通知は使わない。送信に失敗したときは、発行したトークンを削除してからエラーにする（使えないトークンを残さない）。未登録・退会済みのメールアドレス（管理者のメールは、管理者として登録されていないメールアドレス）と、1分以内の再送信では送らない。

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

注文一覧・予約一覧の検索条件（PurchaseSearchRequest・ReservationSearchRequest。共通の AdminListSearchRequest）を引き継ぎ、種類を加えたもの。エラーの戻り先と、メッセージの「注文」「予約」は、種類で切り替える（種類は一覧の種類を表す `AdminListType`。値は `purchase`・`reservation`。語・ルート・テーブルなどの対応はこの enum を正とする）。

| フォーム | ルール |
|---|---|
| 種類（type） | 入力必須、指定の値のみ（`purchase`・`reservation`） |
| 発送状況（shipped） | 必須ではない、0 か 1 |
| 注文日・予約日（date） | 必須ではない、`Y-m-d` 形式の日付 |
| 注文者名・予約者名（name） | 必須ではない、文字列、100文字以下、正しい UTF-8（壊れた文字を含む値はエラーにする。絞り込みが外れて全件が表示・出力されるのを防ぐため） |

### PurchaseSearchRequest.php（注文検索）・ReservationSearchRequest.php（予約検索）・AdminPurchaseUpdateRequest.php・AdminReservationUpdateRequest.php（発送済みの更新）

| フォーム | ルール |
|---|---|
| 発送状況（shipped）・注文日／予約日（date）・注文者名／予約者名（name） | ExportRequest と同じ（すべて必須ではない） |
| 注文／予約（id）※更新 | 入力必須、整数値、1以上（存在するかは検証せず、更新の処理が404で返す） |
| 発送済み（shipped）※更新 | 入力必須、真偽値 |

### 取消に関する追記（発送状況・取消のリクエスト）

- 発送状況（shipped）は、0 か 1 に加えて `canceled`（取消済）を受け付ける（ExportRequest・PurchaseSearchRequest・ReservationSearchRequest 共通。ほかの値はエラー「発送状況は「未発送」「発送済」「取消済」のどれかを選んでください。」）。「0」＝未発送で未取消、「1」＝発送済み、「canceled」＝取消済み、空＝全件（一覧では取消済みも表示）。
- エクスポートは、発送状況が `canceled` のとき以外は取消済みを含めない。`canceled` のときは取消済みだけを出力する。列は変えないファイル名は、`canceled` のときだけ `注文一覧_取消済_YYYYMMDD.xlsx`・`予約一覧_取消済_YYYYMMDD.xlsx`（ASCII 側は `purchase_canceled_YYYYMMDD.xlsx`・`reservation_canceled_YYYYMMDD.xlsx`）にする。シート名は変えない。
- 発送済みの更新（`/admin/purchase/update`・`/admin/reservations/update`）は、取消済みの注文・予約を更新せず、409（`{"message": "取り消されているため、発送状況を変更できません。ページを再読み込みしてください。"}`）を返す。。検証のあとに物理削除されるなどで存在しない注文・予約は、404（`{"message": "注文が見つかりません。"}`。予約は「予約が見つかりません。」）を返す

### AdminPurchaseCancelRequest.php・AdminReservationCancelRequest.php（注文・予約の取消）

入力の誤りは、取消の前に見ていた一覧（直前のページが注文一覧・予約一覧の検索結果のとき。それ以外は一覧の全件）に戻して、エラーを表示する。存在するか・発送済みか・取消済みかは、行をロックするリポジトリで判定する。

| フォーム | ルール |
|---|---|
| 注文／予約（id） | 入力必須、整数値 |

### UserSearchRequest.php（会員検索）・UserDeleteRequest.php（会員の物理削除）

入力の誤りは、会員検索（UserSearchRequest）は `/admin/users`（全件の表示）に戻して、会員の物理削除（UserDeleteRequest）は削除の前に見ていた会員一覧（直前のページが会員一覧・検索結果のとき。それ以外は `/admin/users`）に戻して、エラーを表示する。

| フォーム | ルール |
|---|---|
| 状態（status） | 必須ではない、`member`・`withdrawn` のみ（文字列以外はエラー） |
| 氏名（name） | 必須ではない、文字列、100文字以下、正しい UTF-8（ExportRequest の氏名と同じ） |
| 会員（id）※削除 | 入力必須、整数値（退会済みの会員として存在するかは、入力の検証ではなく、削除の処理の中で判定する。退会していない会員・存在しない会員は同じエラー） |

### AdminPasswordResetLinkRequest.php（管理者のパスワード再設定メールの送信）

会員の PasswordResetLinkRequest を引き継ぐ（ルールとメッセージは同じ）。入力の誤りは `/admin/forgot-password` に戻す。

| フォーム | ルール |
|---|---|
| メールアドレス（email） | 入力必須、文字列、メールアドレスの形式（エラーメッセージは会員登録と同じ。「メールアドレスを入力してください」「メールアドレスは文字で入力してください」「メールアドレスは有効なメールアドレス形式で入力してください」）。管理者として登録されているかどうかは検証しない（結果を漏らさないため） |

### AdminNewPasswordRequest.php（管理者の新しいパスワードの設定）

会員の NewPasswordRequest を引き継ぐ（ルールとメッセージは同じ）。入力の誤りは直前のページ（メールのリンクの再設定ページ）に戻す。

| フォーム | ルール |
|---|---|
| トークン（token） | 入力必須、文字列（エラーは「このリンクは無効です」） |
| メールアドレス（email） | 入力必須、文字列、メールアドレスの形式（メッセージは会員登録と同じ） |
| パスワード（password） | 入力必須、文字列（「パスワードを入力してください」「パスワードは文字で入力してください」） |

パスワードの桁数（8文字以上）と確認用パスワードとの一致は、トークンの確認の後に AdminPasswordResetService で検証する（「パスワードは8文字以上で入力してください」「パスワードと確認用パスワードが一致しません」。会員の再設定と同じ順）。検証に失敗したときはトークンを消さないので、同じリンクで入力し直せる。使用済み・期限切れ・改ざんされたトークン（メールアドレスの食い違いを含む）は、再設定ページに「このリンクは無効か、有効期限が切れています。もう一度再設定メールを送信してください。」を表示する。

### MailRequest.php（メール作成）

| フォーム | ルール |
|---|---|
| メールタイトル | 入力必須、文字列、255文字以下 |
| メール本文 | 入力必須、文字列 |

## 管理画面のキャッシュ

管理者のログインが必要なページ（`auth:admin` のルート）の応答には `Cache-Control: no-store` を付ける（ミドルウェア `no-store`）。ログアウト後にブラウザの戻るボタンで管理画面が表示されないようにするため。公開側・ログイン画面には付けない。
