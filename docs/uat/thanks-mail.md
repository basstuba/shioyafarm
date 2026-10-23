# UAT：購入・予約サンクスメール

| 項目 | 内容 |
|---|---|
| 対象ルート | 新しいルートはない。メールを送るのは既存の次のルート：POST /payment（shop.checkout.payment。代金引換、顧客残高で支払い済みの銀行振込）、GET /purchase/success（shop.checkout.success。クレジットカード）、POST /purchase/bank-transfer/confirm（shop.checkout.bank-transfer.confirm。銀行振込の「振込み」）、POST /reservation/payment（shop.reservation.payment。顧客残高で支払い済みの銀行振込）、GET /reservation/success（shop.reservation.success。クレジットカード）、POST /reservation/bank-transfer/confirm（shop.reservation.bank-transfer.confirm。銀行振込の「振込み」） |
| ブランチ | feat/thanks-mail |
| 根拠 | 設計書に記載なし（ユーザーとの相談で決定）。docs/05-table-specification.md（purchase_histories、purchase_history_items、purchase_bank_transfers、reservations、reservation_bank_transfers）、docs/02-initial-settings.md（サイト名・メールアドレス・支払方法） |
| デザイン | Laravel の Markdown メール（HTML版とテキスト版）。Figmaのデザインは使わない |
| 実施日 | 2026-10-23 |

ユーザーと決めた事項：

- 購入と予約の両方で、データを保存した直後にサンクスメールを送る。すべての支払方法（予約はクレジットカードと銀行振込）で送る。
- 形式は Laravel の Markdown メール。キューを使わず同期で送り、注文・予約の保存がコミットされた後に送る。送信に失敗しても注文・予約は成立させ、サンクスページを表示してログに残す。
- 本文は次の構成（たたき台どおり）：宛名、あいさつ、■ご注文内容（注文番号・ご注文日時・お支払方法・商品明細・商品小計・送料・代引手数料（代金引換のみ）・合計）、■お届け先（氏名・住所・電話番号）、■お振込先（銀行振込のみ）、マイページで確認できる案内、送信専用の注意とお問い合わせフォームの案内、署名（しおや農園 / shioyafarm@example.com）。
- ■お振込先：銀行名・支店名・口座種別・口座番号・口座名義・お振込金額と「※ お客様専用の口座です。ご入金を確認してから発送いたします。」。Stripeの顧客残高で支払い済み（振込先の5項目がnull）のときは、振込先の代わりに「お客様の残高からお支払いが完了しています」と表示する。
- 範囲外：農園（管理者）への新規注文通知（管理画面の実装時に検討）、入金確認メール、発送通知メール（作らない）。

Claudeが決めた事項（OKをもらう）：

- 宛先は会員登録のメールアドレス。宛名（「山田 太郎 様」）は会員登録の氏名（姓 名）。■お届け先の氏名は注文時に入力したお届け先氏名（会員の氏名と違ってもよい）。
- 件名：購入は「【しおや農園】ご注文ありがとうございます（注文番号：{purchase_histories.id}）」、予約は「【しおや農園】ご予約ありがとうございます（予約番号：{reservations.id}）」。
- 予約のメールは見出しを「■ご予約内容」にし、「予約番号」「ご予約日時」と表記する。商品明細は1行、代引手数料の行はない。発送について、予約サンクスページと同じ「商品は9月より、準備が整い次第順次発送いたします。」を■ご予約内容の後に入れる。
- ご注文日時はデータの保存日時を「2026年10月23日 14:05」の形で表示する。お支払方法は「クレジットカード」「銀行振込」「代金引換」と表示する。金額は「¥1,850」のようにカンマ区切りで表示する。
- お届け先の住所は「都道府県＋市区町村以下」をつなげて1行で表示する。
- お問い合わせフォームの案内には、お問い合わせページ（/inquiry）のURLを載せる。マイページの案内は文言だけにし、URLはマイページの実装時に追加する。
- 差出人は「しおや農園 <shioyafarm@example.com>」。Markdownメールのヘッダーは「しおや農園」の文字にし、色はサイトのメインカラーに合わせる（ボタンは置かない）。
- データを保存しなかった場合（Stripeや振込先のご案内から「戻る」、カードの拒否、サンクスページの再読み込み・直接アクセス、二重押しの2回目）はメールを送らない。

## 受け入れ条件

| ID | 条件 |
|---|---|
| AC-1 | 購入を完了すると、会員登録のメールアドレスに件名「【しおや農園】ご注文ありがとうございます（注文番号：N）」のメールが1通届く。クレジットカード・銀行振込・代金引換のいずれでも届く |
| AC-2 | 購入のメールに、宛名（会員の氏名）、注文番号、ご注文日時、お支払方法、商品ごとの商品名・数量・小計、商品小計・送料・合計が、サンクスページの直前の購入ページと同じ金額で載っている。代金引換のときだけ代引手数料¥330の行があり、合計に含まれている |
| AC-3 | メールの■お届け先に、注文時に入力したお届け先氏名・住所・電話番号が載っている |
| AC-4 | 銀行振込のときだけ■お振込先が載り、振込先のご案内ページと同じ銀行名・支店名・口座種別・口座番号・口座名義・お振込金額と、「お客様専用の口座です。ご入金を確認してから発送いたします。」が表示される。顧客残高で支払い済みのときは「お客様の残高からお支払いが完了しています」が表示され、口座の項目は出ない |
| AC-5 | 予約を完了すると、件名「【しおや農園】ご予約ありがとうございます（予約番号：N）」のメールが1通届き、予約した商品・数量・小計・送料・合計、お届け先、9月からの発送の案内が載っている。銀行振込のときは■お振込先が載る |
| AC-6 | メールの最後に、マイページで確認できる案内、送信専用の注意、お問い合わせページのURL、署名（しおや農園 / shioyafarm@example.com）が載っている。差出人は「しおや農園」 |
| AC-7 | 注文・予約が保存されなかった場合（戻る、カードの拒否、サンクスページの再読み込み・直接アクセス、ボタンの二重押し）は、メールが届かない・二重に届かない |
| AC-8 | メールの送信に失敗しても、サンクスページが表示され、注文・予約のデータは保存される |
| AC-9 | HTML版・テキスト版のどちらでも内容が読め、スマートフォンの幅でも横スクロールなしで読める。お届け先氏名などに入力したHTMLのタグは、タグとして解釈されずに文字のまま表示される |

