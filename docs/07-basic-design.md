# 基本設計書

## Route / Controller

### ユーザー機能

| パス | メソッド | コントローラー | アクション | 認証必須 | 説明 |
|---|---|---|---|:---:|---|
| `/` | GET | HomeController | index | | トップページ表示（おすすめ商品は、おすすめ「はい」の商品からランダムに最大3件。お知らせは作成日の新しい順に最大5件で、同じ日時は新しく作った順、日付も作成日を表示する） |
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

Fortify が登録するルート。ビューは `FortifyServiceProvider` で指定し、入力の検証・応答の一部はアプリ側のクラスに差し替えている（`FortifyServiceProvider` でバインド）。ログイン中に開いた場合（ログアウトを除く）はトップページへ移動する。会員には二段階認証を付けない方針のため無効にしている（`config/fortify.php`。二段階認証は全会員の個人情報・削除・取消を扱う管理者だけに付けている。下の管理者機能を参照）。

| パス | メソッド | コントローラー | アクション | 認証必須 | 説明 |
|---|---|---|---|:---:|---|
| `/register` | GET | Fortify の RegisteredUserController | create | | 会員登録ページ表示（ルート名 `register`） |
| `/register` | POST | RegisteredUserController（Fortify を継承） | store | | 新規会員登録機能（ルート名 `register.store`。登録に成功したら `RegistrationMailService` で会員登録完了メール（下の「会員登録完了メール」）を送る。登録された会員が `User` でないときは、メールを送らず警告をログに残す。IPごとに1分5回まで（上限は設定 `fortify.register_per_minute`＝`REGISTER_THROTTLE_PER_MINUTE` で変えられる。E2E用アプリ（php-e2e）では大きくしている）。超えたときは登録ページに戻して「登録の操作が続いたため、〇秒後に再度お試しください。」（〇は待ち時間の秒数）を表示する） |
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
| `/admin/login` | POST | AdminAuthController | login | | ログイン機能（ルート名 `admin.login.store`。二段階認証がオン（既定）のときは、メールアドレスとパスワードが正しくても、まだログインせず、確認コードをメールで送って `/admin/two-factor` へ移動する。パスワードを通った管理者のIDと、そのときのパスワードの派生値（HMAC。パスワードのハッシュそのものではない）だけをセッションに保存する。メールアドレスとパスワードの照合は `admin` ガードの `validate()`（応答時間をそろえる処理・認証のイベント。ハッシュの再計算も行う）で行う。メールを送れなかったときはログインページに戻して「確認コードのメールを送信できませんでした。時間をおいてもう一度お試しください。」を表示する。オフのときは、今までどおりパスワードだけでログインして `/admin` へ移動する。確認コードの間違いが続いて上限に達した管理者は、その時間が過ぎるまで、正しいパスワードでもコードを送らず、`/admin/login` へ同じ案内（「確認コードの間違いが続いたため、しばらくログインできません。〇分後にもう一度お試しください。」）を出す。パスワードが間違いのときは、今までどおりのエラー） |
| `/admin/two-factor` | GET | AdminTwoFactorController | create | | 管理者の確認コードの入力ページ表示（ルート名 `admin.two-factor`。設計書にはなかった追加機能。パスワードを通った管理者がセッションにいないとき（直接開いた・5回間違えた後・二段階認証がオフのとき）は `/admin/login` へ移動する。管理者としてログイン中は `/admin` へ移動する） |
| `/admin/two-factor` | POST | AdminTwoFactorController | store | | 管理者の確認コードの確認機能（ルート名 `admin.two-factor.store`。数字6桁（全角数字は半角にして受け付ける）。未入力・6桁の数字でない入力は入力エラーにし、失敗回数には数えない。正しければコードを削除（1回限り）して管理者としてログインし、セッションIDとCSRFトークンを作り直して `/admin` へ移動する。コードを比べる前に失敗回数を1つ使う（DB の UPDATE で、5回までしか増やせない）。ルートごとに、入力待ちの管理者（なければIPアドレス）あたり1分10回まで（上限に達したときは入力欄の近くに「確認コードの入力・再送の回数が上限に達しました。〇秒後に再度お試しください。」、JSONを期待するリクエストは429）。入力待ちの途中で管理者のパスワードが変わったとき（再設定されたとき）は、入力待ちを解いて `/admin/login` へ「パスワードが変更されたため、確認コードは使えなくなりました。新しいパスワードでもう一度ログインしてください。」を付けて移動する（確認コードのページの表示・送信・再送のどれでも。入力待ちがもともとないときは案内なし）。有効期限（10分）が切れていれば「確認コードの有効期限が切れました。「コードを再送する」から新しいコードを受け取ってください。」。正しくなければ失敗回数を数えて「確認コードが正しくありません。（あと〇回入力できます）」。5回目の間違いでコードとセッションの保存を消し、`/admin/login` へ「確認コードを5回間違えたため、無効にしました。もう一度ログインしてください。」を付けて移動する。同じ管理者の間違いは、ログインし直しても別のIPからでも、1時間に合計20回まで（`config/auth.php` の `admin_two_factor.miss_limit`・`miss_window_seconds`。RateLimiter で管理者IDごとに数える。期限切れ・形式エラーは数えない。コードの入力に成功しても消さない）。20回目の間違いで、コードと入力待ちを消して `/admin/login` へ「確認コードの間違いが続いたため、しばらくログインできません。〇分後にもう一度お試しください。」（残り時間を分で切り上げ）を付けて移動する。コードの照合・期限の確認・削除は1回の DELETE で行う） |
| `/admin/two-factor/resend` | POST | AdminTwoFactorController | resend | | 管理者の確認コードの再送機能（ルート名 `admin.two-factor.resend`。前回の送信（ログイン時・再送とも）から1分たっていなければ、メールを送らず「確認コードの再送は、前回の送信から1分たってからお試しください。」。1分以上たっていれば新しいコードに置き換えて（有効期限は数え直すが、失敗回数は引き継ぐ。前のコードは使えなくなる）送り、判定と置き換えは1回の UPDATE で行う（同時に押されてもメールは1通）。置き換えられなかったとき、コードがなければ（ロック・使用済み）、新しく作らず、入力待ちを解いて `/admin/login` へ戻す。上限は確認コードの送信と同じ（1分10回）。「新しい確認コードをお送りしました。前のコードは使えなくなります。」。メールを送れなかったときは `/admin/login` へ戻す。CSRFトークンが古いときは確認コードの送信（`admin.two-factor.store`）とあわせて、エラーページにせず、入力待ちが残っているか（セッションだけで見る。パスワードの変更などは戻り先のページが確かめる）で、残っていれば `/admin/two-factor` へ「ページの有効期限が切れました。もう一度入力してください。」を付けて戻す（コードは置き換えず、メールも送らない。届いているコードをそのまま使える）。入力待ちが残っていなければ `/admin/login` へ「ページの有効期限が切れました。もう一度ログインしてください。」を付けて戻す） |
| `/admin/forgot-password` | GET | AdminPasswordResetController | create | | 管理者のパスワード再設定メールの送信ページ表示（ルート名 `admin.password.request`。管理者としてログイン中は `/admin` へ移動する。設計書にはなかった追加機能） |
| `/admin/forgot-password` | POST | AdminPasswordResetController | store | | 管理者のパスワード再設定メール送信機能（ルート名 `admin.password.email`。会員とは別のパスワードブローカー `admins`。管理者でないメールアドレス（会員を含む）、1分以内の再送信でも、送れたときと同じ案内を表示する。IPごとに1分5回まで） |
| `/admin/reset-password/{token}` | GET | AdminPasswordResetController | edit | | 管理者の新しいパスワードの設定ページ表示（ルート名 `admin.password.reset`。再設定メールのリンクから開く） |
| `/admin/reset-password` | POST | AdminPasswordResetController | update | | 管理者のパスワード再設定機能（ルート名 `admin.password.update`。再設定が完了したら、その管理者に送ってあった確認コード（`admin_two_factor_codes`）を削除する。成功したら管理者用ログインページへ。使用済み・期限切れ（60分）・改ざんされたリンクはエラーにして、送信ページへのリンクを表示する。IPごとに1分5回まで） |
| `/admin/logout` | POST | AdminAuthController | logout | 必須 | ログアウト機能 |
| `/admin` | GET | AdminHomeController | index | 必須 | 管理画面トップページ表示 |
| `/admin/users` | GET | AdminUserController | index | 必須 | 管理画面ユーザー一覧ページ表示機能（画面の表示は「会員一覧」。退会済みの会員も含め、新しく登録した順に1ページ10件） |
| `/admin/users/search` | GET | AdminUserController | search | 必須 | ユーザー検索機能（状態 `status`＝`member` 退会していない会員・`withdrawn` 退会済み、氏名 `name`。入力の誤りは `/admin/users` に戻してエラーを表示） |
| `/admin/users/delete` | DELETE | AdminUserController | delete | 必須 | 退会済みの会員の物理削除機能（設計書にはなかった追加機能。`id` を受け取り、成功・失敗とも削除の前に見ていた検索条件・ページの一覧（直前のページが会員一覧・検索結果のとき。それ以外は `/admin/users`）に戻る。会員と、その会員の注文・予約・振込情報・配送先住所、パスワード再設定のトークンを完全に削除する（セッションは消さない）。未発送の注文・予約が残っている会員、退会していない会員、存在しない会員は削除せずエラーにする（退会済みかの判定は、行をロックするリポジトリだけで行う）。成功の案内「会員を削除しました。」は、検索欄の下に緑の文字で表示する（flash の `list_status`。layout の案内には出さない）。データベースのエラー（ロック待ちのタイムアウトなど）のときはログに残し、「削除できませんでした。時間をおいてもう一度お試しください。」を表示する。判定と削除は同じトランザクションの中で行う。Stripe の顧客データは削除しない。物理削除で発送済みの予約が消えると、その分だけ予約可能数の残りが増える。物理削除は取引の終了後で予約の受付も終わっている想定のため許容する） |
| `/admin/purchase` | GET | AdminPurchaseController | index | 必須 | 管理画面注文一覧ページ表示機能 |
| `/admin/purchase/search` | GET | AdminPurchaseController | search | 必須 | 注文検索機能（発送状況 `shipped`＝0 未発送（未取消のみ）・1 発送済・canceled 取消済、注文日 `date`＝Y-m-d、注文者名 `name`。入力の誤りは `/admin/purchase` に戻してエラーを表示） |
| `/admin/purchase/update` | POST | AdminPurchaseController | update | 必須 | 注文発送済みチェック機能（設計書にはなかった追加機能。`id` と `shipped` を JSON で受け取り JSON を返す。存在しない注文は404） |
| `/admin/purchase/cancel` | POST | AdminPurchaseController | cancel | 必須 | 注文取消機能（設計書にはなかった追加機能。`id` を受け取り、成功・失敗とも取消の前に見ていた検索条件・ページの一覧（直前のページが注文一覧・検索結果のとき。それ以外は `/admin/purchase`）に戻る。行をロックして、存在しない・発送済み・取消済みを判定し、取り消せるときだけ `canceled_at` を記録する。銀行振込で振込先の行があるときは Stripe の PaymentIntent の状態を確認し、未入金は取り消してから、入金済み（succeeded）は Stripe を変えずに、すでに canceled のとき（前回、Stripe の取消だけ成功して保存に失敗した再実行。取り消したあとは一部入金の手がかりが残らない）は成功扱いにして、「入金があった場合は、返金はStripeの管理画面で行ってください。」と案内する。一部入金の判定は、requires_action のときの振込の残り（next_action の amount_remaining）が PaymentIntent の金額より小さいか（amount_received には反映されない）で行う。取り消せない状態・Stripe のエラーは取消済みにしない。クレジットカード・代金引換は Stripe を呼ばない。成功の案内「注文を取り消しました。」（クレジットカードは「クレジットカードの返金は、Stripeの管理画面で行ってください。」つき、入金済み・入金処理中・一部入金済みの銀行振込は「入金済みのため、返金はStripeの管理画面で行ってください。」つき）は flash の `list_status` で検索欄の下に緑の文字、失敗の理由（発送済みのため取り消せません。／すでに取り消されています。／取り消せませんでした。時間をおいて、もう一度お試しください。／注文が見つかりません。）と419は `errors` の `cancel` で同じ場所に赤字。取消のメールは送らない） |
| `/admin/reservations` | GET | AdminReservationController | index | 必須 | 管理画面予約一覧ページ表示機能 |
| `/admin/reservations/search` | GET | AdminReservationController | search | 必須 | 予約検索機能（発送状況 `shipped`＝0 未発送（未取消のみ）・1 発送済・canceled 取消済、予約日 `date`＝Y-m-d、予約者名 `name`。入力の誤りは `/admin/reservations` に戻してエラーを表示） |
| `/admin/reservations/update` | POST | AdminReservationController | update | 必須 | 予約発送済みチェック機能（設計書にはなかった追加機能。`id` と `shipped` を JSON で受け取り JSON を返す。存在しない予約は404） |
| `/admin/reservations/cancel` | POST | AdminReservationController | cancel | 必須 | 予約取消機能（設計書にはなかった追加機能。`id` を受け取る。処理・戻り先・案内は注文取消（`/admin/purchase/cancel`）と同じで、戻り先は `/admin/reservations` の一覧・検索結果、語は「予約」。取り消した予約の数量は予約可能数の残りに戻る） |
| `/admin/notices` | GET | AdminNoticeController | index | 必須 | 管理画面お知らせ一覧ページ表示（作成日の新しい順に1ページ10件。作成日時の降順、同時刻はIDの降順。範囲外のページ番号は最終ページ。列は作成日（YYYY/MM/DD・日本時間）・タイトル・「詳細」・「修正」・「削除」。詳細のモーダルは Livewire の `AdminNoticeDetail`） |
| `/admin/notices/search` | GET | AdminNoticeController | search | 必須 | お知らせ検索機能（作成日 `date`＝Y-m-d・日本時間のその日。検索は作成日だけ。入力の誤りは `/admin/notices` に戻してエラーを表示） |
| `/admin/notices/create` | GET | AdminNoticeController | create | 必須 | お知らせ作成ページ表示 |
| `/admin/notices/store` | POST | AdminNoticeController | store | 必須 | お知らせ作成機能（`title`・`text` を受け取る。成功すると `/admin/notices` に戻り、表の上に緑の文字で「お知らせを作成しました。」（flash の `list_status`）。入力の誤りは作成ページに戻して項目ごとに赤字で案内し、入力を残す。ブラウザが送る改行（CRLF）は LF にそろえて保存し、文字数も LF で数える。ページの有効期限切れ（419）のときは作成ページに戻し、「ページの有効期限が切れました。もう一度作成してください。」を赤字で出して入力を残す） |
| `/admin/notices/edit/{notice_id}` | GET | AdminNoticeController | edit | 必須 | お知らせ修正ページ表示（設計書にはなかった追加機能。`{notice_id}` は数字だけを受け付け、数字以外は404。存在しないお知らせは `/admin/notices` に戻り、「お知らせが見つかりません。」を赤字で表示する。入力欄には今の値が入る。サイドメニューは「お知らせ一覧」が選択中。一覧の「修正」のリンク先） |
| `/admin/notices/update` | POST | AdminNoticeController | update | 必須 | お知らせ修正機能（設計書にはなかった追加機能。お知らせのIDは hidden の `id` で送る。ほかの項目と検証は作成と同じ。成功すると `/admin/notices` に戻り、緑の文字で「お知らせを修正しました。」。入力の誤りは修正ページに戻して赤字で案内し、入力を残す。存在しないお知らせ、`id` の形が正しくない場合は、何も変えずに `/admin/notices` に戻り、「お知らせが見つかりません。」を赤字で表示する。作成日（`created_at`）は変わらないため、一覧・公開サイトのトップの順番と日付は変わらない。419のときは修正ページに戻し、「もう一度修正してください。」を赤字で出して入力を残す） |
| `/admin/notices/delete` | DELETE | AdminNoticeController | delete | 必須 | お知らせ削除機能（設計書にはなかった追加機能。物理削除。`id` を受け取り、成功しても失敗しても `/admin/notices` に戻る。成功の案内「お知らせを削除しました。」は検索欄の下に緑の文字で表示する（flash の `list_status`）。存在しないお知らせ、`id` の形が正しくない場合は、何も変えずに「お知らせが見つかりません。」を赤字で表示する。お知らせを参照するテーブルはない。削除したお知らせは公開サイトのトップからも消える。一覧の「削除」を押すと「「{タイトル}」を削除します。よろしいですか？」「公開サイトのトップからも消えます。」の確認を出す。419のときは一覧に戻り、「ページの有効期限が切れました。もう一度削除してください。」を赤字で出す） |
| `/admin/inquiries` | GET | AdminInquiryController | index | 必須 | 管理画面お問い合わせ一覧ページ表示（新しい順に1ページ10件。問い合わせ日時の降順、同時刻はIDの降順。範囲外のページ番号は最終ページ） |
| `/admin/inquiries/search` | GET | AdminInquiryController | search | 必須 | お問い合わせ検索機能（対応状況 `addressed`＝`0` 非対応・`1` 対応済・空は絞らない、問い合わせ日 `date`＝Y-m-d・日本時間のその日。氏名の検索はない。入力の誤りは `/admin/inquiries` に戻してエラーを表示） |
| `/admin/inquiries/update` | POST | AdminInquiryController | update | 必須 | お問い合わせ対応チェック機能（`id` と `addressed` を JSON で受け取り JSON（`{"id": 1, "addressed": true}`）を返す。存在しないお問い合わせは404（`{"message": "お問い合わせが見つかりません。"}`）、`addressed` などの不正な値は422。対応済みにしてもお客様にメールは送らない。詳細のモーダルは Livewire の AdminInquiryDetail） |
| `/admin/items` | GET | AdminItemController | index | 必須 | 管理画面掲載商品一覧ページ表示機能（削除していない商品を全件表示する。検索・ページ送りはない。並び順は商品名ごとにまとめ、まとまりは同じ商品名の最小IDの順、その中は重さの軽い順、同じ重さはID順。列は商品名・商品重量（kg）・商品金額（円・税込。3桁ごとにカンマ）・「詳細」・「修正」・「削除」。0件のときは「掲載中の商品はありません。」。「詳細」は Livewire の `AdminItemDetail` のモーダルで、商品名・商品重量・商品金額・商品画像・商品紹介文・おすすめ商品掲載（掲載 / 未掲載）・通常/予約限定・予約可能数を表示する。予約可能数は、通常の商品は「無」、予約限定で上限がなければ「上限なし」、上限があれば「200（残り150）」の形で、残りは公開側の予約ページと同じ数。「修正」のリンク先は `/admin/items/edit/{item_id}`（ルート名 `admin.items.edit`）） |
| `/admin/items/create` | GET | AdminItemController | create | 必須 | 掲載商品登録ページ表示（項目は商品名・商品重量（2kg / 5kg / 10kg）・商品金額（円・税込）・商品画像（ドラッグ＆ドロップまたは「画像を選択」。選んだ画像はプレビューに出る）・商品紹介文・おすすめ商品（はい / いいえ）・予約限定（限定 / 通常）・予約可能数（上限なし / 上限あり＋数）。ラジオボタンは最初はすべて未選択。予約限定が「通常」または未選択のあいだは、予約可能数の部品全体を無効（グレー）にする。サイドメニュー・トップページの「掲載商品登録」のリンク先） |
| `/admin/items/store` | POST | AdminItemController | store | 必須 | 掲載商品登録機能（`name`・`weight`・`price`・`image`（ファイル）・`detail`・`recommend`・`is_reserved_only`・`reservation_limit_type`（`unlimited` / `limited`）・`reservation_limit` を受け取る。成功すると `/admin/items` に戻り、表の上に緑の文字で「商品を登録しました。」（flash の `list_status`）。入力の誤りは登録ページに戻して項目ごとに赤字で案内し、画像以外の入力を残す。検証：商品名は必須・255文字以内、商品重量は 2 / 5 / 10、商品金額は 1〜100,000 の整数（全角数字は半角にし、前後の空白は除いてから検証する。予約可能数の数も同じ）、商品画像は必須・jpeg / png / webp・10MB以下（サーバーで GD を使い、長辺1200px以下に縮小（小さい画像は拡大しない）して webp（品質80）にし、`public` ディスクの `image/product/uploads/` にハッシュ名で保存し、`items.img_url` に `image/product/uploads/xxx.webp` を入れる。jpeg の EXIF の向きは、exif 拡張があれば補正する。GD で読み込めない画像は「画像を読み込めませんでした。別の画像を選んでください」。画像の読み込み・縮小・書き出しは、ロックを取るトランザクションの前に済ませ（縮小してから向きを補正する）、ファイルを書くのは、重複などの確認が通ってから。書いたあとにDBの登録が失敗したときだけそのファイルを消す。ブラウザでも、選んだ・落とした時点で形式と大きさを確かめ、合わなければ選択を取り消して「jpeg・png・webp の10MB以下の画像を選んでください」を画像の欄の下に出す。シードの画像は `image/product/` 直下のまま）、商品紹介文は必須・1,000文字以内、おすすめ商品・予約限定は必須。予約可能数は、予約限定が「限定」のときだけ上限の有無を必須とし、「上限あり」のときだけ数（1〜999 の整数）を必須とする。保存する `reservation_limit` は、限定・上限なしは null、限定・上限ありは数、通常は null（送られてきても捨てる）。削除していない商品に同じ商品名・重さの組み合わせがあれば「{商品名} {重さ}kg はすでに登録されています」で登録しない（削除済みの商品とは重複しない。重複の判定は DB の照合順序 `utf8mb4_unicode_ci` で行うため、ひらがなとカタカナ・濁点の有無・大文字小文字・全角半角は同じとみなす。「じゃがいも」と「ジャガイモ」は重複になる。意図どおり）。重複の判定から保存までは、同じ商品名・重さの行をロックしたトランザクションで行い、ロックした行から重複を判定する（`items` の `name`・`weight` に索引がある）。ページの有効期限切れ（419）のときは登録ページに戻し、「ページの有効期限が切れました。もう一度登録してください。」を赤字で出して画像以外の入力を残す） |
| `/admin/items/edit/{item_id}` | GET | AdminItemController | edit | 必須 | 掲載商品修正ページ表示（`{item_id}` は数字だけを受け付け、数字以外は404。削除済み・存在しない商品は `/admin/items` に戻り、「商品が見つかりません。」を赤字で表示する。入力欄・ラジオボタン・今の画像のプレビューには今の値が入る。サイドメニューは「掲載商品一覧」が太字。一覧の「修正」のリンク先） |
| `/admin/items/update` | POST | AdminItemController | update | 必須 | 掲載商品修正機能（商品のIDは hidden の `id` で送る。ほかの項目と検証は登録と同じで、商品画像だけは任意（選ばなければ今の画像のまま。差し替えて更新が確定したあとに、古い画像が `image/product/uploads/` の中のファイルなら消す。シードの画像は消さない。更新が失敗したときも消さない）。成功すると `/admin/items` に戻り、緑の文字で「商品を修正しました。」。入力の誤りは修正ページに戻して赤字で案内し、入力を残す。重複の判定は自分自身を除く。予約可能数の上限を、すでに予約された数（取消済みを除く）より小さくすると「すでに{n}個の予約があるため、{n}以上にしてください」で更新しない（同じ数にはできる）。通常と限定は自由に切り替えられる（限定にした商品は、公開側の既存の仕組みでカートから取り除かれる）。削除済み・存在しない商品、`id` の形が正しくない場合は、何も変えずに `/admin/items` に戻り、「商品が見つかりません。」を赤字で表示する。過去の注文・予約は、注文した時点の商品名・重さ・金額を持っているため、表示は変わらない（画像は今の商品の画像を表示する）。上限を下げる修正と同時に予約が確定すると、予約数が上限を少し超えることがある。予約作成時と同じく許容する（`docs/uat/user/reservation.md` の決定）。画像の読み込み・縮小・書き出しはトランザクションの前に済ませ、ファイルを書くのは、商品の存在・重複・予約済みの数の確認が通ってから。419のときは修正ページに戻し、「もう一度修正してください。」を赤字で出して画像以外の入力を残す） |
| `/admin/items/delete` | DELETE | AdminItemController | delete | 必須 | 掲載商品削除機能（論理削除。`id` を受け取り、成功しても失敗しても `/admin/items` に戻る。成功の案内「商品を削除しました。」は表の上に緑の文字で表示する（flash の `list_status`）。削除済み・存在しない商品、`id` の形が正しくない場合は、何も変えずに「商品が見つかりません。」を赤字で表示する。削除した商品は公開側の商品一覧・トップのおすすめ・商品詳細・カート・購入・予約から消える。過去の注文・予約は、注文した時点の商品名・重さ（`item_name`・`item_weight`）を持っているため、表示は変わらない。元に戻す機能と物理削除は作らない。一覧の「削除」を押すと「{商品名} {重さ}kg を削除します。よろしいですか？」「公開ページとカートから消えます。過去の注文・予約の記録は残ります。」の確認を出す） |
| `/admin/materials` | GET | AdminMaterialController | index | 必須 | 管理画面資材一覧ページ表示（設計書にはなかった追加機能。全件を登録した順（古い順）に表示する。検索・ページ送りはない。列は資材名・在庫数・置き場所・基準値（在庫数・基準値は3桁区切り）・「修正」・「削除」。在庫数が基準値を下回った資材（`Material::isLow()`）は在庫数が赤字の太字で、資材名の下に赤字で「残りわずか」。0件のときは「登録されている資材はありません。」。追加・修正のモーダルも同じページ内。サイドメニュー・トップページの「資材一覧」のリンク先） |
| `/admin/materials/store` | POST | AdminMaterialController | store | 必須 | 資材追加機能（設計書にはなかった追加機能。`name`・`stock`・`location`・`threshold` を受け取る。成功すると `/admin/materials` に戻り、表の上に緑の文字で「資材を追加しました。」（flash の `list_status`）。入力の誤りは一覧に戻してモーダルを開いたまま項目ごとに赤字で案内し、入力を残す。検証は `MaterialRequest` を参照。ページの有効期限切れ（419）のときも一覧に戻してモーダルを開き、「ページの有効期限が切れました。もう一度追加してください。」を赤字で出して入力を残す） |
| `/admin/materials/update` | POST | AdminMaterialController | update | 必須 | 資材修正機能（設計書にはなかった追加機能。資材のIDは hidden の `id` で送る。ほかの項目と検証は追加と同じで、資材名の重複は自分自身を除いて判定する。成功すると `/admin/materials` に戻り、緑の文字で「資材を修正しました。」。並び順は変わらない。入力の誤りは一覧に戻してモーダルを開いたまま赤字で案内し、入力を残す。存在しない資材、`id` の形が正しくない場合は、何も変えずに `/admin/materials` に戻り、「資材が見つかりません。」を赤字で表示する。419のときは一覧に戻してモーダルを開き、「もう一度修正してください。」を赤字で出して入力を残す） |
| `/admin/materials/delete` | DELETE | AdminMaterialController | delete | 必須 | 資材削除機能（設計書にはなかった追加機能。物理削除。`id` を受け取り、成功しても失敗しても `/admin/materials` に戻る。成功の案内「資材を削除しました。」は表の上に緑の文字で表示する（flash の `list_status`）。存在しない資材、`id` の形が正しくない場合は、何も変えずに「資材が見つかりません。」を赤字で表示する。資材を参照するテーブルはない。一覧の「削除」を押すと「「{資材名}」を削除します。よろしいですか？」「在庫数と置き場所の記録も消えます。」の確認を出す。419のときは一覧に戻り、「ページの有効期限が切れました。もう一度削除してください。」を赤字で出す） |
| `/admin/export` | POST | AdminExportController | export | 必須 | エクスポート機能（`type` で種類を指定する。`purchase`＝注文一覧、`reservation`＝予約一覧。不正な種類はエラー。検索条件 `shipped`・`date`・`name` は各一覧の検索と同じで、ページ番号は無視して全件を出力する。入力の誤りは、種類に応じて `/admin/purchase`・`/admin/reservations` に戻してエラーを表示する（種類が不正なときは `/admin/purchase`）。.xlsx・ファイル名 `注文一覧_YYYYMMDD.xlsx`・`予約一覧_YYYYMMDD.xlsx`。発送状況が `canceled` のときだけ `注文一覧_取消済_YYYYMMDD.xlsx`・`予約一覧_取消済_YYYYMMDD.xlsx`。`canceled` のとき以外は取消済みを含めない） |
| `/admin/mail/user/{user_id}` | GET | AdminMailController | createUserMail | 必須 | メール作成ページ表示 |
| `/admin/mail/inquiry/{inquiry_id}` | GET | AdminMailController | createInquiryMail | 必須 | メール作成ページ表示 |
| `/admin/mail/send` | POST | AdminMailController | send | 必須 | メール送信機能 |

