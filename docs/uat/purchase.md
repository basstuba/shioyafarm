# UAT：購入

| 項目 | 内容 |
|---|---|
| 対象ルート | GET /purchase/{item?}（shop.checkout.index）、POST /payment（shop.checkout.payment）、GET /purchase/success（shop.checkout.success）、POST /add-address（shop.checkout.add-address） |
| ブランチ | feat/purchase |
| 根拠 | docs/03-page-list.md「購入ページ」「決済ページ」「購入サンクスページ」、docs/04-feature-list.md「購入ページ表示機能」「購入サンクスページ表示機能」「決済機能」「配送先住所追加機能」、docs/07-basic-design.md（PurchaseRequest、DeliveryAddressRequest）、docs/02-initial-settings.md「支払方法」「発送に関する注意事項」、.claude/rules/shipping.md |
| デザイン | Figma MCPの上限リセット（2026-11-01）まではフロントエンドを保留。ユーザーが渡した画面画像（購入ページPC、住所追加モーダル、サンクスページ）を参考にした仮のBladeで実装する。見た目は11月1日以降にFigmaの値で仕上げる |
| 実施日 | 2026-10-22 |

ユーザーと決めた事項：

- 購入ページの入り口は2つ。カートの「購入する」→ `/purchase`（カートの中身を購入）、商品詳細の「購入する」→ `/purchase/{item}`（その商品を1個から購入）。
- カートから来た場合、購入ページで数量を変えるとカートにも反映する。購入が完了したらカートを空にする。商品詳細から来た場合はカートを変えない。
- クレジットカードは Stripe Checkout（Stripeのページ）で支払う。Webhookは使わず、サンクスページに戻ってきたときにStripeへ決済状態を問い合わせてから購入データを保存する。Checkoutの通貨は常に円で表示する（Adaptive Pricing を無効にする）。
- 銀行振込は Stripe Checkout を使わない（Checkoutでは振込先の案内ページで流れが終わり、サイトへ戻れないため。2026-10-22 のUATで判明）。「注文を確定する」でサーバーからStripeに銀行振込の支払いを作り、返ってきた振込先（銀行名・支店名・口座種別・口座番号・口座名義・振込金額）を表示する「振込先のご案内」ページへ遷移する。このページには「戻る」と「振込み」のボタンがあり、「戻る」で購入ページへ（入力内容は残り、Stripeの支払いは取り消す）、「振込み」で購入データを保存してサンクスページへ遷移する。
- 銀行振込の振込先（Stripeの PaymentIntent ID、銀行名・支店名・口座種別・口座番号・口座名義・振込金額）は、「振込み」で購入データを保存するときに purchase_bank_transfers テーブルに保存する（マイページの購入履歴と注文確認メールで使うため）。注文確認メールの送信とマイページでの表示は、このブランチでは作らない。
- Stripeのテスト用キーはユーザーが `.env`（`STRIPE_KEY` / `STRIPE_SECRET`）に設定し、Stripeダッシュボードで銀行振込を有効にする。
- 代金引換は Stripe を通さず、「注文を確定する」で購入データを保存してサンクスページへ遷移する。

Claudeが決めた事項（OKをもらう）：

- 購入ページの画面操作（数量変更、お届け先住所の切り替え、支払方法の選択、住所追加モーダル）は Livewire で行い、ページを再読み込みせずに金額を更新する。
- お届け先住所の選択肢は、会員登録の住所と、追加した配送先住所（delivery_addresses）。初期値は会員登録の住所。お届け先氏名・電話番号の初期値も会員登録の氏名（姓＋名）・電話番号。
- 「確認用お届け先住所」には、選んだ住所を都道府県から省略せずに表示する。
- 代金引換を選ぶと、金額欄に「代引手数料 330円」の行が加わり、合計に加算される（クレジットカード・銀行振込のときは行を出さない）。
- 住所追加モーダルで追加した住所は、モーダルが閉じたあと、お届け先住所で選択された状態になる。
- 購入データには、お届け先・支払方法・商品ごとの単価・数量・小計に加えて、送料・代引手数料・合計を保存する。purchase_histories に `shipping_fee`・`cod_fee`・`total_price`（integer）を追加するマイグレーションを新しく作る（既存のマイグレーションは書き換えない）。代引以外の `cod_fee` は0。
- サンクスページの背景は `storage/app/public/image/tool/thanks-page-photo.webp`。
- 保存待ちの注文がない状態で /purchase/success を開いた場合（再読み込み・直接アクセス）は、購入データを作らずトップページへリダイレクトする。
- Stripeのページで「戻る」を押した場合は、購入ページへ戻る（入力していたお届け先氏名・電話番号・住所・支払方法・数量は残る）。

## 受け入れ条件

