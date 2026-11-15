# UAT：設計書との差分確認によるコードの修正

| 項目 | 内容 |
|---|---|
| 対象ルート | GET /（top.index）、GET /items（shop.items.index）、GET /items/{item}、GET /cart、GET /purchase、GET /mypage、GET /commercial-transaction（特商法）、購入・予約のサンクスページ、振込先のご案内、GET /login ほか認証画面、管理画面のフッター・一覧、Fortify の /user/confirm-password・/user/confirmed-password-status |
| ブランチ | fix/doc-diff |
| 根拠 | 設計書（docs/01〜07）と実装の差分確認（2026-11-14）でユーザーOKとなった「コード修正」、docs/02-initial-settings.md |
| 実施日 | 2026-11-15 |

管理画面の項目（© 表記・一覧の長い文字）も、同じブランチで直すためこのファイルにまとめる。

## 受け入れ条件

| ID | 条件 |
|---|---|
| AC-1 | © 表記が、公開側のフッター・認証画面・管理画面のフッターのすべてで「©Shioya Farm」に揃う（認証画面の「farm」の小文字は誤りなので直す）。© の後ろに空白は入れない（公開側フッターの「© Shioya Farm」の空白も消す。2026-11-15 ユーザー指示） |
| AC-2 | 発送の目安の「3~5営業日」が、購入サンクスページ・振込先のご案内・特商法のページで「3〜5営業日」になる |
| AC-3 | トップページのサブコピーの先頭が「ー」（長音）ではなく「―」（ダッシュ）になる：「―野菜のことを誰より知る人から、まっすぐあなたへ。」 |
| AC-4 | 予約完了の画面と予約のサンクスメール、予約の振込先のご案内で、発送時期が「商品は9月中に発送いたします。」に揃う（02「発送は9月中」・商品一覧の「北の恵みセットは9月中の発送となります。」に合わせる。振込先のご案内は「下記の口座へお振込みください。お振込みの確認後、商品は9月中に発送いたします。」） |
| AC-5 | ページの og:description・description が「北海道の小さな家族農家『しおや農園』の直販サイトです。じゃがいも・玉ねぎ・人参を産地から直接お届けします。（ポートフォリオ用の架空のサイトです）」になる |
| AC-6 | 商品一覧のご案内に「保存方法」の見出しで、02 の3項目（冷暗所で保存・到着後はお早めに・人参は冷蔵で保存推奨）が、「お届けについて」と「返品について」の間に出る |
| AC-7 | シーダーで作る北の恵みセット（5kg・10kg）と、開発DBの同じ2件の商品紹介文に内訳（5kg：じゃがいも2kg・玉ねぎ2kg・人参1kg／10kg：じゃがいも4kg・玉ねぎ4kg・人参2kg）が入る |
| AC-8 | 特商法の「その他必要になる費用」に代金引換の手数料330円（税込）が、「お支払い方法」の代金引換に手数料330円（税込）が書かれる。金額は送料の設定（代引手数料）から出す |
| AC-9 | トップの「おすすめ商品」と /items の商品カードで、商品名・紹介文が長くても（空白のない長い英数字を含む）カードからはみ出さない。カードでは商品名は2行、紹介文は3行までで「…」で省略し、カードの高さが揃う |
| AC-10 | 商品詳細・カート・購入・予約・マイページの履歴と詳細モーダル・管理画面の一覧と詳細でも、長い商品名がはみ出さず、横スクロールが出ない（こちらは省略せず折り返して全文を出す） |
| AC-11 | /user/confirm-password（GET・POST）と /user/confirmed-password-status は、ログインの有無にかかわらず 404 になる（使っていない Fortify の機能で、GET はビューがなく 500 になるおそれがあるため） |
| AC-12 | 画面に出ない修正（UATの対象外。PHPUnit・./scripts/verify.sh・目視で確認）：未使用の shop/payment.blade.php を消す、User の casts から email_verified_at を消す、Item の recommend・is_reserved_only を真偽値として扱う、環境変数の雛形の既定値を Docker 構成（DB=mysql、メール=Mailpit の smtp:1025、APP_NAME）に合わせる、composer.json の name・description をこのプロジェクトのものにする、Dockerfile から使っていない imagick を外す（コンテナを作り直しても画像の縮小・WebP 変換が動く） |
| AC-13 | これまでの機能が変わらず動く（PHPUnit 全件・E2E 全件が通る） |