## シナリオ

金額の前提：会員登録の住所は北海道。購入はシーダーの商品（じゃがいも2kg 330円、玉ねぎ5kg 795円、人参2kg 418円）、予約は北の恵みセット10kg（1,450円）を使う。メールは Mailpit（http://localhost:8025）で確認する。各シナリオの前に、Mailpitの受信件数を控えておく。

| ID | 対応AC | 画面幅 | 前提 | 手順 | 期待結果 |
|---|---|---|---|---|---|
| S-1 | AC-1, AC-2, AC-3, AC-6 | PC | ログイン済み。カートにじゃがいも2kg×2、玉ねぎ5kg×1、人参2kg×1 | お届け先氏名「UATメールカード」でクレジットカードを選んで注文し、Stripeでテストカード 4242 4242 4242 4242 で支払う。Mailpitを開く | 会員のメールアドレス宛てに1通届く。件名「【しおや農園】ご注文ありがとうございます（注文番号：保存された purchase_histories.id）」、差出人「しおや農園 <shioyafarm@example.com>」。本文に宛名（会員の姓 名＋様）、お支払方法「クレジットカード」、じゃがいも2kg×2 ¥660・玉ねぎ5kg×1 ¥795・人参2kg×1 ¥418、商品小計¥1,873、送料¥1,850、合計¥3,723。代引手数料の行と■お振込先はない。■お届け先は「UATメールカード」と入力した住所・電話番号。最後にマイページの案内・送信専用の注意・お問い合わせページのURL・署名がある |
| S-2 | AC-1, AC-4 | PC | S-1と同じカート | お届け先氏名「UATメール振込」で銀行振込を選んで注文し、「振込先のご案内」の内容を控えてから「振込み」を押す。Mailpitを開く | 1通届く。お支払方法「銀行振込」、合計¥3,723。■お振込先の銀行名・支店名・口座種別・口座番号・口座名義が控えた内容と同じで、お振込金額¥3,723、「※ お客様専用の口座です。ご入金を確認してから発送いたします。」がある |
| S-3 | AC-1, AC-2 | PC | S-1と同じカート | お届け先氏名「UATメール代引」で代金引換を選んで「注文を確定する」。Mailpitを開く | 1通届く。お支払方法「代金引換」、商品小計¥1,873、送料¥1,850、代引手数料¥330、合計¥4,053。■お振込先はない |
| S-4 | AC-4 | PC | Stripeのテスト環境で、会員のStripe顧客に注文金額以上の残高が入金済み（用意できない場合は未実施とし、PHPUnitのテストで確認する） | 銀行振込で注文し、「振込み」を押す。Mailpitを開く | ■お振込先に「お客様の残高からお支払いが完了しています」が表示され、銀行名などの口座の項目は出ない |
| S-5 | AC-5, AC-6 | PC | ログイン済み | 北の恵みセット10kgを数量1、お届け先氏名「UATメール予約カード」、クレジットカードで予約し、4242 4242 4242 4242 で支払う。Mailpitを開く | 1通届く。件名「【しおや農園】ご予約ありがとうございます（予約番号：保存された reservations.id）」。■ご予約内容に予約番号・ご予約日時・お支払方法「クレジットカード」、北の恵みセット10kg×1 ¥1,450、商品小計¥1,450、送料¥1,530、合計¥2,980。代引手数料の行と■お振込先はない。「商品は9月より、準備が整い次第順次発送いたします。」がある。■お届け先は「UATメール予約カード」 |
| S-6 | AC-5, AC-4 | PC | ログイン済み | 北の恵みセット10kgを数量1、銀行振込で予約し、「振込先のご案内」の内容を控えてから「振込み」を押す。Mailpitを開く | 1通届く。お支払方法「銀行振込」、合計¥2,980。■お振込先が控えた内容と同じで、お振込金額¥2,980 |
| S-7 | AC-7 | PC | S-1と同じカート | 銀行振込で「注文を確定する」→「振込先のご案内」で「戻る」。クレジットカードで「注文を確定する」→ Stripeで「戻る」。Stripeでテストカード 4000 0000 0000 0002 で支払う | どの操作でもメールは届かない（受信件数が変わらない） |
| S-8 | AC-7 | PC | S-3の直後 | 購入サンクスページを再読み込みする。/purchase/success、/purchase/thanks、/reservation/success、/reservation/thanks を直接開く | トップページへ移動し、メールは増えない |
| S-9 | AC-7 | PC | S-1と同じカート | 代金引換で「注文を確定する」をすばやく2回押す。予約の銀行振込で「振込み」をすばやく2回押す | それぞれメールは1通だけ届く |
| S-10 | AC-3 | PC | ログイン済み（会員の氏名とは違うお届け先氏名を使う） | お届け先氏名を「UAT別名 花子」、電話番号を「08011112222」にして代金引換で注文する | 宛名は会員の氏名、■お届け先の氏名は「UAT別名 花子」、電話番号は「08011112222」 |
| S-11 | AC-9 | PC | ログイン済み | お届け先氏名を「<b>UAT太字</b>」にして代金引換で注文し、MailpitでHTML版を開く | 「<b>UAT太字</b>」が太字にならず、そのままの文字で表示される |
| S-12 | AC-9 | PC/SP | S-1〜S-6のメール | MailpitでHTML版とテキスト版（Text）をそれぞれ開く。SP幅でHTML版を開く | テキスト版でも見出し・金額・振込先が崩れずに読める。SP幅でも横スクロールが出ず、金額の行が読める |
| S-13 | AC-8 | PC | ログイン済み。`docker compose stop mail` でMailpitを止める | 代金引換で注文する。確認後 `docker compose start mail` で戻す | サンクスページが表示され、purchase_histories に保存される。`storage/logs/laravel.log` にメール送信の失敗が記録される |

