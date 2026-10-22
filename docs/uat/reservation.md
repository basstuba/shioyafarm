# UAT：予約

| 項目 | 内容 |
|---|---|
| 対象ルート | GET /reservation/{item}（shop.reservation.index）、POST /reservation/payment（shop.reservation.payment）、GET /reservation/success（shop.reservation.success）、GET /reservation/bank-transfer（shop.reservation.bank-transfer）、POST /reservation/bank-transfer/cancel（shop.reservation.bank-transfer.cancel）、POST /reservation/bank-transfer/confirm（shop.reservation.bank-transfer.confirm）、GET /reservation/thanks（shop.reservation.thanks）、POST /add-address（shop.checkout.add-address。既存） |
| ブランチ | feat/reservation |
| 根拠 | docs/03-page-list.md「予約ページ」「予約サンクスページ」、docs/04-feature-list.md「予約ページ表示機能」「予約サンクスページ表示機能」「商品予約機能」、docs/07-basic-design.md（ReservationRequest）、docs/02-initial-settings.md「詰め合わせセットについて」「予約について」、docs/05-table-specification.md（reservations）、.claude/rules/shipping.md |
| デザイン | Figma MCPの上限リセット（2026-11-01）まではフロントエンドを保留。ユーザーが渡した画面画像（予約内容ページPC、予約サンクスページPC）を参考にした仮のBladeで実装する。見た目は11月1日以降にFigmaの値で仕上げる |
| 実施日 | 2026-10-22 |

ユーザーと決めた事項：

- 予約は購入ページとほぼ同じ構成にする。支払いは予約時の先払いで、支払方法はクレジットカードと銀行振込の2つ（代金引換なし）。
- クレジットカードは購入と同じく Stripe Checkout で支払い、/reservation/success に戻ったときにStripeへ決済状態を問い合わせてから予約データを保存する。
- 銀行振込は購入と同じ流れにする：「予約を確定する」→「振込先のご案内」ページ（「戻る」「振込み」）→「振込み」で予約データを保存してサンクスページへ。振込先（PaymentIntent ID、銀行名・支店名・口座種別・口座番号・口座名義・振込金額）は新しいテーブル `reservation_bank_transfers` に保存する（後のメール・マイページで使う）。
- reservations テーブルに `shipping_fee`・`total_price`（integer）を追加するマイグレーションを新しく作る（既存のマイグレーションは書き換えない）。`cod_fee` は追加しない。
- 予約可能数（reservation_limit）は「予約を確定する」の時点でだけ確認する。すでに予約された数量の合計＋今回の数量が予約可能数を超える場合はエラーにして先へ進ませない。Stripeで支払っている間に他の人の予約と重なって数個超える分は許容する（保存時の再確認・自動返金はしない）。

Claudeが決めた事項（OKをもらう）：

- 予約ページは、予約限定の商品（is_reserved_only）1つだけを扱う。予約限定でない商品・存在しない商品のIDでは404。カートは使わず、変えもしない。
- 商品詳細の「予約する」ボタンのリンク先を予約ページにする。
- 予約がすでに予約可能数に達している商品は、商品詳細の「予約する」ボタンを押せない「予約受付終了」の表示にし、予約ページを直接開いた場合は商品詳細へ戻して「この商品の予約受付は終了しました」と表示する。
- 数量の上限は、10と残数（予約可能数 − 予約済みの合計）の小さい方。予約可能数が未設定（null）の商品は上限10だけ。
- 画面の操作（数量変更、お届け先住所の切り替え、支払方法の選択、住所追加モーダル）は購入ページと同じく Livewire で、ページを再読み込みせずに金額を更新する。お届け先の初期値・住所の選択肢・「確認用お届け先住所」・住所追加モーダルの動きは購入ページと同じ。
- 送料の計算は購入と同じ（お届け先の都道府県と合計重量から。30kg超は2個口）。
- ページ下部の文言はデザインどおり「ご予約内容をご確認のうえ、よろしければ「予約を確定する」ボタンを押してください」（設計書の「こちらの商品で宜しければ決済ページへお進みください」から変更）。
- 設計書は予約の決済を /payment（PaymentController）にまとめているが、購入と予約で処理が分かれるため、予約は /reservation/payment など /reservation/ 以下のルートを追加する（上の対象ルートのとおり）。
- サンクスページの背景は購入と同じ `storage/app/public/image/tool/thanks-page-photo.webp`。カード・銀行振込とも保存直後の1回だけ表示し、再読み込み・直接アクセスではトップページへリダイレクトして予約データを作らない。
- Stripeの決済ページ・振込先のご案内の「戻る」では予約ページへ戻り、入力していた内容が残る（戻った直後の1回だけ。購入と同じ）。
- 二重送信の対策（1回限りの注文トークン、ボタンを押した直後の無効化、ユーザー単位のロック）は購入と同じ仕組みを使う。
- 予約確認メールとマイページの予約履歴はこのブランチでは作らない（購入サンクスメール・マイページの実装時に入れる）。

## 受け入れ条件