## シナリオ

| ID | 対応AC | 画面幅 | 前提 | 手順 | 期待結果 |
|---|---|---|---|---|---|
| S-1 | AC-1 | PC/SP | なし | トップ・/login・/admin/login・管理画面トップ（ログイン後）を開き、フッターの © を見る | どれも空白なしの「©Shioya Farm」。「farm」の小文字はない |
| S-2 | AC-2 | PC | 会員でログインし、代金引換・銀行振込で購入できる | 代金引換で購入してサンクスページを見る。銀行振込の振込先のご案内を見る（残高のない会員）。/commercial-transaction を開く | どれも「3〜5営業日」で、「3~5」はない |
| S-3 | AC-3 | PC/SP | なし | トップを開く | サブコピーが「―野菜のことを誰より知る人から、まっすぐあなたへ。」（SPの改行位置はこれまでどおり） |
| S-4 | AC-4 | PC | 会員でログインし、予約限定商品を予約できる。Mailpit を確認できる | 予約を完了させ、予約完了の画面とサンクスメールを見る。銀行振込で予約し、振込先のご案内を見る | 3つとも発送時期が「9月中」の同じ文言 |
| S-5 | AC-5 | PC | なし | トップ・/items のページのソースで og:description・description を見る | しおや農園のサイトの説明で、ポートフォリオであることがわかる。「TAKAYOSHI SHIOYAの個人開発ウェブサイト」ではない |
| S-6 | AC-6 | PC/SP | なし | /items のご案内を見る | 「お届けについて」の次に「保存方法」があり、3項目が出る。その次が「返品について」 |
| S-6b | AC-7 | PC/SP | 開発DBの北の恵みセット2件の紹介文を、メインセッションがシーダーと同じ文に書き換えておく | /items と北の恵みセット（5kg・10kg）の商品詳細を開く | 紹介文に内訳（5kg：じゃがいも2kg・玉ねぎ2kg・人参1kg／10kg：じゃがいも4kg・玉ねぎ4kg・人参2kg）が出る |
| S-7 | AC-8 | PC/SP | なし | /commercial-transaction を開く | 「その他必要になる費用」と「お支払い方法」に代引手数料330円（税込）がある |
| S-8 | AC-9 | PC/SP | 長い商品名（全角50字程度・空白のない半角英数字80字程度の2件）と長い紹介文（全角300字・空白のない半角英数字300字）の公開中・おすすめの商品をUAT用に登録する | トップの「おすすめ商品」と /items を開く | 商品名は2行、紹介文は3行までで「…」になり、カードからはみ出さない。他の商品のカードと高さ・並びが揃う。横スクロールなし |
| S-9 | AC-10 | PC/SP | S-8 の商品をカートに入れて購入できる会員でログインする | 商品詳細・カート・購入ページを開き、購入を完了してマイページの購入履歴と詳細モーダルを見る。管理画面の注文一覧・詳細、掲載商品の一覧も見る | 長い商品名が枠の中で折り返され、全文が読める。横スクロールなし |
| S-10 | AC-11 | PC | なし | 未ログインと会員でログインした状態で、/user/confirm-password と /user/confirmed-password-status を開く（POST は curl などで送る） | すべて 404（ログイン中は 404 ページ。500 にならない） |