## Model

| 説明 |
|---|
| Usersテーブル操作用モデル |
| Adminsテーブル操作用モデル |
| Admin_two_factor_codesテーブル操作用モデル |
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
- 管理者の確認コードの入力ページ（追加）
- 管理画面トップページ
- 管理画面ユーザー一覧ページ
- 管理画面注文一覧ページ
- 管理画面予約一覧ページ
- 管理画面お知らせ一覧ページ
- 管理画面お知らせ作成ページ
- 管理画面お知らせ修正ページ（追加）
- 管理画面お問い合わせ一覧ページ
- 管理画面掲載商品一覧ページ
- 管理画面掲載商品登録ページ
- 管理画面掲載商品修正ページ
- 管理画面資材一覧ページ（追加）
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

### 管理画面から送るメール

| Mailable | 送るタイミング | 件名 |
|---|---|---|
| AdminMessageMail | 管理画面のメール作成ページ（`/admin/mail/user/{user_id}`、`/admin/mail/inquiry/{inquiry_id}`）で「送信する」を押し、確認で「送信する」を選んだとき（`POST /admin/mail/send`）。会員宛て（退会済みの会員にも送れる）とお問い合わせへの返信 | 【しおや農園】{管理者が入力したメールタイトル} |

送信はキューを使わず、リクエストの中で同期的に行う。宛先（To）は会員登録のメールアドレス、またはお問い合わせのメールアドレスだけ。控えを農園のアドレス（`config('mail.from.address')`）に BCC で送る（お客様には見えない）。送信履歴は保存しない。差出人は `config('mail.from')`。