## 実施結果

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | OK | 対象外 | 会員 uat-buy@example.com（姓「UAT」名「購入太郎」）で、カード（Stripe 4242 4242 4242 4242）。purchase_histories id=47。Mailpitに1通。件名「【しおや農園】ご注文ありがとうございます（注文番号：47）」、差出人「しおや農園 <shioyafarm@example.com>」、宛名「UAT 購入太郎 様」、お支払方法「クレジットカード」、じゃがいも2kg×2 ¥660・玉ねぎ5kg×1 ¥795・人参2kg×1 ¥418、商品小計¥1,873、送料¥1,850、合計¥3,723（購入ページ・Stripeの画面と同額）。代引手数料の行と■お振込先はない。■お届け先は「UATメールカード 様」「北海道札幌市UAT1-1」「電話番号：09011112222」。最後にマイページの案内・送信専用の注意・http://localhost/inquiry・署名（しおや農園 / shioyafarm@example.com）がある。HTML版・テキスト版とも確認 | screenshots/thanks-mail/S-1-html-pc.png, screenshots/thanks-mail/S-1-html-sp.png |
| S-2 | OK | 対象外 | purchase_histories id=48。「振込先のご案内」は銀行名いろは銀行・支店名中央支店・口座種別普通・口座番号TEST48213081・口座名義ストライプジャパン（カ　シュウノウダイコウ・振込金額3,723円。「振込み」後のメール（1通）の■お振込先は同じ内容で、お振込金額¥3,723、「※ お客様専用の口座です。ご入金を確認してから発送いたします。」がある。お支払方法「銀行振込」、合計¥3,723、お届け先「UATメール振込 様」。purchase_bank_transfers の保存値ともメールが一致。なお同じ操作の前に「戻る」を1回押している（S-7）。口座番号はPaymentIntentを作り直しても同じ値（Stripeテスト環境の仕様） | screenshots/thanks-mail/S-2-html-pc.png, screenshots/thanks-mail/S-2-html-sp.png |
| S-3 | OK | 対象外 | purchase_histories id=49。お支払方法「代金引換」、商品小計¥1,873、送料¥1,850、代引手数料¥330、合計¥4,053（購入ページの表示と同額）。■お振込先なし。お届け先「UATメール代引 様」 | screenshots/thanks-mail/S-3-html-pc.png, screenshots/thanks-mail/S-3-html-sp.png |
| S-4 | OK | 対象外 | Stripeのテスト環境で、専用の会員（uat-balance@example.com、UAT 残高花子）のStripe顧客に fund_cash_balance で10,000円を入金してから、銀行振込で「注文を確定する」。振込先のご案内ページは出ず、そのままサンクスページ（/purchase/thanks）へ移動した（顧客残高で支払い済みのため。シナリオの「振込み」を押す手順は不要だった）。purchase_histories id=53、purchase_bank_transfers id=13（銀行名・支店名・口座種別・口座番号・口座名義がNULL、amount=3723）。メール（1通、宛名「UAT 残高花子 様」）の■お振込先は「お客様の残高からお支払いが完了しています。」のみで、銀行名などの口座の項目は出ない。お支払方法「銀行振込」、合計¥3,723 | screenshots/thanks-mail/S-4-html-pc.png, screenshots/thanks-mail/S-4-html-sp.png |
| S-5 | OK | 対象外 | reservations id=20、カードで予約し4242で支払い。件名「【しおや農園】ご予約ありがとうございます（予約番号：20）」。■ご予約内容に予約番号・ご予約日時（2026年10月23日 23:30）・お支払方法「クレジットカード」、北の恵みセット 10kg × 1 ¥1,450、商品小計¥1,450、送料¥1,530、合計¥2,980。代引手数料の行と■お振込先はない。「商品は9月より、準備が整い次第順次発送いたします。」が■ご予約内容の後にある。お届け先「UATメール予約カード 様」。結びの案内は「ご予約の内容は、マイページの予約履歴でも…」 | screenshots/thanks-mail/S-5-html-pc.png, screenshots/thanks-mail/S-5-html-sp.png |
| S-6 | OK | 対象外 | reservations id=21、銀行振込。「振込先のご案内」（口座番号TEST48213081、振込金額2,980円）を確認して「振込み」。メール（1通）はお支払方法「銀行振込」、合計¥2,980、■お振込先は案内ページと同じ内容でお振込金額¥2,980、「※ お客様専用の口座です。ご入金を確認してから発送いたします。」あり | screenshots/thanks-mail/S-6-html-pc.png, screenshots/thanks-mail/S-6-html-sp.png |
| S-7 | OK | 対象外 | Mailpitの受信件数が変わらないことをAPIで確認（①銀行振込→振込先のご案内で「戻る」：2件のまま、purchase_histories も増えず、購入ページに戻る。②カード→Stripeで「戻る」リンク：購入ページに戻り入力が残る、メールは増えず注文も増えない。③4000 0000 0000 0002：Stripeに「拒否されました。デビットカードでの支払いをお試しください。」が出て、メール・注文とも増えない）。メールが増えたのは保存したときだけ | screenshots/thanks-mail/S-7-bank-back-pc.png, screenshots/thanks-mail/S-9-inbox-pc.png |
| S-8 | OK | 対象外 | S-3の直後に、サンクスページの再読み込み、/purchase/success、/purchase/thanks、/reservation/success、/reservation/thanks を順に開くと、すべてトップページ（/）へ移動。メールは4件のまま（増えない）、purchase_histories は49件のまま、reservations も増えない | screenshots/thanks-mail/S-9-inbox-pc.png |
| S-9 | OK | 対象外 | ①代金引換（お届け先氏名「UATメール二重COD」）で「注文を確定する」をdblclickで2回押す：purchase_histories id=52の1件だけ、メールも1通（注文番号：52のみ）。②予約の銀行振込（「UATメール予約二重」）で「振込み」をdblclickで2回押す：reservations id=22の1件だけ、reservation_bank_transfers id=13の1件、メールも1通（予約番号：22のみ）。Playwrightのdblclickのため、2回目のクリックが実際にボタンへ届いたかまでは確認できていない（押した後はサンクスページへ移動する） | screenshots/thanks-mail/S-9-inbox-pc.png, screenshots/thanks-mail/S-9-inbox-sp.png |
| S-10 | OK | 対象外 | purchase_histories id=50（delivery_name=UAT別名 花子、tell=08011112222、cod）。メールの宛名「UAT 購入太郎 様」、■お届け先は「UAT別名 花子 様」「北海道札幌市UAT1-1」「電話番号：08011112222」。金額は小計¥330・送料¥940・代引手数料¥330・合計¥1,600（購入ページと同額） | screenshots/thanks-mail/S-10-html-pc.png, screenshots/thanks-mail/S-10-html-sp.png |
| S-11 | OK | 対象外 | purchase_histories id=51（delivery_name=`<b>UAT太字</b>`）。HTML版は「<b>UAT太字</b> 様」が太字にならず文字のまま表示される（HTMLソースは &lt;b&gt;UAT太字&lt;/b&gt;）。テキスト版も同じ文字のまま | screenshots/thanks-mail/S-11-html-pc.png, screenshots/thanks-mail/S-11-html-sp.png |
| S-12 | OK | OK | Mailpitで注文番号48（銀行振込）のHTML版・Text版をPC・SPで表示。テキスト版は見出し（■ご注文内容など）・金額・振込先が崩れずに読める（SPでは本文が画面幅で折り返される）。HTML版のメール本文（/view/<ID>.html）をS-1〜S-6・S-10・S-11の8通についてPC（1280px）・SP（414px）で確認し、すべて横スクロールなし（scrollWidth＝clientWidth）、金額の行が読める（SPでは口座名義が折り返して「ウ」だけ次の行になる程度）。ヘッダーはサイトのメインカラー（オレンジ）で「しおや農園」の文字 | screenshots/thanks-mail/S-12-mailpit-html-pc.png, screenshots/thanks-mail/S-12-mailpit-text-pc.png, screenshots/thanks-mail/S-12-mailpit-html-sp.png, screenshots/thanks-mail/S-12-mailpit-text-sp.png, screenshots/thanks-mail/S-12-txt-raw-pc.png |
| S-13 | OK | 対象外 | `docker compose stop mail` でMailpitを止めて、代金引換（「UATメール失敗」）で注文。約4秒後にサンクスページ（/purchase/thanks）が表示され、purchase_histories id=54（cod、total_price=1600）が保存された。`storage/logs/laravel.log` に「[2026-10-23 23:33:52] local.ERROR: サンクスメールの送信に失敗しました {"saved":"App\\Models\\Purchase_history","id":54,"exception":"Symfony\\Component\\Mailer\\Exception\\TransportException","message":"Connection could not be established with host \"mail:1025\": …getaddrinfo for mail failed…"}」が記録された。確認後 `docker compose start mail` で起動し直し、既存のメールが残っていることも確認（11件のまま） |  |