| ID | 条件 |
|---|---|
| AC-1 | 未ログインで購入ページを開くとログインページへ移動し、ログイン後に購入ページへ戻る |
| AC-2 | カートの「購入する」で購入ページが開き、カートの商品（画像・商品名・単価・数量・小計）が表示される。お届け先氏名・電話番号・住所には会員登録の内容が入っている。カートが空のときはカートページへ戻される |
| AC-3 | 商品詳細の「購入する」で、その商品1個だけの購入ページが開く。予約限定の商品・存在しない商品では購入ページが開かない（404） |
| AC-4 | 送料は、選んだお届け先住所の都道府県と、商品の合計重量から計算される（送料案内ページの料金表と同じ金額。30kgを超えると2個口）。数量や住所を変えると、ページを再読み込みせずに小計・送料・合計が更新される |
| AC-5 | 代金引換を選ぶと代引手数料330円が合計に加わる。クレジットカード・銀行振込では加わらない |
| AC-6 | 「＋新しい住所を追加」でモーダルが開き、都道府県と住所を入力して「住所を追加」を押すと、住所が登録され、お届け先住所の選択肢に加わって選択される。未入力ではエラーが表示される |
| AC-7 | 入力に誤りがある（氏名が空、電話番号が10〜11桁の数字でない、数量が1〜10の範囲外、支払方法未選択）と「注文を確定する」でエラーが表示され、注文されない |
| AC-8 | クレジットカードを選んで「注文を確定する」を押すとStripeの決済ページへ移動し、支払いを済ませるとサンクスページが表示され、購入データ（お届け先・支払方法・送料・代引手数料・合計・商品ごとの単価・数量・小計）が保存される |
| AC-9 | 銀行振込を選んで「注文を確定する」を押すと「振込先のご案内」ページが表示され、Stripeから発行された振込先（銀行名・支店名・口座種別・口座番号・口座名義）と振込金額が表示される。「振込み」を押すとサンクスページが表示され、購入データが保存される。「戻る」を押すと購入ページへ戻り、入力内容が残り、購入データは作られない |
| AC-10 | 代金引換を選んで「注文を確定する」を押すと、Stripeを通らずにサンクスページが表示され、購入データが保存される |
| AC-11 | カートから購入した場合、購入完了後にカートが空になる。商品詳細から購入した場合、カートは変わらない |
| AC-12 | Stripeの決済ページから戻った場合や、サンクスページを再読み込み・直接開いた場合に、購入データが作られない・二重に作られない |
| AC-13 | サンクスページに背景画像と「ご注文ありがとうございました。心を込めてお届けいたします。」、発送の案内、「ホームに戻る」（トップページへのリンク）が表示される |
| AC-14 | PC・SPとも横スクロールが出ず、各項目が操作できる |
| AC-15 | Stripeの決済ページの金額は常に円で表示される |

## シナリオ

金額の前提：会員登録の住所は北海道。商品価格はシーダーのとおり（じゃがいも2kg 330円、じゃがいも10kg 1,320円、玉ねぎ5kg 795円、人参2kg 418円）。