本文はサンクスメールと同じ Markdown メールの枠（ヘッダー・フッター。HTML版とテキスト版）に、管理者が入力した本文だけを入れる（宛名・署名は自動で付けない）。入力した文字はエスケープし、改行はそのまま出す。お問い合わせへの返信だけ、本文の下に元のお問い合わせ（問い合わせ日 YYYY/MM/DD・内容）を引用として付ける（会員宛てには付けない）。同じ宛先に同じ内容を10秒以内に続けて送ろうとしたときは、2通目を送らない（二重送信の防止）。送信に失敗したときはログに残し、メール作成ページに入力を残して「メールを送信できませんでした。時間をおいて、もう一度お試しください。」を表示する。

### 会員登録完了メール

| Mailable | 送るタイミング | 件名 |
|---|---|---|
| RegistrationMail | 会員登録に成功したとき（`POST /register`。`RegisteredUserController` から `RegistrationMailService` を通して送る。設計書にはなかった追加機能） | 【しおや農園】会員登録ありがとうございます |

宛先は登録したメールアドレス。差出人は `config('mail.from')`。送信はキューを使わず、`defer`（`always: true`）でレスポンスを返した後に同じリクエストの中で行う。送信に失敗しても会員登録とログインは成立させ、ログに残す。入力エラーで登録されなかったときは送らない。