- コンソールエラー：なし（Stripeで4000 0000 0000 0002を使ったときの、Stripe APIの402（カード拒否）が1件のみで想定どおり）
- メール（Mailpit）：購入の確認メール7通（注文番号47〜53。54は送信失敗のため0通）、予約の確認メール3通（予約番号20〜22）。すべて宛先は会員のメールアドレス、差出人は「しおや農園 <shioyafarm@example.com>」。件名は購入「【しおや農園】ご注文ありがとうございます（注文番号：N）」、予約「【しおや農園】ご予約ありがとうございます（予約番号：N）」。ご注文日時は保存日時（JST）の「2026年10月23日 23:28」の形式。Mailpitは受信箱に既存の1通（Mailpit疎通確認）があり、合計11件
- 追加したデータ：
  - users に1件（姓「UAT」名「残高花子」、メール uat-balance@example.com、パスワード password、住所：北海道札幌市UAT2-2、電話 09022223333、id=4、S-4用）。stripe_customer_id は cus_VUiytE0eVPtH1H（Stripeのテスト環境に作成し、10,000円を入金。S-4の注文で3,723円使用）
  - purchase_histories に8件（id=47 UATメールカード（card）、48 UATメール振込（customer_balance）、49 UATメール代引（cod）、50 UAT別名 花子（cod）、51 `<b>UAT太字</b>`（cod）、52 UATメール二重COD（cod）、53 UATメール残高（customer_balance、残高払い）、54 UATメール失敗（cod、メール送信失敗）。id=53は uat-balance、他は uat-buy@example.com の会員）、purchase_history_items に16件、purchase_bank_transfers に2件（id=12：purchase_history_id=48、id=13：purchase_history_id=53で口座の5項目がNULL）
  - reservations に3件（id=20 UATメール予約カード（card）、21 UATメール予約振込（customer_balance）、22 UATメール予約二重（customer_balance））、reservation_bank_transfers に2件（id=12、13）
  - Stripeのテスト環境：Checkout Session・PaymentIntent（銀行振込の「戻る」でキャンセルしたもの、振込先を発行したまま入金待ちのもの）が数件できている。また、会員 test@test.com（users id=1）のStripe顧客（cus_VUM6PKcRF5eK77）と、uat-buy@example.com の顧客（cus_VUKlskCyp8yzfr）に、顧客残高の入金テスト（10,000円・3,723円）をしてしまった（顧客を取り違えて入金。後者は過去のUATで作った未払いの銀行振込の PaymentIntent に自動で充当された）。Stripeのテスト環境だけの話で、アプリのDBには影響しない
### 再実施（レビュー対応後）