| ID | 条件 |
|---|---|
| AC-1 | 未ログインで予約ページを開くとログインページへ移動し、ログイン後に予約ページへ戻る |
| AC-2 | 商品詳細（予約限定の商品）の「予約する」で予約ページが開き、商品（画像・商品名・単価・数量1・小計）、小計・送料・合計が表示される。お届け先氏名・電話番号・住所には会員登録の内容が入っている |
| AC-3 | 予約限定でない商品・存在しない商品では予約ページが開かない（404） |
| AC-4 | 送料は、選んだお届け先住所の都道府県と商品の合計重量から計算される（送料案内ページの料金表と同じ金額。30kgを超えると2個口）。数量や住所を変えると、ページを再読み込みせずに小計・送料・合計が更新される |
| AC-5 | 支払方法はクレジットカードと銀行振込の2つだけが表示され、代金引換は選べない（直接送っても予約されない） |
| AC-6 | 「＋新しい住所を追加」で住所を追加でき、お届け先住所の選択肢に加わって選択される（購入ページと同じ） |
| AC-7 | 入力に誤りがある（氏名が空、電話番号が10〜11桁の数字でない、数量が1〜10の範囲外、支払方法未選択）と「予約を確定する」でエラーが表示され、予約されない |
| AC-8 | すでに予約された数量の合計＋今回の数量が予約可能数を超えると、「予約を確定する」でエラーが表示され、予約されない。数量の入力の上限は10と残数の小さい方 |
| AC-9 | 予約が予約可能数に達した商品は、商品詳細の「予約する」が押せない「予約受付終了」になり、予約ページを直接開くと商品詳細へ戻されて受付終了の案内が表示される |
| AC-10 | クレジットカードを選んで「予約を確定する」を押すとStripeの決済ページへ移動し、支払いを済ませるとサンクスページが表示され、予約データ（商品・お届け先・支払方法・単価・数量・小計・送料・合計）が保存される |
| AC-11 | 銀行振込を選んで「予約を確定する」を押すと「振込先のご案内」ページに振込先と振込金額が表示される。「振込み」でサンクスページが表示され、予約データと振込先が保存される。「戻る」で予約ページへ戻り、入力内容が残り、予約データは作られない |
| AC-12 | Stripeの決済ページから戻った場合、サンクスページを再読み込み・直接開いた場合、「予約を確定する」を2回押した場合に、予約データが作られない・二重に作られない |
| AC-13 | サンクスページに背景画像と「ご予約ありがとうございます。お届けまで、どうぞ楽しみにお待ちください。」「商品は9月より、準備が整い次第順次発送いたします。」「ホームに戻る」（トップページへのリンク）が表示される |
| AC-14 | 予約してもカートの中身は変わらない |
| AC-15 | PC・SPとも横スクロールが出ず、各項目が操作できる |

## シナリオ

金額の前提：会員登録の住所は北海道。商品はシーダーのとおり（北の恵みセット5kg 825円・予約可能数300、北の恵みセット10kg 1,450円・予約可能数200、人参2kg 418円は予約限定でない）。予約可能数のシナリオは、開発用DBの北の恵みセットを締め切らないよう、tinkerで追加する「UAT予約商品」（予約限定、重さ5kg、価格1,000円、予約可能数3）を使う。