本文の構成（Markdownメール。HTML版とテキスト版）：宛名（「姓 名 様」）、登録のお礼、■ご登録内容（お名前・メールアドレスだけ。パスワード・住所・電話番号は載せない）、マイページで登録情報の変更・退会ができる案内、「マイページを開く」ボタン（マイページ `/mypage`。登録直後はログイン済みのため。未ログインで開いたときはログインページを経てマイページへ戻る）、心当たりがない場合の案内、送信専用の注意とお問い合わせページのURL、署名。姓・名・メールアドレスはエスケープして出す。

### パスワード再設定メール

| Mailable | 送るタイミング | 件名 |
|---|---|---|
| PasswordResetMail | パスワード再設定メールの送信ページで、登録済みのメールアドレスを送信したとき（`/forgot-password`。`User::sendPasswordResetNotification` から送る） | 【しおや農園】パスワード再設定のご案内 |
| AdminTwoFactorCodeMail | 管理者が、メールアドレスとパスワードで管理者用ログイン（`POST /admin/login`）を通ったとき、または確認コードの入力ページで「コードを再送する」を押したとき（二段階認証がオンのときだけ。`AdminTwoFactorService` から同期送信する。宛先は管理者のメールアドレス、宛名は管理者名。数字6桁のコードと有効期限（10分）を載せ、心当たりがない場合はパスワードの再設定をすすめる。送信に失敗したときはコードを削除してログインページに戻す） | 【しおや農園】管理画面のログイン確認コード |
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
| お知らせ内容 | 入力必須、文字列、2000文字以下（2000文字の上限は設計書にない追加の決まり） |