## 実施結果

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | OK | OK | 公開側フッター（/・/items・/commercial-transaction）・/login・/admin/login・管理者の確認コード入力画面・管理画面トップ（ログイン後）・注文一覧・掲載商品一覧のフッターが、PC・SPとも「©Shioya Farm」（空白なし。「farm」の小文字はない）。横スクロールなし | screenshots/doc-diff/S-1-top-pc.png、screenshots/doc-diff/S-1-top-sp.png、screenshots/doc-diff/S-1-login-pc.png、screenshots/doc-diff/S-1-login-sp.png、screenshots/doc-diff/S-1-adminlogin-pc.png、screenshots/doc-diff/S-1-adminlogin-sp.png、screenshots/doc-diff/S-1-admin2fa-pc.png、screenshots/doc-diff/S-1-admintop-pc.png、screenshots/doc-diff/S-1-admintop-sp.png |
| S-2 | OK | - | 代金引換のサンクス（注文番号223。商品はお支払い確定後3〜5営業日以内、代金引換はご注文確定後3〜5営業日以内に発送いたします。）、銀行振込の振込先のご案内（残高なしの会員。お振込みの確認後、3〜5営業日以内に発送いたします。）、特商法ページ（お届け時期の2箇所）のすべてが「3〜5営業日」で、「3~5」はない。なお特商法ページの実際のURLは /law ではなく /commercial-transaction（/law は404）。SPも参考に撮影 | screenshots/doc-diff/S-2-thanks-pc.png、screenshots/doc-diff/S-2-thanks-sp.png、screenshots/doc-diff/S-2-banktransfer-pc.png、screenshots/doc-diff/S-2-banktransfer-sp.png、screenshots/doc-diff/S-7-pc.png |
| S-3 | OK | OK | サブコピーが「―野菜のことを誰より知る人から、まっすぐあなたへ。」（先頭は「―」）。SPは「―野菜のことを誰より知る人から、」の後で改行し「まっすぐあなたへ。」 | screenshots/doc-diff/S-3-pc.png、screenshots/doc-diff/S-3-sp.png |
| S-4 | OK | - | 銀行振込で予約（予約番号135、UAT上限なし予約 5kg）。予約完了の画面：「商品は9月中に発送いたします。」。サンクスメール（Mailpit）：「商品は9月中に発送いたします。」。振込先のご案内：「下記の口座へお振込みください。お振込みの確認後、商品は9月中に発送いたします。」。3つとも「9月中」で揃っている。SPも参考に撮影 | screenshots/doc-diff/S-4-banktransfer-pc.png、screenshots/doc-diff/S-4-banktransfer-sp.png、screenshots/doc-diff/S-4-thanks-pc.png、screenshots/doc-diff/S-4-thanks-sp.png |
| S-5 | OK | - | /・/items・/login の description・og:description が「北海道の小さな家族農家『しおや農園』の直販サイトです。じゃがいも・玉ねぎ・人参を産地から直接お届けします。（ポートフォリオ用の架空のサイトです）」で一致。「TAKAYOSHI SHIOYAの個人開発ウェブサイト」ではない（ブラウザ内の meta 要素で確認。PCの幅で確認） | なし（ソース確認のため） |
| S-6 | OK | OK | ご案内は「送料について」「支払方法について」「お届けについて」「保存方法」「返品について」の順。「保存方法」を開くと3項目（冷暗所で保存してください。／生鮮品のため、到着後はお早めにお召し上がりください。／人参は冷蔵での保存をおすすめします。）。次が「返品について」。事実（判定はユーザー）：PC（1280px）は4列グリッドで、1行目に「送料について」「支払方法について」「お届けについて」「保存方法」の4つ、2行目の左端に「返品について」が1つだけ出る（右の3列は空き）。SPは5項目が縦に並ぶ。閉じた状態・全部開いた状態の両方を撮影。UAT後の追記（2026-11-15 ユーザー指示）：PCの並びを、4列（返品だけ2行目）→ 5列・1行（圧迫感があり取り消し）→ lg以上で3列・2行目の2項目を中央に変更。1024・1280・1440pxで3＋2・2行目が中央・横スクロールなし、414pxは1列のまま（implementer が Playwright で確認）。さらにユーザー指示でPCの行の間を12px→40px（閉じた案内1個分）に広げた（1024・1280pxで実測40px、414pxは12pxのまま） | screenshots/doc-diff/S-6-pc.png（全部開いた状態）、screenshots/doc-diff/S-6-pc-closed.png、screenshots/doc-diff/S-6-sp.png、screenshots/doc-diff/S-6-sp-closed.png、screenshots/doc-diff/S-6-3col-1280.png ほか S-6-3col-1024/1440/414.png |
| S-6b | OK | OK | 北の恵みセット 5kg（id10）の詳細：「甘み豊かなにんじん、玉ねぎ、じゃがいもの詰め合わせです（じゃがいも2kg・玉ねぎ2kg・人参1kg）」、10kg（id11）：「……（じゃがいも4kg・玉ねぎ4kg・人参2kg）」。横スクロールなし。/items の商品カードには紹介文が出ない仕様（名前・価格・ボタンのみ）のため、/items では紹介文を確認できない。紹介文はトップのおすすめ商品のカード（北の恵みセット 5kg はおすすめ設定。表示はランダム3件）と商品詳細に出る | screenshots/doc-diff/S-6b-5kg-pc.png、screenshots/doc-diff/S-6b-5kg-sp.png、screenshots/doc-diff/S-6b-10kg-pc.png、screenshots/doc-diff/S-6b-10kg-sp.png |
| S-7 | OK | OK | /commercial-transaction の「その他必要になる費用」に「代金引換の場合は、別途手数料330円（税込）がかかります。」、「お支払い方法」に「代金引換（手数料330円・税込）」。横スクロールなし | screenshots/doc-diff/S-7-pc.png、screenshots/doc-diff/S-7-sp.png |
| S-8 | OK | OK | トップのおすすめ商品（ランダム3件。長文3件が出るまで再読込して計測）：長い商品名（全角52字・英数82字・英数255字）は2行で「…」、紹介文は3行で「…」。文字はカードの右端を超えない。PCは同じ行のカードの高さが全部460pxで揃った（長文2件＋通常1件の並びで確認。通常商品の紹介文は1行で、価格はカード下端に寄る）。SPは1列のため、カードの高さは内容で違う（通常商品348〜396px、長文424px）。/items：長い商品名のグループの見出し（2行・「…」）とカードの商品名（2行・「…」）がはみ出さない（PCのカード390px・SPのカード396px。ボタンはカード内）。/items のカードは同じ名前の商品でグループ分けされるため、長い商品名と通常の商品名が同じ行に並ぶ場面は作れなかった（行の中の高さ揃えはトップで確認）。横スクロールなし | screenshots/doc-diff/S-8-top-pc.png、screenshots/doc-diff/S-8-top-sp.png、screenshots/doc-diff/S-8-items-1-pc.png〜S-8-items-3-pc.png（1=全角、2=英数82字、3=英数255字）、screenshots/doc-diff/S-8-items-1-sp.png〜S-8-items-3-sp.png |
| S-9 | OK | OK | 商品詳細（id52・53・54）・カート・購入ページ：長い商品名が折り返されて全文が出る（255字の名前も末尾まで確認）。横スクロールなし。代金引換で購入（注文番号223、3商品、合計3,360円＝小計1,500＋送料1,530＋代引330）。マイページの購入履歴の表と詳細モーダル：折り返されて全文が読め、横スクロールなし（SP・PCとも）。管理画面の注文一覧、注文の詳細モーダル、掲載商品の一覧（PCは表、SPはカード）、掲載商品の詳細モーダル：折り返されて全文が出る。横スクロールなし。予約ページ・予約履歴・予約一覧は、長い商品名の予約限定商品を作っていないため確認していない（シナリオの対象外） | screenshots/doc-diff/S-9-show-52-pc.png〜S-9-show-54-sp.png（screenshots/doc-diff/S-9-show-<商品ID>-<pc|sp>.png）、screenshots/doc-diff/S-9-cart-pc.png、screenshots/doc-diff/S-9-cart-sp.png、screenshots/doc-diff/S-9-purchase-pc.png、screenshots/doc-diff/S-9-purchase-sp.png、screenshots/doc-diff/S-9-mypage-pc.png、screenshots/doc-diff/S-9-mypage-sp.png、screenshots/doc-diff/S-9-mypage-modal-pc.png、screenshots/doc-diff/S-9-mypage-modal-sp.png、screenshots/doc-diff/S-9-admin-orders-pc.png、screenshots/doc-diff/S-9-admin-orders-sp.png、screenshots/doc-diff/S-9-admin-order-modal-pc.png、screenshots/doc-diff/S-9-admin-order-modal-sp.png、screenshots/doc-diff/S-9-admin-items-long-pc.png、screenshots/doc-diff/S-9-admin-items-long-sp.png、screenshots/doc-diff/S-9-admin-itemdetail-pc.png、screenshots/doc-diff/S-9-admin-itemdetail-sp.png |
| S-10 | OK | - | 未ログイン（curl）：GET・POST とも /user/confirm-password・/user/confirmed-password-status は404。会員ログイン中（ブラウザ）：GET は404ページ（「ページが見つかりませんでした」。ヘッダーはログイン中の表示で「マイページ」あり）、POST（ブラウザ内の fetch、CSRFトークン付き）も404。500にならない。SPの404ページも参考に撮影 | screenshots/doc-diff/S-10-confirm-pc.png、screenshots/doc-diff/S-10-status-pc.png、screenshots/doc-diff/S-10-confirm-sp.png |