| ID | 対応AC | 画面幅 | 前提 | 手順 | 期待結果 |
|---|---|---|---|---|---|
| S-1 | AC-1 | PC | 未ログイン | 北の恵みセット10kgの予約ページ（/reservation/{id}）を開き、ログインする | ログインページへ移動し、ログイン後に同じ予約ページへ戻る |
| S-2 | AC-2, AC-4, AC-5 | PC/SP | ログイン済み（住所：北海道） | 商品詳細（北の恵みセット10kg）の「予約する」を押す | 予約ページに北の恵みセット10kg×1が表示され、小計1,450円、送料1,530円（10kg・100サイズ）、合計2,980円。氏名・電話番号・住所に会員登録の内容が入り、確認用お届け先住所に北海道から始まる住所が表示される。支払方法はクレジットカードと銀行振込の2つだけ |
| S-3 | AC-3 | PC | ログイン済み | 人参2kg（予約限定でない）のIDと、存在しないIDで /reservation/{id} を開く | どちらも404 |
| S-4 | AC-4 | PC | S-2の状態 | 数量を1→2に変える | ページを再読み込みせずに小計2,900円、送料2,190円（20kg・140サイズ）、合計5,090円に更新される |
| S-5 | AC-4 | PC | S-2の状態。東京都の配送先住所が登録済み | お届け先住所を東京都の住所に切り替える | 送料2,050円、合計3,500円に更新され、確認用お届け先住所が東京都の住所になる |
| S-6 | AC-4 | PC | S-2の状態 | 数量を4に変える | 小計5,800円、送料4,590円（180サイズ3,060円＋100サイズ1,530円の2個口）、合計10,390円 |
| S-7 | AC-4 | PC | ログイン済み（住所：北海道） | 商品詳細（北の恵みセット5kg）の「予約する」を押す | 小計825円、送料1,230円（5kg・80サイズ）、合計2,055円 |
| S-8 | AC-6 | PC/SP | S-2の状態 | 「＋新しい住所を追加」を押し、都道府県「東京都」、住所「UAT区予約町1-2-3」を入力して「住所を追加」を押す | モーダルが閉じ、お届け先住所に「東京都UAT区予約町1-2-3」が選択され、送料2,050円・合計3,500円に変わる |
| S-9 | AC-7 | PC/SP | S-2の状態 | 氏名を空にして「予約を確定する」。電話番号を「090-1234-5678」「123456789」にして押す | それぞれエラーが表示され、Stripeへ移動せず、予約データも作られない。入力していた値は残る |
| S-10 | AC-7 | PC | S-2の状態 | 数量に0、11を入力して「予約を確定する」を押す | 数量のエラーが表示され、予約されない |
| S-11 | AC-5 | PC | S-2の状態 | 開発者ツールなどで payment_method=cod にして送信する | エラーになり、予約データは作られない |
| S-12 | AC-8 | PC | ログイン済み。UAT予約商品（予約可能数3）に、別の会員の予約が数量1で1件ある（残数2） | UAT予約商品の予約ページを開き、数量を3にして「予約を確定する」を押す | 数量の入力の上限が2になっている。3で送ると「予約可能数を超えています」の旨（残り2個）のエラーが表示され、予約されない |
| S-13 | AC-8, AC-9, AC-11 | PC | S-12の状態 | 数量2・銀行振込で予約を完了する。商品詳細（UAT予約商品）を開き、予約ページ（/reservation/{id}）を直接開く | 予約データが保存される（合計数量3）。商品詳細の「予約する」が「予約受付終了」になって押せない。予約ページを直接開くと商品詳細へ戻され、「この商品の予約受付は終了しました」が表示される |
| S-14 | AC-10, AC-14 | PC/SP | S-2の状態。Stripeのテストキーが設定済み。カートに人参2kg×1 | クレジットカードを選び「予約を確定する」→ Stripeでテストカード 4242 4242 4242 4242 で支払う | Stripeの決済ページに北の恵みセット10kgと送料1,530円、合計￥2,980が円で表示される。支払い後サンクスページ（/reservation/success）が表示される。reservations に1件（item=北の恵みセット10kg、quantity=1、price=1450、subtotal=1450、shipping_fee=1530、total_price=2980、payment_method=card、お届け先が入力どおり）が保存される。カートには人参2kg×1が残っている |
| S-15 | AC-11 | PC/SP | S-2の状態。Stripeで銀行振込が有効 | 銀行振込を選び「予約を確定する」→「振込先のご案内」で「振込み」を押す | 振込先のご案内に銀行名・支店名・口座種別・口座番号・口座名義と振込金額2,980円が表示される。「振込み」でサンクスページ（/reservation/thanks）が表示され、payment_method=customer_balance、shipping_fee=1530、total_price=2980 の予約データと、表示されたものと同じ振込先・振込金額2,980円の reservation_bank_transfers 1件が保存される |
| S-16 | AC-11, AC-12 | PC/SP | S-2の状態 | 氏名を「UAT予約戻る」、支払方法を銀行振込にして「予約を確定する」→「振込先のご案内」で「戻る」を押す | 予約ページへ戻り、入力していた内容（氏名・電話番号・住所・支払方法・数量）が残っている。予約データは作られない |
| S-17 | AC-12 | PC | S-2の状態 | クレジットカードで「予約を確定する」→ Stripeの決済ページで「戻る」を押す | 予約ページへ戻り、入力していた内容が残っている。予約データは作られない |
| S-18 | AC-12 | PC | S-2の状態 | クレジットカードで「予約を確定する」→ Stripeでテストカード 4000 0000 0000 0002 で支払う | カードが拒否された旨がStripeの画面に表示され、サンクスページへは移動しない。予約データは作られない |
| S-19 | AC-12 | PC | S-14・S-15の直後 | サンクスページを再読み込みする。ブラウザの戻るで「振込先のご案内」を開き直し「振込み」を押す。/reservation/success と /reservation/thanks を直接開く | いずれもトップページへ移動し、予約データは増えない |
| S-20 | AC-12 | PC | S-2の状態 | 銀行振込で「予約を確定する」をすばやく2回押し、「振込み」を押す | 押した直後にボタンが無効になり、予約データは1件だけ作られる |
| S-21 | AC-13 | PC/SP | S-15の直後 | サンクスページを見て「ホームに戻る」を押す | 背景画像の上に「ご予約ありがとうございます。お届けまで、どうぞ楽しみにお待ちください。」「商品は9月より、準備が整い次第順次発送いたします。」が表示され、トップページへ移動する |
| S-22 | AC-15 | PC/SP | S-2の状態 | 予約ページ・住所追加モーダル・振込先のご案内・サンクスページを開く | 横スクロールが出ず、文字・ボタンが画面に収まっている |