| ID | 対応AC | 画面幅 | 前提 | 手順 | 期待結果 |
|---|---|---|---|---|---|
| S-1 | AC-1 | PC | 未ログイン | /purchase を開き、ログインする | ログインページへ移動し、ログイン後に /purchase（カートが空ならカートページ）へ戻る |
| S-2 | AC-2, AC-4 | PC/SP | ログイン済み（住所：北海道）。カートにじゃがいも2kg×2、玉ねぎ5kg×1、人参2kg×1 | カートページの「購入する」を押す | 購入ページに3商品が表示され、小計1,873円、送料1,850円（11kg・120サイズ）、合計3,723円。氏名・電話番号・住所に会員登録の内容が入り、確認用お届け先住所に北海道から始まる住所が表示される |
| S-3 | AC-2 | PC | ログイン済み。カートが空 | /purchase を開く | カートページへ戻される |
| S-4 | AC-3 | PC/SP | ログイン済み | 商品詳細（人参2kg）の「購入する」を押す | 人参2kg×1だけの購入ページが表示される。小計418円、送料940円（2kg・60サイズ・北海道）、合計1,358円 |
| S-5 | AC-3 | PC | ログイン済み | 予約限定の商品（北の恵みセット）のIDで /purchase/{id} を開く。存在しないIDでも開く | どちらも404 |
| S-6 | AC-4, AC-11 | PC | S-2の状態 | じゃがいも2kgの数量を2→3に変え、カートページを開く | ページを再読み込みせずに小計2,203円、送料1,850円（13kg）、合計4,053円に更新される。カートページでもじゃがいも2kgが3個になっている |
| S-7 | AC-4 | PC | ログイン済み。東京都の配送先住所が登録済み。S-2と同じカート | お届け先住所を東京都の住所に切り替える | 送料2,370円、合計4,243円に更新され、確認用お届け先住所が東京都の住所になる |
| S-8 | AC-4 | PC | ログイン済み（住所：北海道）。カートにじゃがいも10kg×3、玉ねぎ5kg×1（35kg） | /purchase を開く | 小計4,755円、送料4,290円（180サイズ3,060円＋80サイズ1,230円）、合計9,045円 |
| S-9 | AC-4 | PC | ログイン済み。沖縄県の配送先住所が登録済み | 商品詳細（じゃがいも2kg）から購入ページを開き、住所を沖縄県に切り替える | 送料2,340円、合計2,670円 |
| S-10 | AC-5 | PC/SP | S-2の状態 | 支払方法を代金引換→クレジットカードの順に選ぶ | 代金引換で「代引手数料 330円」の行が出て合計4,053円。クレジットカードに戻すと行が消え合計3,723円。選んだ支払方法のカードが選択中の見た目になる |
| S-11 | AC-6 | PC/SP | ログイン済み | 「＋新しい住所を追加」を押し、都道府県「東京都」、住所「UAT区テスト町1-2-3」を入力して「住所を追加」を押す | モーダルが閉じ、お届け先住所に「東京都UAT区テスト町1-2-3」が選択され、送料が関東の金額に変わる。再読み込みしても選択肢に残っている |
| S-12 | AC-6 | PC | ログイン済み | モーダルで何も入力せずに「住所を追加」を押す。住所に256文字入力して押す | 都道府県・住所のエラーが表示され、住所は登録されない |
| S-13 | AC-7 | PC/SP | S-2の状態 | 氏名を空にして「注文を確定する」。電話番号を「090-1234-5678」「123456789」にして押す | それぞれエラーが表示され、Stripeへ移動せず、購入データも作られない。入力していた値は残る |
| S-14 | AC-7 | PC | S-2の状態 | 数量に0、11を入力して「注文を確定する」を押す | 数量のエラーが表示され、注文されない |
| S-15 | AC-8, AC-11, AC-13 | PC/SP | S-2の状態。Stripeのテストキーが設定済み | クレジットカードを選び「注文を確定する」→ Stripeでテストカード 4242 4242 4242 4242 で支払う | Stripeの決済ページに3商品と送料1,850円、合計3,723円が表示される。支払い後サンクスページが表示される。purchase_histories に1件（payment_method=card、お届け先が入力どおり、shipping_fee=1850、cod_fee=0、total_price=3723）、purchase_history_items に3件（単価・数量・小計が購入ページと同じ）が保存され、カートが空になる |
| S-16 | AC-9, AC-11 | PC/SP | S-2の状態。Stripeで銀行振込が有効 | 銀行振込を選び「注文を確定する」→「振込先のご案内」で「振込み」を押す | 振込先のご案内に銀行名・支店名・口座種別・口座番号・口座名義と振込金額3,723円が表示される。「振込み」でサンクスページが表示され、payment_method=customer_balance、shipping_fee=1850、cod_fee=0、total_price=3723 の購入データ（商品3件）と、振込先のご案内に表示されたものと同じ振込先・振込金額3,723円の purchase_bank_transfers 1件が保存され、カートが空になる |
| S-17 | AC-10, AC-11 | PC/SP | S-2の状態 | 代金引換を選び「注文を確定する」 | Stripeを通らずにサンクスページが表示され、payment_method=cod、shipping_fee=1850、cod_fee=330、total_price=4053 の購入データ（商品3件）が保存され、カートが空になる |
| S-18 | AC-11 | PC | ログイン済み。カートに玉ねぎ5kg×1 | 商品詳細（人参2kg）から代金引換で購入する | 購入データは人参2kg×1だけ。カートには玉ねぎ5kg×1が残っている |
| S-19 | AC-12 | PC | S-2の状態 | クレジットカードで「注文を確定する」→ Stripeの決済ページで「戻る」を押す | 購入ページへ戻り、入力していた内容が残っている。購入データは作られない |
| S-20 | AC-12 | PC | S-17の直後 | サンクスページを再読み込みする。別の機会に /purchase/success を直接開く | トップページへリダイレクトされ、購入データは増えない |
| S-21 | AC-13 | PC/SP | S-17の直後 | サンクスページを見て「ホームに戻る」を押す | 背景画像の上に「ご注文ありがとうございました。心を込めてお届けいたします。」「商品はお支払い確定後3~5営業日以内、代金引換の場合はご注文確定後3~5営業日以内に発送いたします。」が表示され、トップページへ移動する |
| S-22 | AC-14 | PC/SP | S-2の状態 | 購入ページ・住所追加モーダル・サンクスページを開く | 横スクロールが出ず、文字・ボタンが画面に収まっている |
| S-23 | AC-12 | PC | S-2の状態。Stripeのテストキーが設定済み | クレジットカードを選び「注文を確定する」→ Stripeでテストカード 4000 0000 0000 0002（有効期限は未来の日付、CVCは任意の3桁）で支払う | Stripeの画面にカードが拒否された旨が表示され、サンクスページへは移動しない。購入データは作られず、カートもそのまま |
| S-24 | AC-9, AC-12 | PC/SP | S-2の状態 | 銀行振込を選び「注文を確定する」→「振込先のご案内」で「戻る」を押す | 購入ページへ戻り、入力していた内容（氏名・電話番号・住所・支払方法・数量）が残っている。購入データは作られず、カートもそのまま |
| S-25 | AC-12 | PC | S-16の直後 | サンクスページを再読み込みする。ブラウザの戻るで「振込先のご案内」を開き直し、「振込み」を押す | トップページ（または購入ページ）へ移動し、購入データは増えない |
| S-26 | AC-15 | PC/SP | S-2の状態 | クレジットカードを選び「注文を確定する」 | Stripeの決済ページの金額が円（￥3,723）で表示される（他の通貨にならない） |