実施日：2026-10-23。レビュー対応（保存処理の戻り値の整理、SMTPタイムアウト10秒、振込先データが無い場合の扱い、支払方法の表示名の共通化、購入・予約メール本文の組み立ての共通化）の後に、PCで再実施した。会員は uat-buy@example.com（姓「UAT」名「購入太郎」。残高なし）を使用。test@test.com と uat-balance@example.com は使っていない。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | OK | 対象外 | カート：じゃがいも2kg×2、玉ねぎ5kg×1、人参2kg×1。お届け先氏名「UATメール再カード」、クレジットカード、Stripeで4242 4242 4242 4242。purchase_histories id=57。Mailpitに1通。件名「【しおや農園】ご注文ありがとうございます（注文番号：57）」、差出人「しおや農園 <shioyafarm@example.com>」、宛先 uat-buy@example.com、宛名「UAT 購入太郎 様」、お支払方法「クレジットカード」、じゃがいも2kg×2 ¥660・玉ねぎ5kg×1 ¥795・人参2kg×1 ¥418、商品小計¥1,873、送料¥1,850、合計¥3,723（購入ページ・Stripeの画面と同額）。代引手数料の行と■お振込先はない。■お届け先は「UATメール再カード 様」「北海道札幌市UAT1-1」「電話番号：09011112222」。最後にマイページの案内・送信専用の注意・http://localhost/inquiry・署名がある。HTML版・テキスト版とも確認 | screenshots/thanks-mail/S-1-recheck-pc.png |
| S-2 | OK | 対象外 | purchase_histories id=58（customer_balance）。お届け先氏名「UATメール再振込」。「振込先のご案内」は銀行名いろは銀行・支店名中央支店・口座種別普通・口座番号TEST48213081・口座名義ストライプジャパン（カ　シュウノウダイコウ・振込金額3,723円。「振込み」後のメール（1通、注文番号58）はお支払方法「銀行振込」、合計¥3,723、■お振込先が案内ページと同じ内容でお振込金額¥3,723、「※ お客様専用の口座です。ご入金を確認してから発送いたします。」あり。purchase_bank_transfers id=14の保存値ともメールが一致 | screenshots/thanks-mail/S-2-recheck-pc.png |
| S-3 | OK | 対象外 | purchase_histories id=55。お届け先氏名「UATメール再代引」。メール（注文番号55）はお支払方法「代金引換」、商品小計¥1,873、送料¥1,850、代引手数料¥330、合計¥4,053（購入ページの表示と同額）。■お振込先なし | screenshots/thanks-mail/S-3-recheck-pc.png |
| S-5 | OK | 対象外 | reservations id=23。お届け先氏名「UATメール再予約カード」、カードで予約し4242で支払い。件名「【しおや農園】ご予約ありがとうございます（予約番号：23）」。■ご予約内容に予約番号・ご予約日時（2026年10月23日 23:46）・お支払方法「クレジットカード」、北の恵みセット 10kg × 1 ¥1,450、商品小計¥1,450、送料¥1,530、合計¥2,980。代引手数料の行と■お振込先はない。「商品は9月より、準備が整い次第順次発送いたします。」が■ご予約内容の後にある。お届け先「UATメール再予約カード 様」。なお、Stripe支払い後のURLは /reservation/success?session_id=…のままサンクスページの内容（「ご予約ありがとうございます。」）が表示される（前回と同じ挙動。Playwrightの /reservation/thanks 待ちは30秒を超えたが、表示とメールは問題なし） | screenshots/thanks-mail/S-5-recheck-pc.png |
| S-6 | OK | 対象外 | reservations id=24（customer_balance）。お届け先氏名「UATメール再予約振込」。「振込先のご案内」（口座番号TEST48213081、振込金額2,980円）を確認して「振込み」。メール（1通、予約番号24）はお支払方法「銀行振込」、合計¥2,980、■お振込先は案内ページと同じ内容（いろは銀行・中央支店・普通・TEST48213081・ストライプジャパン（カ　シュウノウダイコウ）でお振込金額¥2,980、「※ お客様専用の口座です。ご入金を確認してから発送いたします。」あり。reservation_bank_transfers id=14 | screenshots/thanks-mail/S-6-recheck-pc.png |
| S-9 | OK | 対象外 | ①代金引換（お届け先氏名「UATメール再二重COD」）で「注文を確定する」をdblclickで2回押す：purchase_histories id=56の1件だけ、メールも1通（注文番号：56のみ）。②予約の銀行振込（「UATメール再予約二重」）で「振込み」をdblclickで2回押す：reservations id=25の1件だけ、reservation_bank_transfers の追加も1件、メールも1通（予約番号：25のみ）。Playwrightのdblclickのため、2回目のクリックが実際にボタンへ届いたかまでは確認できていない（前回と同じ制約）。Mailpitの総件数は11件→18件（追加7通＝購入55〜58の4通と予約23〜25の3通。59は送信失敗） | screenshots/thanks-mail/S-9-recheck-pc.png |
| S-13 | OK | 対象外 | `docker compose stop mail` でMailpitを止めて、代金引換（「UATメール再失敗」）で注文。ボタンを押してからサンクスページ（/purchase/thanks）が表示されるまで約4.3秒（SMTPタイムアウト10秒の範囲内）。purchase_histories id=59（cod、total_price=4053）が保存された。`storage/logs/laravel.log` に「[2026-10-23 23:46:11] local.ERROR: サンクスメールの送信に失敗しました {"saved":"App\\Models\\Purchase_history","id":59,"exception":"Symfony\\Component\\Mailer\\Exception\\TransportException","message":"Connection could not be established with host \"mail:1025\": …getaddrinfo for mail failed…"}」が記録された。確認後 `docker compose start mail` で起動し直し、既存のメールが残っていることも確認（起動後15件→その後の再実施で18件）。この環境ではホスト名が引けない失敗のため、10秒のタイムアウト自体が効く場面（接続できるが応答しない）は確認できていない | screenshots/thanks-mail/S-13-recheck-pc.png |

支払方法の表示名（共通化の影響確認）：

| 画面 | 結果 | 備考 | スクリーンショット |
|---|---|---|---|
| 購入ページ（/purchase） | OK | 「クレジットカード」「銀行振込」「代金引換」の3つ。説明文は「Visa / Master / JCB」「銀行口座にお振込み」「受け取り時にお支払い」。代金引換を選ぶと「代引手数料 330円」の行が出て合計が4,053円になる。以前と同じ | screenshots/thanks-mail/pay-names-purchase-recheck-pc.png（代金引換を選択した状態） |
| 予約ページ（/reservation/11） | OK | 「クレジットカード」「銀行振込」の2つのみ（代金引換なし）。説明文は以前と同じ | screenshots/thanks-mail/pay-names-reservation-recheck-pc.png |