## 実施結果

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | OK | -（対象外） | 未ログインで /reservation/11 を開くとログインページへ移動し、ログイン後に同じ予約ページへ戻った | screenshots/reservation/S-1-pc.png |
| S-2 | OK | OK | 商品詳細の「予約する」（リンク先 /reservation/{id}）から予約ページへ。北の恵みセット10kg×1、小計1,450円・送料1,530円・合計2,980円。氏名「UAT購入太郎」・電話09011112222・住所「北海道札幌市UAT1-1」、確認用お届け先住所も同じ。支払方法はクレジットカード・銀行振込の2つのみ。数量の入力上限は10。SPも同じ内容で、横スクロールなし | screenshots/reservation/S-2-pc.png、screenshots/reservation/S-2-sp.png |
| S-3 | OK | -（対象外） | 人参2kg（id=7）・存在しないID（9999）ともに404 | screenshots/reservation/S-3-pc.png |
| S-4 | OK | -（対象外） | 数量を2にすると、再読み込みなし（ページ上の目印が残ることで確認）で小計2,900円・送料2,190円・合計5,090円に更新 | screenshots/reservation/S-4-pc.png |
| S-5 | OK | -（対象外） | 東京都UAT区登録町9-9-9に切り替えると送料2,050円・合計3,500円、確認用お届け先住所も東京都に更新（再読み込みなし）。※数量欄を入力した直後（Tabなどで確定する前）に住所を切り替えると、数量の入力が反映されないまま金額が計算された。数量欄を離れてから操作した通常の手順では正しく更新される | screenshots/reservation/S-5-pc.png |
| S-6 | OK | -（対象外） | 数量4で小計5,800円・送料4,590円・合計10,390円 | screenshots/reservation/S-6-pc.png |
| S-7 | OK | -（対象外） | 北の恵みセット5kg（id=10）で小計825円・送料1,230円・合計2,055円 | screenshots/reservation/S-7-pc.png |
| S-8 | OK | OK | モーダルで東京都・「UAT区予約町1-2-3」を追加すると、モーダルが閉じ、住所の選択肢に「東京都UAT区予約町1-2-3」が加わって選択され、確認用お届け先住所も更新、送料2,050円・合計3,500円。SPのモーダルも画面に収まり、ボタンの高さは44px | screenshots/reservation/S-8-modal-pc.png、screenshots/reservation/S-8-pc.png、screenshots/reservation/S-8-sp.png、screenshots/reservation/S-22-modal-sp.png |
| S-9 | OK | OK | 氏名を空で送信すると「お届け先氏名を入力してください」、電話番号「090-1234-5678」「123456789」で「お届け先電話番号はハイフンなしの数字のみで10〜11桁で入力してください」。Stripeへは移動せず、入力値は残り、予約データも増えない | screenshots/reservation/S-9-pc.png、screenshots/reservation/S-9-tel-pc.png、screenshots/reservation/S-9-sp.png、screenshots/reservation/S-9-tel-sp.png |
| S-10 | OK | -（対象外） | 数量0・11とも、数量欄を離れた時点で「商品の数は1個以上にしてください／10個以下にしてください（数量は1個のままです）」、「予約を確定する」後も「商品の数は1個以上にしてください／10個以下にしてください」が表示され予約されない。※数量に0を入力した直後に、欄を離れずに直接「予約を確定する」を押すと、確認エラーが表示されず予約ページが再読み込みされて入力が初期値（氏名・数量）に戻った（予約データは作られない）。数量は入力→Tab→ボタンの順で確認した | screenshots/reservation/S-10-0-pc.png、screenshots/reservation/S-10-11-pc.png |
| S-11 | OK | -（対象外） | payment_method=cod にして送信すると「お支払い方法を選択してください」が表示され、予約データは作られない | screenshots/reservation/S-11-pc.png |
| S-12 | OK | -（対象外） | UAT予約商品（id=12。別会員の予約1件あり）の数量の入力上限は2。3で送ると「予約可能数を超えています（残り2個）」が数量欄に表示され、「予約を確定する」後も同じエラーで予約されない | screenshots/reservation/S-12-pc.png |
| S-13 | OK | -（対象外） | 数量2・銀行振込で予約を完了（reservations id=2、合計数量3）。商品詳細は「予約受付終了」の無効ボタン、/reservation/12 を直接開くと /items/12 へ戻され「この商品の予約受付は終了しました」が表示 | screenshots/reservation/S-13-bank-pc.png、screenshots/reservation/S-13-thanks-pc.png、screenshots/reservation/S-13-detail-pc.png、screenshots/reservation/S-13-redirect-pc.png |
| S-14 | OK | OK | Stripe決済ページに「北の恵みセット 10kg ￥1,450」「送料 ￥1,530」、合計￥2,980。4242カードで支払い後 /reservation/success?session_id=… にサンクスページが表示。reservations に id=3（PC）・id=7（SP）が item=11、quantity=1、price=1450、subtotal=1450、shipping_fee=1530、total_price=2980、payment_method=card、お届け先は入力どおりで保存。カートは人参2kg×1のまま。SPのStripe画面は商品内訳が「詳細を表示」で折りたたまれる（Stripe側の表示） | screenshots/reservation/S-14-stripe-pc.png、screenshots/reservation/S-14-pc.png、screenshots/reservation/S-14-stripe-sp.png、screenshots/reservation/S-14-sp.png |
| S-15 | OK | OK | 振込先のご案内に いろは銀行／中央支店／普通／TEST48213081／ストライプジャパン（カ　シュウノウダイコウ／振込金額2,980円。「振込み」で /reservation/thanks。reservations id=4（PC）・id=8（SP）が payment_method=customer_balance、shipping_fee=1530、total_price=2980、reservation_bank_transfers（id=2 PC・id=5 SP）に表示と同じ振込先・amount=2980が保存 | screenshots/reservation/S-15-bank-pc.png、screenshots/reservation/S-15-pc.png、screenshots/reservation/S-15-bank-sp.png、screenshots/reservation/S-15-sp.png |
| S-16 | OK | OK | 氏名・電話・数量2・東京都住所・銀行振込にして確定→「戻る」で予約ページへ戻り、氏名・電話・住所・支払方法（銀行振込）・数量2がすべて残り、金額も5,610円のまま。予約データは増えない | screenshots/reservation/S-16-pc.png、screenshots/reservation/S-16-sp.png |
| S-17 | OK | -（対象外） | Stripeの「戻る」で予約ページへ戻り、氏名「UAT予約Stripe戻る」が残る。予約データは増えない | screenshots/reservation/S-17-pc.png |
| S-18 | OK | -（対象外） | 4000 0000 0000 0002 で「クレジットカードが拒否されました。デビットカードでの支払いをお試しください。」がStripe画面に表示され、サンクスページへは移動せず、予約データは増えない | screenshots/reservation/S-18-pc.png |
| S-19 | OK | -（対象外） | カード（success）：成功直後のあと、同じURL（session_id付き）・/reservation/success・/reservation/thanks を開くとすべてトップページへ。銀行振込：サンクスページを再読み込みするとトップへ、ブラウザの戻るで振込先のご案内を開き直して「振込み」を押してもトップへ。いずれも予約データは増えなかった（reservations 4件・reservation_bank_transfers 2件のまま） | -（遷移先がトップページのため撮影なし） |
| S-20 | OK | -（対象外） | 「予約を確定する」を連続3回クリック（20ms間隔）しても /reservation/payment へのPOSTは1回だけで、ボタンは無効になる。続けて「振込み」を連続3回クリック（20ms間隔）したところ、confirm へのPOSTが3回送られ、予約は1件のみ作られたがサンクスページではなくトップページに着地した。現実的な操作間隔（80ms）で2回押した場合は、押した直後にボタンが消え、POSTは1回で予約は1件・サンクスページ表示。予約データはどちらも1件だけ（id=5、id=6） | -（NGなし） |
| S-21 | OK | OK | 背景画像の上に「ご予約ありがとうございます。お届けまで、どうぞ楽しみにお待ちください。」「商品は9月より、準備が整い次第順次発送いたします。」「ホームに戻る」が表示され、押すとトップページへ。SPは見出しが「ご予約ありがとうござい／ます。…」のように途中で折り返す（文の途中での改行） | screenshots/reservation/S-21-pc.png、screenshots/reservation/S-21-sp.png |
| S-22 | OK | OK | 予約ページ・住所追加モーダル・振込先のご案内・サンクスページのPC・SPとも横スクロールなし（scrollWidth=clientWidth）、文字・ボタンが画面に収まる。SPのボタン・入力欄の高さは44px（数量欄80×44、住所追加・予約を確定する・戻る・振込み・ホームに戻るも44px）、本文の文字は16〜20px。SPのフルページ撮影ではヘッダー右端の「メニュー」が欠けて見える（検証用ブラウザのスクロールバー15pxぶんがレイアウトに含まれるため。PCもヘッダー右端「ログアウト」・フッター右端が同様に欠けて見える。全ページ共通で予約固有ではない） | screenshots/reservation/S-2-sp.png、screenshots/reservation/S-22-modal-sp.png、screenshots/reservation/S-15-bank-sp.png、screenshots/reservation/S-21-sp.png |