お知らせの修正（`NoticeUpdateRequest`。設計書にない追加）は、同じ項目に、お知らせのID（必須・1以上の整数。正しくないときは存在しないお知らせと同じ扱い）を加えたもの。削除（`NoticeDeleteRequest`）はお知らせのIDだけ、検索（`NoticeSearchRequest`）は作成日（Y-m-d）だけ。

### MaterialRequest.php（資材の追加・修正）

設計書にない追加。前後の空白（全角を含む）は取り除き、在庫数・基準値の全角の数字は半角にし、数字だけの値は先頭の0を取り除いて（「05」→5）から検証する。入力の誤りは、モーダルを開いたままの `/admin/materials` に戻す。

| フォーム | ルール |
|---|---|
| 資材名 | 入力必須、文字列、50文字以下、重複不可（修正では自分自身を除く） |
| 在庫数 | 入力必須、整数、0以上、99999以下 |
| 置き場所 | 入力必須、文字列、100文字以下 |
| 基準値 | 入力必須、整数、0以上、99999以下 |

資材の修正（`MaterialUpdateRequest`）は、同じ項目に、資材のID（必須・1以上の整数。正しくないときは存在しない資材と同じ扱い）を加えたもの。削除（`MaterialDeleteRequest`）は資材のIDだけ。

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
| 発送状況（shipped） | 必須ではない、0・1・canceled のどれか |
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
- エクスポートは、発送状況が `canceled` のとき以外は取消済みを含めない。`canceled` のときは取消済みだけを出力する。列は変えない。ファイル名は、`canceled` のときだけ `注文一覧_取消済_YYYYMMDD.xlsx`・`予約一覧_取消済_YYYYMMDD.xlsx`（ASCII 側は `purchase_canceled_YYYYMMDD.xlsx`・`reservation_canceled_YYYYMMDD.xlsx`）にする。シート名は変えない。
- 発送済みの更新（`/admin/purchase/update`・`/admin/reservations/update`）は、取消済みの注文・予約を更新せず、409（`{"message": "取り消されているため、発送状況を変更できません。ページを再読み込みしてください。"}`）を返す。。検証のあとに物理削除されるなどで存在しない注文・予約は、404（`{"message": "注文が見つかりません。"}`。予約は「予約が見つかりません。」）を返す