- コンソールエラー：なし（再実施中のブラウザのコンソールにエラー・警告なし）
- メール（Mailpit）：再実施で届いたのは購入の確認メール4通（注文番号55〜58）と予約の確認メール3通（予約番号23〜25）。すべて宛先は uat-buy@example.com、差出人は「しおや農園 <shioyafarm@example.com>」、件名の形式は前回と同じ。注文番号59は送信失敗のため0通。Mailpitは `docker compose start mail` で起動済みに戻した
- 追加したデータ（再実施分）：
  - purchase_histories に5件（id=55 UATメール再代引（cod）、56 UATメール再二重COD（cod）、57 UATメール再カード（card）、58 UATメール再振込（customer_balance）、59 UATメール再失敗（cod、メール送信失敗）。すべて uat-buy@example.com の会員）、purchase_history_items に15件（各3行）、purchase_bank_transfers に1件（id=14：purchase_history_id=58）
  - reservations に3件（id=23 UATメール再予約カード（card）、24 UATメール再予約振込（customer_balance）、25 UATメール再予約二重（customer_balance））、reservation_bank_transfers に2件（id=14：reservation_id=24、id=15：reservation_id=25）
  - Stripeのテスト環境：Checkout Session（カード2件）と、銀行振込のPaymentIntent（購入1件・予約2件）ができた。顧客残高の入金はしていない

### 再々実施（2回目のレビュー対応後）

実施日：2026-10-23（Mailpit上の注文日時は日付をまたいで2026年10月24日 00:03〜00:06）。2回目のレビュー対応（メール送信をレスポンス後に行う defer()、差出人と署名を config('mail.from') から出す、商品明細の改行、商品の表示名の共通化、不正な文字の扱い）の後に、PCで再実施した。S-12 はSP幅でHTML版を確認した。会員は uat-buy@example.com（姓「UAT」名「購入太郎」。残高なし）を使用。test@test.com と uat-balance@example.com は使っていない。`config('mail.from')` は「しおや農園 <shioyafarm@example.com>」。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | OK | 対象外 | カート：じゃがいも2kg×2、玉ねぎ5kg×1、人参2kg×1。お届け先氏名「UATメール再々カード」、クレジットカード、Stripeで4242 4242 4242 4242。purchase_histories id=60。Stripeから戻ったサンクスページ（/purchase/success?session_id=…。表示は「ご注文完了」）の表示と同時（Mailpitの受信時刻が戻りの時刻の約0.02秒後）にメールが届き、1通だけ（Mailpit 18件→19件）。件名「【しおや農園】ご注文ありがとうございます（注文番号：60）」、差出人「しおや農園 <shioyafarm@example.com>」、宛先 uat-buy@example.com、宛名「UAT 購入太郎 様」、お支払方法「クレジットカード」、商品小計¥1,873、送料¥1,850、合計¥3,723（購入ページ・Stripeの画面と同額）。代引手数料の行と■お振込先はない。■お届け先は「UATメール再々カード 様」「北海道札幌市UAT1-1」「電話番号：09011112222」。最後にマイページの案内・送信専用の注意・http://localhost/inquiry・署名「しおや農園 / shioyafarm@example.com」。HTML版・テキスト版とも確認。商品明細：HTML版は3商品が1つのブロックの中で行間が等しく並び、余白の二重はない（HTMLは1つの段落の中を <br> で改行）。テキスト版も3行が連続 | screenshots/thanks-mail/S-1-thanks-recheck2-pc.png, screenshots/thanks-mail/S-1-recheck2-pc.png, screenshots/thanks-mail/S-1-recheck2-sp.png |
| S-2 | OK | 対象外 | purchase_histories id=64（customer_balance）。お届け先氏名「UATメール再々振込」。「振込先のご案内」は銀行名いろは銀行・支店名中央支店・口座種別普通・口座番号TEST48213081・口座名義ストライプジャパン（カ　シュウノウダイコウ・振込金額3,723円。「振込み」（約1.5秒でサンクスページ）後のメール（1通、注文番号64）はお支払方法「銀行振込」、合計¥3,723、■お振込先が案内ページと同じ内容でお振込金額¥3,723、「※ お客様専用の口座です。ご入金を確認してから発送いたします。」あり。差出人・署名は「しおや農園 / shioyafarm@example.com」。商品明細は3商品が等間隔。purchase_bank_transfers id=15 | screenshots/thanks-mail/S-2-guide-recheck2-pc.png, screenshots/thanks-mail/S-2-thanks-recheck2-pc.png, screenshots/thanks-mail/S-2-recheck2-pc.png |
| S-3 | OK | 対象外 | purchase_histories id=61。お届け先氏名「UATメール再々代引」。「注文を確定する」からサンクスページ（/purchase/thanks）まで約0.3秒。メール（注文番号61、1通）はサンクスページの表示と同時に届いた（Mailpitの受信時刻はクリックから約0.1秒以内）。お支払方法「代金引換」、商品小計¥1,873、送料¥1,850、代引手数料¥330、合計¥4,053（購入ページの表示と同額）。■お振込先なし。差出人・署名は「しおや農園 / shioyafarm@example.com」 | screenshots/thanks-mail/S-3-purchase-recheck2-pc.png, screenshots/thanks-mail/S-3-thanks-recheck2-pc.png, screenshots/thanks-mail/S-3-recheck2-pc.png |
| S-5 | OK | 対象外 | reservations id=26。お届け先氏名「UATメール再々予約カード」、カードで予約し4242で支払い。件名「【しおや農園】ご予約ありがとうございます（予約番号：26）」、1通。■ご予約内容に予約番号・ご予約日時（2026年10月24日 00:05）・お支払方法「クレジットカード」、北の恵みセット 10kg × 1 ¥1,450、商品小計¥1,450、送料¥1,530、合計¥2,980。代引手数料の行と■お振込先はない。「商品は9月より、準備が整い次第順次発送いたします。」が■ご予約内容の後にある。お届け先「UATメール再々予約カード 様」。署名「しおや農園 / shioyafarm@example.com」。Stripe支払い後は /reservation/success?session_id=…のままサンクスページの内容が表示される（これまでと同じ挙動） | screenshots/thanks-mail/S-5-thanks-recheck2-pc.png, screenshots/thanks-mail/S-5-recheck2-pc.png |
| S-6 | OK | 対象外 | reservations id=27（customer_balance）。お届け先氏名「UATメール再々予約振込」。「振込先のご案内」（口座番号TEST48213081、振込金額2,980円）を確認して「振込み」。メール（1通、予約番号27）はお支払方法「銀行振込」、合計¥2,980、■お振込先は案内ページと同じ内容（いろは銀行・中央支店・普通・TEST48213081・ストライプジャパン（カ　シュウノウダイコウ）でお振込金額¥2,980、「※ お客様専用の口座です。ご入金を確認してから発送いたします。」あり。reservation_bank_transfers id=16 | screenshots/thanks-mail/S-6-guide-recheck2-pc.png, screenshots/thanks-mail/S-6-thanks-recheck2-pc.png, screenshots/thanks-mail/S-6-recheck2-pc.png |
| S-9 | OK | 対象外 | ①代金引換（お届け先氏名「UATメール再々二重COD」）で「注文を確定する」をdblclickで2回押す：purchase_histories id=63の1件だけ、メールも1通（注文番号：63のみ）。②予約の銀行振込（「UATメール再々予約二重」）で「振込み」をdblclickで2回押す：reservations id=28の1件だけ、reservation_bank_transfers の追加も1件（id=17）、メールも1通（予約番号：28のみ）。defer() によるレスポンス後の送信でも重複なし。Playwrightのdblclickのため、2回目のクリックが実際にボタンへ届いたかまでは確認できていない（前回と同じ制約） | screenshots/thanks-mail/S-9a-recheck2-pc.png, screenshots/thanks-mail/S-9b-recheck2-pc.png |
| S-11 | OK | 対象外 | purchase_histories id=62（delivery_name=`<b>UAT太字</b>`、cod）。HTML版は「<b>UAT太字</b> 様」が太字にならず文字のまま表示される（HTMLソースは &lt;b&gt;UAT太字&lt;/b&gt; 様）。テキスト版も同じ文字のまま | screenshots/thanks-mail/S-11-recheck2-pc.png |
| S-12 | 対象外 | OK | SP幅（414px）でHTML版を確認。注文番号64（銀行振込）をスクリーンショットに撮り、60（カード）・61（代引）・26（予約カード）・27（予約振込）も含めて5通とも横スクロールなし（scrollWidth＝clientWidth＝399。縦スクロールバー分を除いた幅）。金額の行・■お振込先が読める（口座名義は折り返して「ウ」だけ次の行になる程度）。テキスト版（/view/<ID>.txt）もSP幅で横スクロールなし、見出し・金額・振込先が崩れずに読める | screenshots/thanks-mail/S-12-recheck2-sp.png, screenshots/thanks-mail/S-12-text-recheck2-sp.png, screenshots/thanks-mail/S-1-recheck2-sp.png |
| S-13 | OK | 対象外 | `docker compose stop mail` でMailpitを止めて、代金引換（「UATメール再々失敗」）で注文。サンクスページ（/purchase/thanks）まで約0.24秒（前回は約4.3秒。レスポンス後に送信するため待たされない）。purchase_histories id=65（cod、total_price=4053）が保存された。`storage/logs/laravel.log` に「[2026-10-24 00:06:32] local.ERROR: サンクスメールの送信に失敗しました {"saved":"App\\Models\\Purchase_history","id":65,"exception":"Symfony\\Component\\Mailer\\Exception\\TransportException","message":"Connection could not be established with host \"mail:1025\": …getaddrinfo for mail failed…"}」が記録された。確認後 `docker compose start mail` で起動し直し、既存のメールが残っていることも確認（26件のまま） | screenshots/thanks-mail/S-13-recheck2-pc.png |