## 実施結果

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | OK | 対象外 | /purchase を未ログインで開くとログインページへ移動。ログイン後は /purchase に戻り、カートが空だったためカートページ（/cart）へ移動した | screenshots/purchase/S-1-pc.png |
| S-2 | OK | OK | 3商品が表示され、小計1,873円・送料1,850円・合計3,723円。氏名「UAT購入太郎」・電話番号・住所（北海道札幌市UAT1-1）が入り、確認用住所も北海道から表示。SPは横スクロールなし。SPでお届け先住所のselectの高さが約21px（他の入力欄は44px）。SPヘッダー右端の「メニュー」の文字が全ページ撮影で右に少し欠ける（共通ヘッダー） | screenshots/purchase/S-2-pc.png, screenshots/purchase/S-2-sp.png |
| S-3 | OK | 対象外 | ログイン済み・カートが空で /purchase を開くとカートページへ戻された | screenshots/purchase/S-3-pc.png |
| S-4 | OK | OK | 人参2kg×1のみ。小計418円・送料940円・合計1,358円。SPは横スクロールなし。詳細ページの「購入する」の高さ44px | screenshots/purchase/S-4-pc.png, screenshots/purchase/S-4-sp.png, screenshots/purchase/S-4-detail-sp.png |
| S-5 | OK | 対象外 | /purchase/10（予約限定・北の恵みセット）と /purchase/9999 のどちらも404（HTTPステータス404） | screenshots/purchase/S-5-pc.png |
| S-6 | OK | 対象外 | じゃがいも2kgを2→3に変更。再読み込みなし（window変数が残る）で小計2,203円・送料1,850円・合計4,053円に更新。カートページでもじゃがいも2kgが3個 | screenshots/purchase/S-6-pc.png, screenshots/purchase/S-6-cart-pc.png |
| S-7 | OK | 対象外 | 東京都（UAT区登録町9-9-9）へ切り替え、再読み込みなしで送料2,370円・合計4,243円。確認用住所も東京都の住所 | screenshots/purchase/S-7-pc.png |
| S-8 | OK | 対象外 | じゃがいも10kg×3＋玉ねぎ5kg×1（35kg）で小計4,755円・送料4,290円・合計9,045円 | screenshots/purchase/S-8-pc.png |
| S-9 | OK | 対象外 | 商品詳細（じゃがいも2kg）から開き、北海道は送料940円・合計1,270円、沖縄県へ切り替えると送料2,340円・合計2,670円 | screenshots/purchase/S-9-pc.png |
| S-10 | OK | OK | 北海道で代金引換を選ぶと「代引手数料 330円」の行が出て合計4,053円、クレジットカードに戻すと行が消えて3,723円。選んだカードが選択中の見た目になる。東京都のときは4,243円→4,573円（代引）。銀行振込では手数料の行は出ない | screenshots/purchase/S-10-pc.png, screenshots/purchase/S-10-sp.png |
| S-11 | OK | OK | 東京都＋「UAT区テスト町1-2-3」を追加するとモーダルが閉じ、その住所が選択され、送料2,370円・合計4,243円。再読み込みしても選択肢に残る（初期選択は会員登録の住所に戻る）。PC・SPで1件ずつ追加したため同じ住所が2件ある（同じ住所の重複は登録できてしまう） | screenshots/purchase/S-11-pc.png, screenshots/purchase/S-11-sp.png |
| S-12 | OK | 対象外 | 未入力で「住所を追加」→「都道府県を選択してください」「住所を入力してください」。256文字→「住所は255文字以内で入力してください」。いずれも住所は増えない | screenshots/purchase/S-12-empty-pc.png, screenshots/purchase/S-12-256-pc.png |
| S-13 | 判定保留 | 判定保留 | 電話番号「090-1234-5678」「123456789」はどちらも「お届け先電話番号はハイフンなしの数字のみで10〜11桁で入力してください」が出て、Stripeへ移動せず購入データも0件のまま、入力値も残る。氏名を空にした場合は「お届け先氏名を入力してください」が出て注文されないが、氏名の欄に空ではなく初期値（UAT購入太郎）が入り直る（エラー表示と欄の中身が食い違う）。期待結果の「入力していた値は残る」を空欄にも当てはめるのか判断できないため判定保留。他の項目（住所・支払方法・数量・電話番号）は残る | screenshots/purchase/S-13-pc.png, screenshots/purchase/S-13-tell-pc.png, screenshots/purchase/S-13-sp.png, screenshots/purchase/S-13-tell-sp.png |
| S-14 | OK | 対象外 | 数量0→「商品の数は1個以上にしてください」、11→「商品の数は10個以下にしてください」。注文されない。変更直後はLivewireが数量をカートの値（3個）のまま保ち、確定ボタン後の再描画では0（金額は1個で計算）・11（金額は10個で計算）が欄に残る。再読み込みすると3個 | screenshots/purchase/S-14-0-pc.png, screenshots/purchase/S-14-0-submit-pc.png, screenshots/purchase/S-14-11-pc.png, screenshots/purchase/S-14-11-submit-pc.png |
| S-15 | NG | NG | クレジットカードで「注文を確定する」を押しても、Stripeの決済ページへ移動せず、購入ページに「決済ページを開けませんでした。しばらく時間をおいてから、もう一度お試しください」と出る（購入データは作られない）。原因：Stripe Checkout Session の作成が「The `payment_method_types` parameter is no longer supported when creating a Checkout Session. Payment methods are now managed from your Dashboard settings」で失敗する（StripePaymentGateway が payment_method_types を送っている）。Stripeのページ・サンクス・保存内容・カートが空になる点は確認できていない | screenshots/purchase/S-15-pc.png, screenshots/purchase/S-15-sp.png |
| S-16 | NG | 対象外 | 銀行振込で「注文を確定する」を押しても、S-15と同じエラーでStripeへ移動しない（SPでも同じ）。購入データは作られない | screenshots/purchase/S-16-pc.png, screenshots/purchase/S-16-sp.png |
| S-17 | OK | OK | Stripeを通らずサンクスページへ。PC：purchase_histories id=1（payment_method=cod、delivery_name=UAT購入PC代引、tell=09033334444、shipping_fee=1850、cod_fee=330、total_price=4053）、items 3件（1: 2個×330=660、5: 1個×795=795、7: 1個×418=418）、カートが空。SP：id=3（delivery_name=UAT購入SP代引、tell=08055556666、同じ金額・商品3件）、カートが空。SPは横スクロールなし | screenshots/purchase/S-17-pc.png, screenshots/purchase/S-17-sp.png |
| S-18 | OK | 対象外 | カートに玉ねぎ5kg×1がある状態で商品詳細（人参2kg）から代金引換。送料940円・代引330円・合計1,688円。purchase_histories id=2（total_price=1688）、itemsは人参2kg×1のみ。カートには玉ねぎ5kg×1が残った | screenshots/purchase/S-18-pc.png, screenshots/purchase/S-18-cart-pc.png |
| S-19 | 未実施 | 未実施 | S-15のとおりStripeの決済ページへ移動できないため、決済ページで「戻る」を押す操作ができない |  |
| S-20 | OK | OK | サンクスページの再読み込み（SP）・/purchase/success の直接アクセス（PC・SP）はどちらもトップページへリダイレクト。購入データは3件・商品7件のまま増えない | screenshots/purchase/S-17-pc.png |
| S-21 | OK | OK | 背景画像の上に「ご注文ありがとうございました。心を込めてお届けいたします。」と発送の案内（「商品はお支払い確定後3~5営業日以内、代金引換の場合はご注文確定後3~5営業日以内に発送いたします。」）。PCは「ホームに戻る」を押してトップページ（/）へ移動。SPはリンクのhref（トップページ）と高さ44pxを確認（クリックは再読み込みのテストを優先して未実施） | screenshots/purchase/S-21-pc.png, screenshots/purchase/S-21-sp.png |
| S-22 | OK | OK | 購入ページ・住所追加モーダル・サンクスページのいずれもPC・SPで横スクロールなし（scrollWidth＝clientWidth）、モーダルは画面内に収まる。参考：PCで全ページを撮影すると共通ヘッダーの「ログアウト」とフッターの最後のリンクが右端で欠けて見える（共通レイアウト） | screenshots/purchase/S-22-purchase-pc.png, screenshots/purchase/S-22-purchase-sp.png, screenshots/purchase/S-22-modal-pc.png, screenshots/purchase/S-22-modal-sp.png, screenshots/purchase/S-22-thanks-pc.png, screenshots/purchase/S-22-thanks-sp.png |
| S-23 | 未実施 | 未実施 | S-15のとおりStripeの決済ページへ移動できないため、テストカード（4000 0000 0000 0002）での支払いができない |  |