- コンソールエラー：予約の画面で想定外のエラーなし。出たのは、S-3で404ページを開いたときの「Failed to load resource（404）」2件と、S-18でカード拒否時にStripeのAPIが返した402の1件のみ（いずれも想定どおりの動作）
- メール（MailHog）：対象外（予約確認メールはこのブランチでは作らない）
- 追加したデータ：items に1件（id=12「UAT予約商品」。is_reserved_only=true、weight=5、price=1000、reservation_limit=3、img_url は北の恵みセットと同じ）。reservations に8件（id=1 別会員 uat-header@example.com（user_id=2）の「UAT既存予約」（item=12、数量1、card）、id=2 UAT予約商品×2（customer_balance、S-13）、id=3 北の恵みセット10kg×1（card、S-14 PC）、id=4 同（customer_balance、S-15 PC）、id=5・id=6 同（customer_balance、S-20）、id=7 同（card、S-14 SP）、id=8 同（customer_balance、S-15 SP）。id=2〜8 は user_id=3（uat-buy@example.com）、お届け先はいずれも北海道札幌市UAT1-1）。reservation_bank_transfers に5件（id=1〜5。reservation_id=2、4、5、6、8）。delivery_addresses に2件（user_id=3、東京都「UAT区予約町1-2-3」。S-8のPCとSP）。カートは人参2kg×1（購入のUATで作った分ではなく、S-14の確認用に追加）。Stripeのテスト環境にCheckout Session（支払い済み2件、「戻る」1件、拒否1件）と銀行振込のPaymentIntent（「戻る」の分を含む数件）ができている