### AdminPurchaseCancelRequest.php・AdminReservationCancelRequest.php（注文・予約の取消）

入力の誤りは、取消の前に見ていた一覧（直前のページが注文一覧・予約一覧の検索結果のとき。それ以外は一覧の全件）に戻して、エラーを表示する。存在するか・発送済みか・取消済みかは、行をロックするリポジトリで判定する。

| フォーム | ルール |
|---|---|
| 注文／予約（id） | 入力必須、整数値 |

### InquirySearchRequest.php（お問い合わせ検索）・AdminInquiryUpdateRequest.php（対応済みチェックの更新）

入力の誤りは、お問い合わせ検索（InquirySearchRequest）は `/admin/inquiries`（全件の表示）に戻して、検索欄の近くにエラーを表示する。対応済みチェックの更新（AdminInquiryUpdateRequest）は fetch への JSON（422）で返す。

| フォーム | ルール |
|---|---|
| 対応状況（addressed） | 必須ではない、`0`・`1` のみ |
| 問い合わせ日（date） | 必須ではない、Y-m-d の日付 |
| お問い合わせ（id）※更新 | 入力必須、整数値、1以上（存在するかは検証せず、更新の処理が404で返す） |
| 対応済み（addressed）※更新 | 入力必須、真偽値 |

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