- コンソールエラー：なし（S-5の404ページを開いたときの「Failed to load resource: 404」2件のみで、想定どおり）
- メール（MailHog）：対象外
- 追加したデータ：users に1件（姓「UAT」名「購入太郎」、メール uat-buy@example.com、パスワード password、住所：北海道札幌市UAT1-1、電話 09011112222、id=3）、delivery_addresses に4件（東京都「UAT区登録町9-9-9」、沖縄県「UAT那覇市9-9」、東京都「UAT区テスト町1-2-3」×2）、purchase_histories に3件（id=1 UAT購入PC代引、id=2 UAT購入PC詳細代引、id=3 UAT購入SP代引）、purchase_history_items に7件。Stripeを使うシナリオはStripe側にもデータは作られていない

## 再実施（2026-10-22）

修正後（Stripe Checkout Session を `allowed_payment_method_types` で作成、エラーで戻ったときの空欄の扱い、SPの住所selectの高さ）に、S-13・S-15・S-16・S-19・S-23・S-2（SPの住所selectのみ）と、回帰確認のS-17を再実施した。前回の結果は上の表のまま残している。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-13 | OK | OK | 氏名を空にして確定すると「お届け先氏名を入力してください」が出て、氏名欄は空のまま（以前は初期値に戻っていた）。電話番号を空にした場合も「お届け先電話番号を入力してください」で欄は空のまま。「090-1234-5678」「123456789」は「お届け先電話番号はハイフンなしの数字のみで10〜11桁で入力してください」。いずれもStripeへ移動せず購入データは増えない。他の入力値も残る | screenshots/purchase/S-13-retest-pc.png, screenshots/purchase/S-13-retest-sp.png, screenshots/purchase/S-13-tell-retest-sp.png |
| S-15 | OK | 未実施 | PC：確定するとStripeの決済ページへ移動。表示は、既定でポーランドズロチ（PLN 95.10）で、「￥3,723」ボタンで円表示に切り替えられる（Stripeの多通貨表示）。円表示にするとじゃがいも2kg（数量2・単価￥330・￥660）、玉ねぎ5kg（￥795）、人参2kg（￥418）、送料（￥1,850）で合計￥3,723。事業者名の欄にはメールアドレス形式の文字列が出ている（Stripe側の設定）。4242 4242 4242 4242 で支払うとサンクスページ（/purchase/success?session_id=…）が表示され、purchase_histories id=4（payment_method=card、delivery_name=UAT購入PCカード、tell=09077778888、住所=北海道札幌市UAT1-1、shipping_fee=1850、cod_fee=0、total_price=3723）、items 3件（1: 2個×330=660、5: 1個×795=795、7: 1個×418=418）が保存され、カートが空になった。サンクスページを再読み込み・同じURLを開き直してもトップへ移動し、データは4件のまま増えない。SP：Stripeの決済ページ（人参2kg、￥1,358）が横スクロールなしで開くところまで確認し、支払いは行っていない | screenshots/purchase/S-15-stripe-pc.png, screenshots/purchase/S-15-stripe-jpy-pc.png, screenshots/purchase/S-15-stripe-filled-pc.png, screenshots/purchase/S-15-pc.png, screenshots/purchase/S-15-stripe-sp.png |
| S-16 | 判定保留 | 対象外 | Stripeの決済ページ（銀行振込）には3商品・送料￥1,850・合計￥3,723が円で表示され、「支払う」を押すとStripeの「Bank Transfer Instructions」ページ（英語表記。口座名義・口座番号・銀行コード等）が表示された。このページにはしおや農園へ戻るリンクがなく、10秒待ってもサンクスページへ自動では移動しなかったため、「振込先の案内を受けた時点でサンクスページへ戻る」流れを画面操作で確認できない。参考として、Checkout Sessionのsession_id付きの /purchase/success URLを直接開くと、サンクスページが表示され、purchase_histories id=5（payment_method=customer_balance、shipping_fee=1850、cod_fee=0、total_price=3723、delivery_name=UAT購入PC振込）、items 3件が保存され、カートが空になった。Stripe側の流れ（振込案内ページから戻る手段がない）をどう扱うかの判断が必要なため判定保留。【再々確認：新しいタブ（ポップアップ）で開く可能性を確認】「支払う」を押した後に browser_tabs と context.pages() で調べたところ、タブは最初から最後まで1つだけで、そのタブ自体が「Bank Transfer Instructions」（payments.stripe.com/bank_transfers/instructions）へ遷移した。新しいタブ・ポップアップは開かず、元のタブがサンクスページへ遷移することもなかった（約18秒待って確認）。購入データも増えていない（purchase_histories 6件のまま）。実ブラウザ固有の挙動（ポップアップ）はPlaywrightでは確認できないため判定保留のまま。この確認でStripe側に銀行振込のCheckout Sessionを1件追加（お届け先氏名「UAT購入PC振込2」、カートは3商品のまま残っている） | screenshots/purchase/S-16-stripe-pc.png, screenshots/purchase/S-16-stripe-after-pc.png, screenshots/purchase/S-16-pc.png, screenshots/purchase/S-16-recheck-pc.png |
| S-19 | OK | 対象外 | Stripeの決済ページの「戻る」を押すと /purchase に戻り、氏名（UAT購入PCカード）・電話番号（09077778888）・住所・支払方法（クレジットカード）・商品と金額（小計1,873円・送料1,850円・合計3,723円）が残っている。購入データは3件・商品7件のまま増えない | screenshots/purchase/S-19-pc.png |
| S-23 | OK | 対象外 | 4000 0000 0000 0002（有効期限12/30、CVC 123）で支払うと、Stripeの画面に「クレジットカードが拒否されました。デビットカードでの支払いをお試しください。」と表示され、サンクスページへは移動しない。購入データは増えず（3件のまま）、カートも3商品のまま。コンソールにはStripeのAPIが返す402（カード拒否）が1件出る（想定どおり） | screenshots/purchase/S-23-pc.png |
| S-2（SPの住所selectのみ） | 対象外 | OK | お届け先住所のselectの高さが44px（氏名・電話番号の入力欄も44px）。横スクロールなし | screenshots/purchase/S-2-retest-sp.png |
| S-17（回帰確認） | 対象外 | OK | SPで代金引換。合計4,053円（代引手数料330円）でサンクスページへ。purchase_histories id=6（payment_method=cod、shipping_fee=1850、cod_fee=330、total_price=4053、delivery_name=UAT再確認SP代引、tell=08066667777）、items 3件（1: 2個×330=660、5: 1個×795=795、7: 1個×418=418）、カートが空 | screenshots/purchase/S-17-retest-sp.png |