- コンソールエラー：なし（出たのは、S-10 で意図的に開いた /user/confirm-password・/user/confirmed-password-status の404の「Failed to load resource」だけ。最初に誤って /law を開いたときの404も同種）
- メール（Mailpit）：予約サンクスメール（宛先 uat-buy@example.com、件名「【しおや農園】ご予約ありがとうございます（予約番号：135）」）の本文に「商品は9月中に発送いたします。」。購入サンクスメール（注文番号223）は送信された（本文に営業日の記載なし）。管理者のログイン確認コードのメール（宛先 admin@admin.com）を受け取ってログインした
- 追加したデータ：items に3件（id52「UAT長文」＋全角あいうえおかきくけこ繰り返し＝名前52字、id53「UAT長文」＋英数82字、id54「UAT長文」＋英数255字。いずれも2kg・500円・おすすめ・予約限定でない・紹介文は全角300字または空白なし英数300字）。purchase_histories に1件（id223、代金引換、uat-buy@example.com、id52〜54を1個ずつ、合計3,360円）、reservations に1件（予約番号135、UAT上限なし予約 id19、銀行振込）と reservation_bank_transfers に1件、Stripe のテスト用の振込先（銀行振込の購入の振込先のご案内を開いたため。購入としては確定していない）。uat-buy@example.com のカートに じゃがいも2kg×1 が残っている（銀行振込の購入のご案内を開いただけで確定していないため）

## ユーザー確認チェックリスト

- [x] 長い商品名・紹介文のカードで、省略のされ方（2行・3行）が見やすい
- [x] og:description・description の文言がサイトの説明として自然
- [x] 予約の発送時期の文言（画面・メール・振込先のご案内）がわかりやすい
- [x] 商品一覧の「保存方法」の文言・位置が自然
- [x] 特商法の代引手数料の書き方が自然

## 判定

- 受け入れ：済
- コメント：チェック項目をすべてクリアしているので受け入れ済とします