- コンソールエラー：なし（ブラウザのコンソールにエラー・警告なし）
- メール（Mailpit）：再々実施で届いたのは購入の確認メール5通（注文番号60〜64）と予約の確認メール3通（予約番号26〜28）。すべて宛先は uat-buy@example.com、差出人は「しおや農園 <shioyafarm@example.com>」、署名は「しおや農園 / shioyafarm@example.com」。注文番号65は送信失敗のため0通。総件数は18件→26件。Mailpitは `docker compose start mail` で起動済みに戻した
- 追加したデータ（再々実施分）：
  - purchase_histories に6件（id=60 UATメール再々カード（card）、61 UATメール再々代引（cod）、62 `<b>UAT太字</b>`（cod）、63 UATメール再々二重COD（cod）、64 UATメール再々振込（customer_balance）、65 UATメール再々失敗（cod、メール送信失敗）。すべて uat-buy@example.com の会員）、purchase_history_items に18件（各3行）、purchase_bank_transfers に1件（id=15：purchase_history_id=64）
  - reservations に3件（id=26 UATメール再々予約カード（card）、27 UATメール再々予約振込（customer_balance）、28 UATメール再々予約二重（customer_balance））、reservation_bank_transfers に2件（id=16：reservation_id=27、id=17：reservation_id=28）
  - Stripeのテスト環境：Checkout Session（カード2件）と、銀行振込のPaymentIntent（購入1件・予約2件）ができた。顧客残高の入金はしていない

### 最終確認（3回目のレビュー対応後）