- コンソールエラー：なし（S-23でStripeのAPIが返す402が1件のみで、カード拒否の想定どおり）
- 追加したデータ（前回の分に加えて）：purchase_histories に3件（id=4 UAT購入PCカード（card）、id=5 UAT購入PC振込（customer_balance）、id=6 UAT再確認SP代引（cod））、purchase_history_items に9件。Stripeのテスト環境にCheckout Sessionが数件できている（支払い済み1件、拒否1件、銀行振込の案内1件、未払い・戻った分）

## 再実施2（2026-10-22）

銀行振込を Stripe Checkout から「振込先のご案内」ページ経由に変更し、Checkoutを常に円表示にした仕様変更後に、S-16・S-24・S-25・S-26（新規・書き換え）と、回帰確認のS-15・S-19・S-23・S-17を再実施した。前の結果はそのまま残している。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-16 | OK | OK | PC：銀行振込を選んで確定すると「振込先のご案内」（/purchase/bank-transfer）が同じタブで開き、銀行名「いろは銀行」・支店名「中央支店」・口座種別「普通」・口座番号 TEST58833077・口座名義「ストライプジャパン（カ　シュウノウダイコウ」・振込金額 3,723円、注文内容（3商品・小計1,873円・送料1,850円・合計3,723円）、お届け先、「戻る」「振込み」が表示される。「振込み」でサンクスページへ。purchase_histories id=7（customer_balance、shipping_fee=1850、cod_fee=0、total_price=3723、delivery_name=UAT振込PC）、items 3件（2個×330=660、1個×795=795、1個×418=418）、purchase_bank_transfers id=1（purchase_history_id=7、bank_name=いろは銀行、branch_name=中央支店、account_type=futsu、account_number=TEST58833077、account_holder_name=ストライプジャパン（カ　シュウノウダイコウ、amount=3723。案内ページの表示と一致、stripe_payment_intent_id あり）、カートが空。SP：案内ページ・サンクスとも横スクロールなし、ボタン「戻る」「振込み」は200×44px、文字16px。保存まで確認（id=11、purchase_bank_transfers id=3、TEST16793460、amount=4243、案内ページの表示と一致、明細3件、カートが空）。ただしSPではお届け先が東京都（UAT区登録町9-9-9）の状態になっており（S-24の「戻る」で復元された入力が残っていた。下の気づいた点を参照）、送料2,370円・合計4,243円で確認した（北海道のときの3,723円はPCで確認済み） | screenshots/purchase/S-16-guide-pc.png, screenshots/purchase/S-16-pc.png, screenshots/purchase/S-16-guide-sp.png, screenshots/purchase/S-16-sp.png |
| S-24 | OK | OK | 「振込先のご案内」で「戻る」を押すと /purchase に戻り、氏名・電話番号・住所・支払方法（銀行振込）・商品と金額が残っている。購入データは増えず、purchase_bank_transfers も増えず、カートもそのまま。PC：氏名「UAT振込PC戻る」・北海道、小計1,873円・送料1,850円・合計3,723円。SP：氏名「UAT振込SP戻る」・東京都UAT区登録町9-9-9、案内ページの振込金額4,243円、戻ったあとも小計1,873円・送料2,370円・合計4,243円 | screenshots/purchase/S-24-guide-pc.png, screenshots/purchase/S-24-pc.png, screenshots/purchase/S-24-guide-sp.png, screenshots/purchase/S-24-sp.png |
| S-25 | OK | 対象外 | S-16と同じ流れで保存後（purchase_histories id=8、purchase_bank_transfers id=2）、サンクスページを再読み込みするとトップページ（/）へ移動。ブラウザの戻るで「振込先のご案内」（保存前の表示のまま TEST17142575）が開き、「振込み」を押すとトップページ（/）へ移動した。購入データは増えない（保存後は purchase_histories 8件・items 22件・purchase_bank_transfers 2件のまま） | screenshots/purchase/S-25-thanks-pc.png, screenshots/purchase/S-25-back-pc.png, screenshots/purchase/S-25-guide-reopen-pc.png, screenshots/purchase/S-25-pc.png |
| S-26 | OK | OK | クレジットカードのStripeの決済ページは、PC・SPとも円表示（￥3,723、じゃがいも2kg ￥330×2=￥660、玉ねぎ5kg ￥795、人参2kg ￥418、送料 ￥1,850）。PLNなど他の通貨の表示や通貨切り替えボタンは出ない。SPは「詳細を表示」で商品の内訳が出る。横スクロールなし | screenshots/purchase/S-26-pc.png, screenshots/purchase/S-26-sp.png |
| S-15（回帰） | OK | 対象外 | 4242 4242 4242 4242 で支払うとサンクスページ（/purchase/success?session_id=…）が表示され、purchase_histories id=9（card、shipping_fee=1850、cod_fee=0、total_price=3723、delivery_name=UAT購入PCカード3）、items 3件、カートが空。purchase_bank_transfers は増えない（2件のまま） | screenshots/purchase/S-15-retest-pc.png |
| S-19（回帰） | OK | 対象外 | Stripeの決済ページ（円表示）の「戻る」で /purchase に戻り、氏名・電話番号・支払方法・金額が残る。購入データは増えない | screenshots/purchase/S-19-retest-pc.png |
| S-23（回帰） | OK | 対象外 | 4000 0000 0000 0002 で「クレジットカードが拒否されました。デビットカードでの支払いをお試しください。」と表示され、サンクスへは移動しない。購入データは増えず、カートもそのまま。コンソールにStripeのAPIが返す402が1件 | screenshots/purchase/S-23-retest-pc.png |
| S-17（回帰） | OK | 対象外 | 代金引換でサンクスページへ。purchase_histories id=10（cod、shipping_fee=1850、cod_fee=330、total_price=4053、delivery_name=UAT購入PC代引再）、items 3件、カートが空。purchase_bank_transfers は増えない | screenshots/purchase/S-17-retest-pc.png |