### AdminTwoFactorRequest.php（管理者の確認コードの入力）

入力の誤りは確認コードの入力ページ（`/admin/two-factor`）に戻す。全角数字は半角に、前後の空白（全角を含む）は取り除いてから検証する（`prepareForValidation`）。

| フォーム | ルール |
|---|---|
| 確認コード（code） | 入力必須（「確認コードを入力してください」）、文字列、数字6桁（「確認コードは6桁の数字で入力してください」）。ここで入力エラーになるものは失敗回数に数えない。6桁の数字で、コードが違うものだけが失敗回数に数えられる |

### MailRequest.php（メール作成）

| フォーム | ルール |
|---|---|
| メールタイトル（項目名 `mail_title`） | 入力必須、文字列、255文字以下（前後の空白は取り除く） |
| メール本文（項目名 `mail_body`） | 入力必須、文字列、10,000文字以下（設計書にない追加の上限）。先頭・末尾の空白（全角スペースの字下げを含む）は取り除かない（`TrimStrings` の対象から項目名で外している）。空白だけの本文は未入力と同じ |

宛先のメールアドレスはフォームで受け取らない。フォームの hidden で送る宛先の種類（`user`・`inquiry`）とIDからサーバーで引き直す（不正な種類・ID、存在しない会員・お問い合わせは404。リクエストにメールアドレスを足しても使わない）。エラーのときは、そのメール作成ページに入力を残して戻す。

## 管理画面のキャッシュ

管理者のログインが必要なページ（`auth:admin` のルート）の応答には `Cache-Control: no-store` を付ける（ミドルウェア `no-store`）。ログアウト後にブラウザの戻るボタンで管理画面が表示されないようにするため。公開側・ログイン画面には付けない。