実施日：2026-10-23（Mailpit上の注文日時は日付をまたいで2026年10月24日 00:15〜00:19）。3回目のレビュー対応（保存直後にメール送信を登録する形への整理、カード・代金引換で振込先を読み込まないようにした）の後の回帰確認として、PCで S-1・S-2・S-3・S-5・S-13 を実施した。会員は uat-buy@example.com（姓「UAT」名「購入太郎」。残高なし）を使用。test@test.com と uat-balance@example.com は使っていない。カートは購入のたびに空になるため、S-2・S-3 の前にじゃがいも2kg×2・玉ねぎ5kg×1・人参2kg×1 を入れ直した。S-13 は動作確認が目的のため、じゃがいも2kg×1だけを「購入する」から注文した。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | OK | 対象外 | お届け先氏名「UATメール最終カード」、カード（Stripe 4242 4242 4242 4242）。purchase_histories id=66。サンクスページ（/purchase/success?session_id=…、表示は「ご注文完了」）が表示され、メールは1通だけ（Mailpit 26件→27件）。件名「【しおや農園】ご注文ありがとうございます（注文番号：66）」、差出人「しおや農園 <shioyafarm@example.com>」、宛先 uat-buy@example.com、宛名「UAT 購入太郎 様」、お支払方法「クレジットカード」、じゃがいも2kg×2 ¥660・玉ねぎ5kg×1 ¥795・人参2kg×1 ¥418、商品小計¥1,873、送料¥1,850、合計¥3,723（購入ページの表示と同額）。代引手数料の行と■お振込先はない。■お届け先は「UATメール最終カード 様」「北海道札幌市UAT1-1」「電話番号：09011112222」。最後にマイページの案内・送信専用の注意・http://localhost/inquiry・署名「しおや農園 / shioyafarm@example.com」。HTML版・テキスト版とも確認。再々実施と内容は同じ | screenshots/thanks-mail/S-1-thanks-final-pc.png, screenshots/thanks-mail/S-1-final-pc.png |
| S-2 | OK | 対象外 | purchase_histories id=67（customer_balance）。お届け先氏名「UATメール最終振込」。「振込先のご案内」は銀行名いろは銀行・支店名中央支店・口座種別普通・口座番号TEST48213081・口座名義ストライプジャパン（カ　シュウノウダイコウ・振込金額3,723円。「振込み」後にサンクスページ（/purchase/thanks）へ移動し、メールは1通だけ（27件→28件、注文番号67）。お支払方法「銀行振込」、合計¥3,723、■お振込先は案内ページと同じ内容でお振込金額¥3,723、「※ お客様専用の口座です。ご入金を確認してから発送いたします。」あり。差出人・署名は前回と同じ。purchase_bank_transfers id=16（purchase_history_id=67）の保存値ともメールが一致 | screenshots/thanks-mail/S-2-guide-final-pc.png, screenshots/thanks-mail/S-2-thanks-final-pc.png, screenshots/thanks-mail/S-2-final-pc.png |
| S-3 | OK | 対象外 | purchase_histories id=68。お届け先氏名「UATメール最終代引」。「注文を確定する」からサンクスページ（/purchase/thanks）へ移動し、メールは1通だけ（28件→29件、注文番号68）。お支払方法「代金引換」、商品小計¥1,873、送料¥1,850、代引手数料¥330、合計¥4,053（購入ページの表示と同額）。■お振込先なし。差出人・署名は前回と同じ | screenshots/thanks-mail/S-3-purchase-final-pc.png, screenshots/thanks-mail/S-3-thanks-final-pc.png, screenshots/thanks-mail/S-3-final-pc.png |
| S-5 | OK | 対象外 | reservations id=29。お届け先氏名「UATメール最終予約カード」、カードで予約し4242で支払い。サンクスページ（/reservation/success?session_id=…のまま「ご予約ありがとうございます」が表示される。これまでと同じ挙動）。メールは1通だけ（29件→30件）。件名「【しおや農園】ご予約ありがとうございます（予約番号：29）」、差出人「しおや農園 <shioyafarm@example.com>」。■ご予約内容に予約番号・ご予約日時（2026年10月24日 00:18）・お支払方法「クレジットカード」、北の恵みセット 10kg × 1 ¥1,450、商品小計¥1,450、送料¥1,530、合計¥2,980。代引手数料の行と■お振込先はない。「商品は9月より、準備が整い次第順次発送いたします。」が■ご予約内容の後にある。お届け先「UATメール最終予約カード 様」。署名「しおや農園 / shioyafarm@example.com」 | screenshots/thanks-mail/S-5-thanks-final-pc.png, screenshots/thanks-mail/S-5-final-pc.png |
| S-13 | OK | 対象外 | `docker compose stop mail` でMailpitを止めて、代金引換（「UATメール最終失敗」）で注文。ボタンを押すとすぐサンクスページ（/purchase/thanks）が表示された。purchase_histories id=69（cod、total_price=1600）が保存された。`storage/logs/laravel.log` に「[2026-10-24 00:19:46] local.ERROR: サンクスメールの送信に失敗しました {"saved":"App\\Models\\Purchase_history","id":69,"exception":"Symfony\\Component\\Mailer\\Exception\\TransportException","message":"Connection could not be established with host \"mail:1025\": …getaddrinfo for mail failed…"}」が記録された。確認後 `docker compose start mail` で起動し直し（healthy）、既存のメールが残っていることも確認（30件のまま） | screenshots/thanks-mail/S-13-final-pc.png |

- コンソールエラー：なし（エラー0件。警告はStripeのCheckout画面の「<link rel=preload> uses an unsupported `as` value」のみで、Stripe側の画面のもの）
- メール（Mailpit）：最終確認で届いたのは購入の確認メール3通（注文番号66〜68）と予約の確認メール1通（予約番号29）。すべて宛先は uat-buy@example.com、差出人は「しおや農園 <shioyafarm@example.com>」、署名は「しおや農園 / shioyafarm@example.com」。各シナリオで1通ずつで、重複なし。注文番号69は送信失敗のため0通。総件数は26件→30件。Mailpitは `docker compose start mail` で起動済みに戻した
- 追加したデータ（最終確認分）：
  - purchase_histories に4件（id=66 UATメール最終カード（card）、67 UATメール最終振込（customer_balance）、68 UATメール最終代引（cod）、69 UATメール最終失敗（cod、メール送信失敗）。すべて uat-buy@example.com の会員）、purchase_history_items に10件（66〜68は各3行、69は1行）、purchase_bank_transfers に1件（id=16：purchase_history_id=67）
  - reservations に1件（id=29 UATメール最終予約カード（card））
  - Stripeのテスト環境：Checkout Session（カード2件）と、銀行振込のPaymentIntent（購入1件）ができた。顧客残高の入金はしていない

## ユーザー確認チェックリスト

- [x] 購入（カード・銀行振込・代金引換）と予約（カード・銀行振込）を1回ずつ行い、Mailpit（http://localhost:8025）にメールが届く
- [x] 本文のあいさつ・見出し・案内の文言が自然で、高齢の方にも読みやすい
- [x] 銀行振込のメールの振込先が見つけやすく、振込先のご案内ページと同じ内容になっている
- [x] HTML版の見た目（ヘッダー・色・余白）がしおや農園らしい
- [x] スマートフォンの幅で見ても読みやすい
- [x] 件名から、何のメールかがすぐわかる

## 判定

- 受け入れ：済
- コメント：チェック項目をすべてクリアしているので受け入れ済とします