- 気づいた点（合否には含めていない）：銀行振込の「振込先のご案内」で「戻る」を押して購入ページへ戻ったあと、購入ページ（/purchase、/purchase/{item}）を開き直したり商品詳細から入り直したりしても、戻ったときに復元された入力（お届け先住所・支払方法）が残ったままになる（例：沖縄県・銀行振込が選ばれた状態で開く。別の商品の購入ページ /purchase/1 では初期値の北海道・クレジットカード）。この確認では、保存待ちの注文が戻った後もセッションに残っているように見える。再現：/purchase/7 で住所を沖縄県、支払方法を銀行振込にして確定→「戻る」→商品詳細→/purchase/7 を開く
- コンソールエラー：なし（S-23でStripeのAPIが返す402が1件のみ）
- 追加したデータ（前の分に加えて）：purchase_histories に5件（id=7 UAT振込PC（customer_balance）、id=8 UAT振込PC再読込（customer_balance）、id=9 UAT購入PCカード3（card）、id=10 UAT購入PC代引再（cod）、id=11 UAT振込SP（customer_balance））、purchase_history_items に15件、purchase_bank_transfers に3件（id=1〜3。purchase_history_id=7、8、11）。Stripeのテスト環境にCheckout Sessionと銀行振込のPaymentIntentが複数できている（「戻る」で取り消した分を含む）。ログインユーザーのカートは空