## 再実施1（2026-10-22）

修正内容：(1) 購入・予約の確定フォームを共通コンポーネント（components/checkout-form.blade.php）にし、送信時に実行中のLivewire更新を待ってからPOSTする。(2) 振込先のご案内で「戻る」「振込み」のどちらかを押した直後に両方のボタンを無効化する。(3) 予約ルート（shop.reservation.*）のヘッダーナビを購入ページと同じものにする。前の結果は上の「実施結果」にそのまま残している。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| 予約 S-10 | OK | -（対象外） | 氏名を変えたうえで、数量に0・11を入力し、欄を離れずに「予約を確定する」を直接クリック。0は「商品の数は1個以上にしてください」、11は「商品の数は10個以下にしてください」が表示され、氏名（UAT再実施S10-0／UAT再実施S10-11）・数量（0／11）の入力が残った（前回は再読み込みされて消えていた）。通信は livewire/update のあとに reservation/payment のPOSTが1回。予約データは増えない（8件のまま） | screenshots/reservation/R1-S-10-0-pc.png、screenshots/reservation/R1-S-10-11-pc.png |
| 予約 S-9 | OK | -（対象外） | 回帰確認。氏名を空にすると「お届け先氏名を入力してください」（電話は残る）、電話「090-1234-5678」「123456789」で「お届け先電話番号はハイフンなしの数字のみで10〜11桁で入力してください」。いずれも入力値が残り、予約データは増えない | screenshots/reservation/R1-S-9-pc.png、screenshots/reservation/R1-S-9-tel-pc.png |
| 予約 S-20 | OK | -（対象外） | 銀行振込で「予約を確定する」を20ms間隔で3回クリック→POST /reservation/payment は1回。続けて振込先のご案内の「振込み」を20ms間隔で3回クリック→POST /reservation/bank-transfer/confirm は1回、サンクスページ（/reservation/thanks）に着地。予約は1件だけ（reservations id=9、reservation_bank_transfers id=6）。前回は「振込み」のPOSTが3回送られトップページに着地していたが、今回は解消。「戻る」を20ms間隔で3回クリック→POST /reservation/bank-transfer/cancel は1回で予約ページへ戻り、氏名・支払方法が残り、予約は増えない。「戻る」を押した直後に「振込み」を押しても、2回目はクリックできず（POSTは cancel の1回のみ）、予約は増えない | screenshots/reservation/R1-S-20-pc.png、screenshots/reservation/R1-S-20-back-pc.png |
| 予約 S-14 | OK | -（対象外） | カードで確定→Stripeの決済ページに「北の恵みセット 10kg ￥1,450」「送料 ￥1,530」、合計￥2,980。4242カードで支払い後 /reservation/success?session_id=… にサンクスページが表示。reservations id=10（delivery_name=UAT再実施S14カード、item=11、quantity=1、price=1450、subtotal=1450、shipping_fee=1530、total_price=2980、payment_method=card、お届け先は北海道札幌市UAT1-1）が保存 | screenshots/reservation/R1-S-14-stripe-pc.png、screenshots/reservation/R1-S-14-pc.png |
| 予約 S-15 | OK | -（対象外） | 銀行振込で確定→振込先のご案内（いろは銀行／中央支店／普通／TEST48213081／ストライプジャパン（カ　シュウノウダイコウ／振込金額2,980円、注文内容、お届け先）。「振込み」で /reservation/thanks。reservations id=11（customer_balance、shipping_fee=1530、total_price=2980）、reservation_bank_transfers id=7（reservation_id=11、amount=2980、account_number=TEST48213081） | screenshots/reservation/R1-S-15-bank-pc.png、screenshots/reservation/R1-S-15-pc.png |
| 予約 S-16 | OK | -（対象外） | 氏名「UAT予約戻る」・電話08012345678・数量2・東京都UAT区登録町9-9-9・銀行振込で確定→「戻る」で予約ページへ戻り、氏名・電話・数量・住所・支払方法（銀行振込）がすべて残り、金額も2,900円／2,710円／5,610円のまま。予約データは増えない（11件のまま） | screenshots/reservation/R1-S-16-pc.png |
| 予約 S-2（ヘッダー） | OK | OK | PC：予約ページ・振込先のご案内のヘッダーは「しおや農園」ロゴ、お知らせ、しおや農園について、マイページ、ログアウト（カートのリンクは無い）で、購入ページ（/purchase）のヘッダーと同じ内容。SP：予約ページ・振込先のご案内とも、ヘッダーはロゴ、マイページ、メニュー（開くと お知らせ・しおや農園について・ログアウト）で、購入ページのSPヘッダーと同じ。横スクロールなし（振込先のご案内SP：scrollWidth=clientWidth）。サンクスページ（/reservation/success、/reservation/thanks）にはヘッダー自体が無い（購入のサンクスと同様の全画面表示）。※依頼文では「マイページ・ログアウトのみ」とのことだったが、実際には購入ページと同じく「お知らせ」「しおや農園について」も並ぶ（購入ページと同一） | screenshots/reservation/R1-S-2-pc.png、screenshots/reservation/R1-S-2-sp.png、screenshots/reservation/R1-S-2-menu-sp.png、screenshots/reservation/R1-S-2-bank-sp.png、screenshots/reservation/R1-P-header-sp.png |
| 購入 S-14（回帰） | OK | -（対象外） | 氏名を変えたうえで、数量に0・11を入力し、欄を離れずに「注文を確定する」を直接クリック。0は「商品の数は1個以上にしてください」、11は「商品の数は10個以下にしてください」が表示され、氏名・数量の入力が残る（購入ページが再読み込みされない）。購入データは作られない。通信は livewire/update のあとに /payment のPOSTが1回（このとき購入ページのカートは人参2kg×1） | screenshots/reservation/R1-P-S-14-0-pc.png、screenshots/reservation/R1-P-S-14-11-pc.png |
| 購入 S-17（回帰） | OK | -（対象外） | カートはじゃがいも2kg×2、玉ねぎ5kg、人参2kg。代金引換（小計1,873円・送料1,850円・代引手数料330円・合計4,053円）で確定→Stripeを通らず /purchase/thanks。purchase_histories id=40（delivery_name=UAT再実施P-S17代引、cod、shipping_fee=1850、cod_fee=330、total_price=4053）、カートは空 | screenshots/reservation/R1-P-S-17-pc.png |
| 購入 S-28（回帰） | OK | -（対象外） | 代金引換で「注文を確定する」を dblclick（20ms間隔）。POST /payment は1回、/purchase/thanks に着地。購入データは1件だけ（id=41、delivery_name=UAT再実施P-S28連打、cod、total_price=4053） | -（遷移先がサンクスのため撮影なし） |
| 購入 S-16（回帰） | OK | -（対象外） | 銀行振込で確定→振込先のご案内（いろは銀行／中央支店／普通／TEST48213081／ストライプジャパン（カ　シュウノウダイコウ／振込金額3,723円、注文内容3商品、小計1,873円・送料1,850円・合計3,723円）。「振込み」を20ms間隔で3回クリック→POST /purchase/bank-transfer/confirm は1回、/purchase/thanks に着地。purchase_histories id=42（customer_balance、shipping_fee=1850、cod_fee=0、total_price=3723、delivery_name=UAT再実施P-S16振込）、purchase_bank_transfers は1件増加（合計10件）。カートは空 | screenshots/reservation/R1-P-S-16-bank-pc.png、screenshots/reservation/R1-P-S-16-pc.png |

- コンソールエラー：再実施中は、予約・購入の画面でエラーなし（browser_console_messages で error 0件）
- 追加したデータ（再実施1）：reservations に4件（id=9 UAT再実施S20（customer_balance）、id=10 UAT再実施S14カード（card）、id=11 UAT再実施S15（customer_balance）、いずれも item=11・user_id=3。ほか id=8 までは前回のまま）、reservation_bank_transfers に2件（id=6、7。reservation_id=9、11）。これで reservations は11件、reservation_bank_transfers は7件。purchase_histories に3件（id=40 UAT再実施P-S17代引（cod）、id=41 UAT再実施P-S28連打（cod）、id=42 UAT再実施P-S16振込（customer_balance））、purchase_history_items に9件（各3件）、purchase_bank_transfers に1件。Stripeのテスト環境にCheckout Session（支払い済み1件）と銀行振込のPaymentIntent（「戻る」の分を含む数件）ができている。uat-buy@example.com のカートは空（再実施中に、確認のため追加して購入で消化した）

## 再実施2（2026-10-22）

修正内容：コードレビューの指摘（商品詳細の受付終了判定の注入先の変更、予約ページのLivewireで商品取得と予約数集計をリクエスト内で1回にした、確定フォームのLivewireフックの解除処理の追加、予約サービスの重複した支払方法チェックの削除）。前の結果はそのまま残している。