## 再実施3（2026-10-22）

「戻る」で復元した入力が、戻った直後の1回だけ表示され、それ以外の訪問（再読み込み・商品詳細から入り直し）では会員登録の内容（初期値）になるよう修正した後に、PCで再実施した。前の結果はそのまま残している。

| 確認 | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| 確認1（前回の再現手順） | OK | 対象外 | /purchase/7 を開くと初期値（北海道札幌市UAT1-1・クレジットカード）。氏名「UAT復元確認」・電話番号・沖縄県・銀行振込にして確定→案内ページ（振込金額2,758円）で「戻る」→ /purchase/7 に戻り、氏名・電話番号・沖縄県・銀行振込が残っている（URLに ?restore なし）。商品詳細（/items/7）を開いてから /purchase/7 を開くと、会員登録の初期値（北海道札幌市UAT1-1・氏名・電話番号・クレジットカード）に戻っている | screenshots/purchase/R3-1-after-back-pc.png, screenshots/purchase/R3-1-revisit-pc.png |
| 確認2（カード・Stripeの「戻る」） | OK | 対象外 | /purchase（S-2の状態）で氏名「UAT復元カード」・東京都にして確定→Stripeの決済ページの「戻る」→ /purchase に戻り、氏名・電話番号・東京都・クレジットカードが残り、送料2,370円・合計4,243円（URLに ?restore なし）。再読み込みすると初期値（北海道・会員登録の氏名・電話番号、送料1,850円・合計3,723円）になる | screenshots/purchase/R3-2-after-back-pc.png, screenshots/purchase/R3-2-reload-pc.png |
| S-24（回帰） | OK | 対象外 | 沖縄県・銀行振込で確定→案内ページ（振込金額6,113円）で「戻る」→入力（氏名・電話番号・沖縄県・銀行振込・送料4,240円・合計6,113円）が残る。購入データは増えない。再読み込みすると初期値に戻る | screenshots/purchase/R3-S-24-pc.png |
| S-17（回帰） | OK | 対象外 | 代金引換（小計1,873円・送料1,850円・代引手数料330円・合計4,053円）でサンクスページへ。purchase_histories id=12（cod、1850/330/4053、delivery_name=UAT購入PC代引3）、items 3件（2個×330=660、1個×795=795、1個×418=418）、カートが空。purchase_bank_transfers は増えない（3件のまま） | screenshots/purchase/R3-S-17-pc.png |

- コンソールエラー：なし（この再実施でStripeのAPIの402などは出していない）
- 追加したデータ（前の分に加えて）：purchase_histories に1件（id=12 UAT購入PC代引3、cod）、purchase_history_items に3件。Stripeのテスト環境にCheckout Sessionと銀行振込のPaymentIntent（「戻る」で取り消した分）が数件できている。ログインユーザーのカートは空

## ユーザー確認チェックリスト

- [ ] カート・商品詳細の「購入する」から購入ページへの流れが自然
- [ ] 数量・住所・支払方法を変えたときに、送料・合計がすぐ変わるのがわかりやすい
- [ ] 代引手数料の表示がわかりやすい
- [ ] 住所追加モーダルの操作と、追加後に住所が選ばれる動きがわかりやすい
- [ ] エラーメッセージの文言と位置がわかりやすい
- [ ] Stripeの決済ページ（クレジットカード・銀行振込）に表示される商品名・送料・金額に違和感がない
- [ ] サンクスページの文言と背景画像の見え方に違和感がない
- [ ] SPで、数量入力・住所の選択・支払方法のカード・「注文を確定する」が押しやすい（見た目の細部は11月1日以降にFigmaで仕上げる前提）

## 判定

- 受け入れ：未 / 済（ユーザーが記入する）
- コメント：