S-12 は、UAT予約商品（id=12）が前回までのS-13で予約可能数に達して締め切られているため、新しく「UAT予約商品R2」（id=13。予約限定、重さ5kg、価格1,000円、予約可能数3）を追加し、別の会員（uat-header@example.com）の予約を数量1で1件追加して実施した。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| 予約 S-2 | OK | -（対象外） | 商品詳細（北の恵みセット10kg）の「予約する」から予約ページへ。北の恵みセット 10kg×1、小計1,450円・送料1,530円・合計2,980円。氏名「UAT購入太郎」・電話09011112222・住所「北海道札幌市UAT1-1」（確認用お届け先住所も同じ）。支払方法は2つ。数量の入力上限は10 | screenshots/reservation/R2-S-2-pc.png |
| 予約 S-4 | OK | -（対象外） | 数量2で小計2,900円・送料2,190円・合計5,090円。再読み込みなし（ページ上の目印が残る） | screenshots/reservation/R2-S-4-pc.png |
| 予約 S-6 | OK | -（対象外） | 数量4で小計5,800円・送料4,590円・合計10,390円。再読み込みなし | screenshots/reservation/R2-S-6-pc.png |
| 予約 S-12 | OK | -（対象外） | UAT予約商品R2（id=13、別会員の予約1件あり）の数量の入力上限は2（金額は1,000円・送料1,230円・合計2,230円）。数量3にして欄を離れると「予約可能数を超えています（残り2個）（数量は1個のままです）」、「予約を確定する」後は「予約可能数を超えています（残り2個）」が数量欄に表示され、予約されない | screenshots/reservation/R2-S-12-pc.png |
| 予約 S-13（商品詳細部分） | OK | -（対象外） | UAT予約商品（id=12）の商品詳細は「予約受付終了」の無効ボタンのみで、/reservation/12 を直接開くと /items/12 へ戻り「この商品の予約受付は終了しました」が表示。北の恵みセット10kg（id=11）の商品詳細は「予約する」がリンク（http://localhost/reservation/11）で、押すと予約ページが開く | screenshots/reservation/R2-S-13-detail-12-pc.png、screenshots/reservation/R2-S-13-detail-11-pc.png |
| 予約 S-10 | OK | -（対象外） | 氏名を変え、数量に0・11を入力して欄を離れずに「予約を確定する」を直接クリック。0は「商品の数は1個以上にしてください」、11は「商品の数は10個以下にしてください」が表示され、氏名（UAT再実施2-S10-0／UAT再実施2-S10-11）・数量（0／11）の入力が残る。POSTは livewire/update のあと reservation/payment が1回。予約データは作られない | screenshots/reservation/R2-S-10-0-pc.png、screenshots/reservation/R2-S-10-11-pc.png |
| 予約 S-11 | OK | -（対象外） | payment_method=cod にして送信すると「お支払い方法を選択してください」が表示され、予約データは作られない | screenshots/reservation/R2-S-11-pc.png |
| 予約 S-15 | OK | -（対象外） | 銀行振込で確定→振込先のご案内（いろは銀行／中央支店／普通／TEST48213081／ストライプジャパン（カ　シュウノウダイコウ／振込金額2,980円）。「振込み」で /reservation/thanks。reservations id=13（delivery_name=UAT再実施2-S15、item=11、quantity=1、customer_balance、shipping_fee=1530、total_price=2980）、reservation_bank_transfers id=8（reservation_id=13、amount=2980） | screenshots/reservation/R2-S-15-bank-pc.png、screenshots/reservation/R2-S-15-pc.png |
| 購入 S-2 | OK | -（対象外） | カート（じゃがいも2kg×2、玉ねぎ5kg、人参2kg）のカートページの「購入する」で購入ページへ。3商品、小計1,873円・送料1,850円・合計3,723円。氏名「UAT購入太郎」、住所「北海道札幌市UAT1-1」、確認用お届け先住所も北海道から始まる | screenshots/reservation/R2-P-S-2-pc.png |
| 購入 S-14 | OK | -（対象外） | 氏名を変え、数量に0・11を入力して欄を離れずに「注文を確定する」を直接クリック。0は「商品の数は1個以上にしてください」、11は「商品の数は10個以下にしてください」が表示され、氏名・数量の入力が残る。購入データは作られない | screenshots/reservation/R2-P-S-14-0-pc.png、screenshots/reservation/R2-P-S-14-11-pc.png |
| 購入 S-17 | OK | -（対象外） | 代金引換（小計1,873円・送料1,850円・代引手数料330円・合計4,053円）で確定→Stripeを通らず /purchase/thanks。purchase_histories id=43（delivery_name=UAT再実施2-P-S17代引、cod、shipping_fee=1850、cod_fee=330、total_price=4053）、カートは空 | screenshots/reservation/R2-P-S-17-pc.png |

- コンソールエラー：なし（browser_console_messages で error 0件）
- 追加したデータ（再実施2）：items に1件（id=13「UAT予約商品R2」。is_reserved_only=true、weight=5、price=1000、reservation_limit=3、img_url は北の恵みセットと同じ）。reservations に2件（id=12 別会員 uat-header@example.com（user_id=2）の「UAT既存予約R2」（item=13、数量1、card）、id=13 UAT再実施2-S15（user_id=3、customer_balance））。これで reservations は13件。reservation_bank_transfers に1件（id=8。reservation_id=13）。purchase_histories に1件（id=43 UAT再実施2-P-S17代引（cod））、purchase_history_items に3件。Stripeのテスト環境に銀行振込のPaymentIntentが1件できている。uat-buy@example.com のカートは空（確認のため、じゃがいも2kg×2・玉ねぎ5kg・人参2kgを追加して購入で消化した）

## ユーザー確認チェックリスト

- [ ] 商品詳細の「予約する」から予約ページ、サンクスページまでの流れが自然
- [ ] 数量・住所を変えたときに、送料・合計がすぐ変わるのがわかりやすい
- [ ] 支払方法がクレジットカードと銀行振込の2つで、選び方がわかりやすい
- [ ] 予約可能数を超えたときのエラーと、「予約受付終了」の表示がわかりやすい
- [ ] Stripeの決済ページ・振込先のご案内に表示される商品名・送料・金額に違和感がない
- [ ] サンクスページの文言と背景画像の見え方に違和感がない
- [ ] SPで、数量入力・住所の選択・支払方法・「予約を確定する」が押しやすい（見た目の細部は11月1日以降にFigmaで仕上げる前提）

## 判定

- 受け入れ：未 / 済（ユーザーが記入する）
- コメント：
