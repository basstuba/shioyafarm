# UAT：見た目と操作の細部の調整

| 項目 | 内容 |
|---|---|
| 対象ルート | 公開側の全ページのヘッダー、GET /items/{item}（shop.items.show）、GET /cart（shop.cart）、GET /purchase（shop.purchase）、GET /reservation/{item}（予約ページ）、GET /mypage（マイページ）、GET /admin/login（admin.login） |
| ブランチ | fix/polish |
| 根拠 | 最終調整に回していた項目（SPヘッダーのロゴのはみ出し、購入ページの数量のエラー表示、Livewire起動前の操作、管理者ログインの二重送信）。2026-11-13 ユーザー決定 |
| 実施日 | 2026-11-13 |

管理者ログイン（AC-6・7）は管理画面の項目だが、同じブランチで直すためこのファイルにまとめる。

## 受け入れ条件

| ID | 条件 |
|---|---|
| AC-1 | SP・PCとも、公開側のヘッダー（高さ70px）の中にロゴと「しおや農園」の文字が収まり、はみ出さない（PCは2026-11-13のUAT後にユーザー指示で追加。修正前は文字の下端が79px）。文字の大きさは変えない |
| AC-2 | 購入ページで数量が範囲外（0・11など）のエラーになったとき、その商品の数量の入力欄が赤枠になり、aria-invalid="true" が付く。エラーが直れば赤枠と aria-invalid は消える（予約ページと同じ表示） |
| AC-3 | Livewire が起動する前は、Livewire で動くボタン・入力欄（カートに入れる、カートの数量・削除、購入・予約ページの数量、新しい住所を追加、注文履歴の詳細・ページ送り）が押せない（disabled）状態で表示される |
| AC-4 | Livewire が起動したら、AC-3 のボタン・入力欄は押せるようになり、これまでどおり動く。もともと押せない条件のもの（ページ送りの端、追加処理中のボタンなど）はその条件どおり押せないまま |
| AC-5 | AC-3 の対応でレイアウトがずれない（ボタンが隠れたり後から現れたりしない） |
| AC-6 | 管理者ログインで「ログイン」ボタンを続けて押しても（二重送信）、送信は1回だけで、二段階認証のコードのメールは1通だけ届く |
| AC-7 | 管理者ログインで送信した後、ブラウザの「戻る」でログイン画面に戻った場合や、入力エラーでログイン画面が表示し直された場合は、「ログイン」ボタンを押せる |
| AC-8 | 予約限定商品の詳細ページのヘッダーのカートアイコンは、これまでどおり表示する（変更なし） |
| AC-9 | Livewire の起動前に購入・予約の確定ボタンを押しても、数量が送信されて、これまでどおり注文・予約へ進む（数量の欄は起動前は変更できないが、値は送られる）。/code-review の指摘で追加 |
| AC-10 | 管理者ログインで送信した後、通信が止まるなどでページが移らなかった場合も、一定時間（10秒程度）たつと「ログイン」をまた押せる。/code-review の指摘で追加 |
| AC-11 | Livewire の起動前は、購入・予約ページの支払方法のラジオボタンとお届け先住所の選択も変更できない（見た目でわかる）。起動前に確定ボタンを押しても、表示されている支払方法・住所が送信される。起動後は選べて、手数料・送料が計算し直される。2回目の /code-review の指摘で追加 |
| AC-12 | Livewire の起動前は、購入・予約ページのお届け先氏名・電話番号の欄に入力できない（見た目でわかる）。起動前に確定ボタンを押しても、表示されている値が送信される。起動後は入力でき、入力した文字が元に戻らない。2回目の /code-review の修正中の気づきで追加 |

## シナリオ

| ID | 対応AC | 画面幅 | 前提 | 手順 | 期待結果 |
|---|---|---|---|---|---|
| S-1 | AC-1 | SP | なし | トップ・商品一覧・商品詳細・カート・マイページの各ページ（ログイン画面は高さ固定のヘッダーがない別の作りのため対象外）を開き、ヘッダーを見る | ロゴと「しおや農園」の文字がヘッダーの70pxの中に収まる（ロゴと文字の下端がヘッダーの下端より上）。横スクロールなし |
| S-2 | AC-1 | PC | なし | トップ・商品一覧・商品詳細・カート・マイページの各ページ（ログイン画面は高さ固定のヘッダーがない別の作りのため対象外）を開き、ヘッダーを見る | ロゴと「しおや農園」の文字がヘッダーの70pxの中に収まる（文字の下端がヘッダーの下端より上）。ナビの項目の位置は変わらず、ロゴが窮屈に見えない |
| S-3 | AC-2 | PC/SP | 会員でログインし、カートに商品が2件ある | /purchase で1件目の数量を0にして「注文を確定する」を押す | 1件目の数量の欄が赤枠になり、aria-invalid="true" が付き、エラー文が出る。2件目の欄は通常の枠のまま |
| S-4 | AC-2 | PC | S-3 の続き | 1件目の数量の欄を2に変える（注文は確定しない） | エラー文が消え、赤枠と aria-invalid も消える |
| S-5 | AC-3, AC-5 | SP | Livewire のスクリプトの読み込みを遅らせる（Playwright でスクリプトへのリクエストを数秒止める） | 商品詳細（通常商品）・カート・購入ページ・マイページを開き、読み込み中のボタン・入力欄を見る | AC-3 のボタン・入力欄が disabled になっている。ボタンは見えていて、配置は起動後と同じ |
| S-6 | AC-4 | PC/SP | S-5 の続き（スクリプトの読み込みを再開する） | Livewire の起動後に「カートに入れる」、カートの数量変更・削除、購入ページの数量変更、マイページの「新しい住所を追加」を押す | どれも押せて、これまでどおり動く（モーダルが出る・金額が変わる・削除される） |
| S-7 | AC-4 | PC | 注文履歴が2ページ以上ある会員でログイン | マイページの注文履歴を開く | 1ページ目では「前へ」が押せないまま、「次へ」は押せる。予約ページの数量・注文履歴の「詳細」も押せる |
| S-8 | AC-6 | PC | 二段階認証がオンで、Mailpit を空にしておく | /admin/login で正しいメールアドレス・パスワードを入れ、「ログイン」ボタンを素早く2回押す（ダブルクリック） | コード入力画面に移り、Mailpit に届いたコードのメールは1通だけ |
| S-9 | AC-7 | PC | なし | /admin/login で間違ったパスワードを入れて「ログイン」を押す | エラーが表示され、もう一度「ログイン」を押せる |
| S-10 | AC-7 | PC | S-8 の続き | コード入力画面からブラウザの「戻る」でログイン画面に戻る | 「ログイン」ボタンが押せる状態になっている |
| S-11 | AC-8 | PC/SP | 予約限定商品がある | 予約限定商品の詳細ページを開く | ヘッダーにカートアイコンが表示される（変更なし） |
| S-12 | AC-9 | SP | S-5 と同じく Livewire のスクリプトの読み込みを遅らせる。会員でログインし、カートに商品が1件（数量2）ある。支払方法は代金引換 | Livewire の起動前に /purchase の「注文を確定する」を押す（予約ページも同様に、起動前に「予約を確定する」を押す。支払方法は銀行振込でStripeの画面に移ればよい） | 数量の欄は起動前に変更できない。数量の抜けによるエラーにならず、購入は数量2で注文される（予約は入力した数量でStripeの画面へ進む） |
| S-13 | AC-10 | PC | なし | /admin/login で、POST /admin/login への応答を Playwright の route で止めたまま「ログイン」を押し、10秒以上待つ | 送信直後は「ログイン」が押せない。一定時間（10秒程度）たつと、また押せるようになる |
| S-14 | AC-11 | SP | S-5 と同じく Livewire のスクリプトの読み込みを遅らせる。会員でログインし、カートに商品がある。住所が2件以上ある | 起動前に /purchase で支払方法を代金引換に、お届け先住所を別の住所に変えようとする。起動後に同じ操作をする。予約ページでも支払方法で同じ操作をする | 起動前は変えられず、見た目で押せないとわかる。起動後は選べて、代金引換で手数料330円が足され、住所を変えると送料が変わる |
| S-15 | AC-12 | SP | S-5 と同じく Livewire のスクリプトの読み込みを遅らせる。会員でログインし、カートに商品がある | 起動前に /purchase のお届け先氏名・電話番号に文字を打とうとする。起動後に氏名を「UAT起動後太郎」に変え、支払方法を切り替える（更新リクエストを起こす） | 起動前は入力できない。起動後は入力でき、支払方法を切り替えた後も「UAT起動後太郎」のまま残る |

## 実施結果

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | - | OK | トップ・商品一覧・商品詳細・カート・ログインの5ページで、ヘッダーは高さ70px。ロゴ画像は y=1〜49、「しおや農園」の文字は y=49〜65（ともにヘッダーの下端70pxより上）。リンク全体も y=1〜65。横スクロールなし（scrollWidth がビューポート幅以下） | screenshots/polish/S-1-sp.png（トップ）、S-1-items-sp.png、S-1-detail-sp.png、S-1-cart-sp.png、S-1-login-sp.png |
| S-2 | OK | - | トップ・商品一覧で、ロゴ画像は x=21〜83・y=1〜63、「しおや農園」の文字は y=63〜79（PCは修正前から文字の下端が70pxを少し超えている。今回は「PCは変えない」条件なので事実として記録）。既存の header-nav/S-14-items-login-pc.png とロゴ部分（左上200×80px）をピクセル比較して差分なし（完全一致）。ナビの項目は header-nav 実施後に変わっているため、比較はロゴ部分のみ | screenshots/polish/S-2-pc.png、S-2-top-pc.png、S-2-items-pc.png |
| S-3 | OK | OK | uat-buy@example.com でログイン、カートに じゃがいも2kg・玉ねぎ2kg。/purchase で1件目の数量を0にして「注文を確定する」を押すと、1件目の欄が border-danger（赤枠）・aria-invalid="true"・エラー文「商品の数は1個以上にしてください」。2件目は border-line（通常の枠）で aria-invalid なし（PC・SPとも同じ） | screenshots/polish/S-3-pc.png、S-3-sp.png |
| S-4 | OK | - | 続けて1件目を2に変えると（注文は確定せず）、エラー文が消え、赤枠（border-line に戻る）と aria-invalid も消えた | screenshots/polish/S-4-pc.png |
| S-5 | - | OK | Playwright の route で /livewire/livewire.js を5秒遅らせ、DOM が出た時点（起動前、livewire は未定義）で確認。商品詳細（「カートに入れる」1件）・カート（数量・削除の4件）・購入ページ（数量2件＋「新しい住所を追加」の3件）・マイページ（「新しい住所を追加」・住所/購入/予約の各ページ送り・「詳細」で27件）・予約ページ（2件）が、見えている要素はすべて disabled。ボタンは見えていて薄く表示される。起動後と比べて位置・大きさのずれは0件（マイページ・購入ページは、同じ wire:click ごとに x,y,幅,高さを比較）。購入・マイページのモーダル内の「住所を追加」は、非表示のモーダルの中にあるため起動前も disabled ではないが、押せない（表示されない）。route は解除済み | screenshots/polish/S-5-sp.png（商品詳細）、S-5-cart-sp.png、S-5-purchase-sp.png、S-5-mypage-sp.png、S-5-reservation-sp.png |
| S-6 | OK | OK | 起動後、PC・SPとも次のとおりこれまでどおり動いた。「カートに入れる」→「カートに追加しました！」。カートの数量を3にすると金額が 990円 に、小計が変わる。「削除」で玉ねぎの行が消え小計 990円。購入ページで数量を4にすると 小計 990円→1,320円、合計 2,520円→2,850円（送料 1,530円）。マイページの「＋新しい住所を追加」でモーダル「新しい住所を追加」が開く | screenshots/polish/S-6-pc.png、S-6-sp.png（カートに入れる）、S-6-cart-pc.png、S-6-cart-sp.png、S-6-purchase-pc.png、S-6-purchase-sp.png、S-6-mypage-pc.png、S-6-mypage-sp.png |
| S-7 | OK | OK | uat-buy@example.com（購入履歴80件・16ページ）。1ページ目は住所・購入・予約のどれも「前のページ」が disabled、「次のページ」は押せる。購入履歴の「次のページ」を押すと2ページ目に移り、「前のページ」が押せるようになった（住所・予約のほうは動かない）。最終ページ（16）では「次のページ」が disabled、「前のページ」は押せる。「詳細」は押せてモーダル「購入の詳細」が開く。予約ページ（/reservation/11）の数量入力も押せる | screenshots/polish/S-7-pc.png、S-7-sp.png、S-7-reservation-pc.png、S-7-reservation-sp.png |
| S-8 | OK | - | Mailpit を空にし、admin@admin.com / admin1234 で「ログイン」を dblclick。POST /admin/login は1回、/admin/two-factor へ移動、Mailpit に届いたメールは1通（件名「【しおや農園】管理画面のログイン確認コード」、宛先 admin@admin.com）。追加で、同じタイミングに click() を2回呼ぶ操作（JS）でも、POST 1回・メール1通 | screenshots/polish/S-8-pc.png（送信前は S-8-before-pc.png） |
| S-9 | OK | - | 間違ったパスワードで送信すると /admin/login に戻り「メールアドレスまたはパスワードが正しくありません。」。「ログイン」は disabled でなく、続けて2回目の誤送信もできた（POST 2回、メール0通） | screenshots/polish/S-9-pc.png |
| S-10 | OK | - | S-8 の続きで /admin/two-factor から page.goBack で /admin/login に戻ると、「ログイン」は disabled でなく（テキストも「ログイン」のまま）、入力し直して押すと /admin/two-factor へ移動しメール1通 | screenshots/polish/S-10-pc.png |
| S-11 | OK | OK | 予約限定商品（北の恵みセット5kg、/items/10）の詳細で、ヘッダーに「カート」（/cart）のリンクが表示される（PCは「カート」の文字つき、SPはアイコン）。通常商品（/items/1）と同じ。「予約する」ボタンが表示される | screenshots/polish/S-11-pc.png、S-11-sp.png |

- コンソールエラー：なし（全シナリオで error 0件・warning 0件）
- メール（Mailpit）：S-8・S-9・S-10 で確認。S-8 は1通のみ。実施前に Mailpit を API（DELETE /api/v1/messages）で空にした
- 判定保留・未実施：なし。SPの「-」はシナリオの画面幅がPCまたはSPのみのため
- 気になった点：なし（NGなし）
- 追加したデータ：なし（既存の uat-buy@example.com（users.id=3）と admin@admin.com を使用。カートはセッション内のため DB への追加はなく、購入の確定・住所の追加もしていない）。実施中に admin_two_factor_codes に admin@admin.com のコードが数件でき、Mailpit のメールは実施のたびに空にした

### 再実施（2026-11-13、PCのロゴも48pxに変更後）

S-1・S-2 を、書き換え後の AC-1 とシナリオ（対象はトップ・商品一覧・商品詳細・カート・マイページ。ログイン画面は対象外）でやり直した。uat-buy@example.com でログインし、カートにはじゃがいも2kgを1件入れた状態で、各ページを開いて測った（ログイン状態のため、ナビはページによって「マイページ」「ログアウト」「カート」の出方が違う）。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | - | OK | 5ページとも、ヘッダーの高さは70px。ロゴ画像は y=1〜49（x=16〜64、幅48px）、「しおや農園」の文字は y=49〜65、リンク全体は y=1〜65で、どれもヘッダーの下端70pxより上（下端の余り5px）。横スクロールなし（5ページとも scrollWidth＝clientWidth。SPのトップ・商品一覧・商品詳細・マイページは縦スクロールバーが出て399px、カートは414px）。ナビ（カート・マイページ・メニュー）は y=12.5〜53.5 でロゴと重ならない。見た目も窮屈ではない | screenshots/polish/S-1-recheck-sp.png（トップ）、S-1-recheck-items-sp.png、S-1-recheck-detail-sp.png、S-1-recheck-cart-sp.png、S-1-recheck-mypage-sp.png |
| S-2 | OK | - | 5ページとも、ヘッダーの高さは70px。ロゴ画像は y=1〜49（x=28〜76、幅48px）、「しおや農園」の文字は y=49〜65、リンク全体は y=1〜65で、どれもヘッダーの下端70pxより上（修正前の文字の下端79pxから改善）。横スクロールなし（scrollWidth＝clientWidth。縦スクロールバーがあるページは1265px、カートは1280px）。ナビの項目の x 位置は前回のスクリーンショット（S-2-pc.png、ログアウト状態のトップ）と同じ（お知らせ x=747・しおや農園について 841・オンラインショップ 1005・マイページ 1169〜1261。ナビ文字のピクセルの横の範囲 771〜1258 も完全に同じ）。縦は、前回はヘッダーの帯が80pxだったため文字の縦位置が y=35〜46 だったが、今回はヘッダー70pxの中央になり y=28〜39 に7px上がった（ロゴが小さくなりヘッダーの高さが70pxに収まったため。ナビの順序・間隔は変わらず）。ロゴが窮屈に見えることもない | screenshots/polish/S-2-recheck-pc.png（トップ）、S-2-recheck-items-pc.png、S-2-recheck-detail-pc.png、S-2-recheck-cart-pc.png、S-2-recheck-mypage-pc.png |

- 測った値（y は画面上端から。ヘッダーは固定表示で上端が y=0）：全10通り（SP・PC × 5ページ）で、ヘッダー70px、ロゴ画像の下端49px、文字の下端65px。PCのナビの縦位置は全ページ y=23〜43（項目の枠）
- コンソールエラー：なし（error 0件・warning 0件）
- 判定保留・未実施：なし。SPの「-」・PCの「-」はシナリオの画面幅がPCまたはSPのみのため
- 追加したデータ：なし（既存の uat-buy@example.com を使用。カートはセッション内のため DB への追加なし）

### 再実施（2026-11-13、コードレビューの修正後）

/code-review の指摘で直した次の3点（購入・予約・カートの数量欄は起動前は readonly、管理者ログインの「ログイン」は送信から10秒後に押せる、x-outline-button の livewire 属性で渡された disabled が起動後も保たれる）を踏まえ、S-3〜S-10 をやり直し、S-12・S-13 を新しく実施した。S-1・S-2・S-11 は対象外。uat-buy@example.com（users.id=3）でログインし、管理者は admin@admin.com / admin1234。S-5・S-12 の「Livewire 起動前」は、`/livewire/livewire.js` へのリクエストを Playwright の route で止め、その間に DOM を調べる方法で確かめた（`window.Livewire` は undefined のままであることを確認）。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-3 | OK | OK | カートにじゃがいも2kg・玉ねぎ2kg。/purchase で1件目の数量を0にして「注文を確定する」（支払方法は代金引換）を押すと、1件目の欄が border-danger（赤枠）・aria-invalid="true"・エラー文「商品の数は1個以上にしてください」。2件目は border-line（通常の枠）で aria-invalid なし。PC・SPとも同じ。SPは横スクロールなし（399/399） | screenshots/polish/S-3-review-pc.png、S-3-review-sp.png |
| S-4 | OK | - | 続けて1件目の数量を2にして欄の外へフォーカスを移す（blur。数量は change で反映されるため。入力だけでフォーカスが欄に残っていると、エラーは消えない）と、エラー文が消え、赤枠（border-line に戻る）と aria-invalid も消えた。注文は確定していない（SPでも同じ動作を確認） | screenshots/polish/S-4-review-pc.png |
| S-5 | - | OK | 読み込み中（`window.Livewire` が undefined の間）の main 内を調べた。商品詳細（「カートに入れる」1件）・カート（「削除」2件）・購入ページ（「＋新しい住所を追加」1件）・予約ページ（「＋新しい住所を追加」1件）・マイページ（「＋新しい住所を追加」と、住所/購入/予約の各ページ送り・「詳細」の計27件のうち、1ページ目の「前のページ」3件は元から disabled）のボタンはすべて disabled。数量欄は disabled ではなく readOnly=true（カート2件・購入ページ2件・予約ページ1件）。読み込み中の購入ページの数量欄にフォーカスしてキー入力（9）しても値は変わらなかった（S-12 で確認）。ボタンは見えていて薄く表示され、数量欄は文字がグレーで表示される。起動後はこれらがすべて操作できる状態（disabled=false・readOnly=false）になり、「前のページ」の3件だけ disabled のまま。レイアウト：画像・フォントの読み込みが終わった時点で起動前と起動後の x, y, 幅, 高さを比べ、差は5ページ・計51要素で0件、ページ全体の高さも同じ（1058・970・2071・3016・1805px）。「注文を確定する」「予約を確定する」は起動前から disabled ではない（AC-9。S-12）。支払方法のラジオ・お届け先の入力欄・住所のセレクトも起動前から disabled ではない（AC-3 の対象外）。route は解除済み | screenshots/polish/S-5-detail-review-sp.png、S-5-cart-review-sp.png、S-5-purchase-review-sp.png、S-5-mypage-review-sp.png、S-5-reservation-review-sp.png |
| S-6 | OK | OK | 起動後、PC・SPとも次のとおりこれまでどおり動いた。「カートに入れる」→「カートに追加しました！」。カートの数量：PCは 7（1,320円→2,310円、小計も2,310円）、SPは 2（2,310円→660円、小計660円）。「削除」：玉ねぎの行が消え、小計が じゃがいも分のみになる（PC 1,650円、SP 990円）。購入ページの数量を4にすると、PC・SPとも 小計 →1,320円・合計 2,850円（送料 1,530円）。マイページの「＋新しい住所を追加」でモーダル「新しい住所を追加」が開く（SPは横スクロールなし） | screenshots/polish/S-6-add-review-pc.png、S-6-add-review-sp.png、S-6-cart-review-pc.png、S-6-cart-review-sp.png、S-6-purchase-review-pc.png、S-6-purchase-review-sp.png、S-6-mypage-review-pc.png、S-6-mypage-review-sp.png |
| S-7 | OK | - | uat-buy@example.com（購入履歴16ページ）。1ページ目は住所・購入・予約のどれも「前のページ」が disabled、「次のページ」は押せる。購入履歴の「次のページ」を押すと2ページ目に移り「前のページ」が押せるようになった（住所・予約のほうは動かない）。購入履歴の「詳細」10件は disabled なし、押すとモーダル「購入の詳細」が開く。予約ページ（/reservation/11）の数量欄は readOnly でなく、3に変えると合計が 7,410円 に変わった | screenshots/polish/S-7-review-pc.png、S-7-detail-review-pc.png、S-7-reservation-review-pc.png |
| S-8 | OK | - | 実施前に Mailpit を API（DELETE /api/v1/messages）で空にした。「ログイン」を dblclick。POST /admin/login は1回、/admin/two-factor へ移動、Mailpit に届いたメールは1通（件名「【しおや農園】管理画面のログイン確認コード」、宛先 admin@admin.com）。追加で、JS から click() を続けて2回呼ぶ操作でも、POST 1回・メールは1通増えただけ | screenshots/polish/S-8-before-review-pc.png、S-8-review-pc.png |
| S-9 | OK | - | 間違ったパスワードで送信すると /admin/login に戻り「メールアドレスまたはパスワードが正しくありません。」。「ログイン」は disabled でなく、続けて2回目の誤送信もできた（POST 2回、メール増えず） | screenshots/polish/S-9-review-pc.png |
| S-10 | OK | - | S-8 の続きで /admin/two-factor から page.goBack で /admin/login に戻ると、「ログイン」は disabled でなく（テキストも「ログイン」のまま） | screenshots/polish/S-10-review-pc.png |
| S-12 | - | OK | 購入：カートはじゃがいも2kg ×2。/livewire/livewire.js を止めて（`window.Livewire` は undefined）、数量欄は readOnly=true（disabled ではない）・値は2。数量欄をクリックして「9」を打っても値は2のまま。支払方法を代金引換にして「注文を確定する」を押すと /purchase/thanks（ご注文完了）へ進み、数量の抜けによるエラーにならなかった。作られた purchase_histories は id=220（user_id=3、payment_method=cod、送料1,230円＋代引手数料330円、合計2,220円）、purchase_history_items は id=359（じゃがいも2kg、数量2、小計660円）。注文確認メールも uat-buy@example.com に1通。予約：/reservation/11（北の恵みセット10kg、数量の初期値1）で、同じく起動前に数量欄は readOnly=true・値1（「9」を打っても変わらない）。支払方法を銀行振込にして「予約を確定する」を押すと /reservation/bank-transfer（振込先のご案内。数量1個、合計2,980円）へ進み、Stripeの画面（Checkout）ではなく振込先の案内ページに移った（uat-buy は Stripe の残高がなく、これまでも振込先が出る）。この画面で「戻る」を押して取り消した。reservations のレコードは増えていない。数量を変えて送る確認は、起動前は数量欄を変えられないため、初期値（予約は1、購入は2）での確認になる | screenshots/polish/S-12-purchase-before-review-sp.png、S-12-purchase-thanks-review-sp.png、S-12-reservation-before-review-sp.png、S-12-reservation-banktransfer-review-sp.png |
| S-13 | OK | - | 方法：Playwright の route で POST /admin/login の応答を止める方法は、止めている間、ページ内の操作（page.click・page.evaluate）が戻ってこなくなり確かめられなかった（下の「気になった点」）。そのため submit の遷移を止める方法で確かめた。`window` に submit イベントの listener を足して `preventDefault()` を呼び（Alpine の x-on:submit のあとに動くので、送信中の状態にはなるがページは移らない）、「ログイン」を押した。結果：0.5秒後・5.5秒後は「ログイン」が disabled、11.5秒後には disabled が外れて押せる状態に戻った。もう一度押すと submit が2回目に動き、また disabled になった | screenshots/polish/S-13-sending-review-pc.png（送信直後、押せない）、S-13-review-pc.png（11.5秒後、押せる） |

- コンソールエラー：アプリ由来のエラーなし（warning 0件）。error が1件あるのは、S-12 で `livewire.js` のリクエストを route で中止（abort）したときの「Failed to load resource: net::ERR_FAILED @ /livewire/livewire.js」で、こちらの操作による
- メール（Mailpit）：S-8〜S-10・S-12 で確認。S-8 は1通のみ。S-12 の購入は uat-buy@example.com に「ご注文ありがとうございます（注文番号：220）」が1通。実施前に Mailpit を API で空にした（E2E で溜まっていたメールも消えている）
- 判定保留・未実施：なし。SPの「-」・PCの「-」はシナリオの画面幅がPCまたはSPのみのため
- 気になった点：
  - S-4：数量欄は change（フォーカスが外れたとき）で反映される。fill だけ（入力中でフォーカスが欄に残っている状態）ではエラー表示は変わらない。人が手で入力して欄の外を押す・Tab を押す使い方では問題ない。仕様どおりと思われるが記録しておく
  - S-13：route で POST の応答を止めたまま `page.click()` や `page.evaluate()` を呼ぶと戻ってこなくなる（Playwright 側の挙動と思われ、アプリの問題ではない）。route を外す（unroute）と、止めていた POST は送信されてしまい、その分ログインが成功してコードのメールが送られた（実施中に admin@admin.com 宛のコードのメールが2通増えた。これも「追加したデータ」に含む）
  - 起動前の購入ページ・予約ページでは、数量欄はグレーの文字で表示される（readonly の見た目）。確定ボタンは起動前から押せる（AC-9 のとおり）
- 追加したデータ：purchase_histories に1件（id=220。uat-buy@example.com、じゃがいも2kg ×2、代金引換、合計2,220円）、purchase_history_items に1件（id=359）。予約は reservations に追加なし（振込先の案内ページまで進んで「戻る」で取り消し。Stripe 側に銀行振込用の PaymentIntent が作られた可能性がある）。admin_two_factor_codes に admin@admin.com のコードが数件増えた。カートはセッション内のため DB への追加なし

### 再実施（2026-11-13、2回目のコードレビューの修正後）

/code-review の指摘で直した次の点（支払方法のラジオとお届け先住所の select は起動前 inert＋aria-disabled="true"、お届け先氏名・電話番号は起動前 readonly、押せないときの見た目のクラスを LivewireBoot に集約）を踏まえ、S-5・S-12・S-14・S-15 をやり直した（SP 414×896）。uat-buy@example.com（users.id=3）でログイン。「Livewire 起動前」は `/livewire/livewire.js` へのリクエストを Playwright の route で止め（`goto` は waitUntil: 'commit'）、`window.Livewire` が undefined の間に調べた。起動前の操作は、座標のマウスクリック・キー入力・Tab で行った（要素の fill や check は使っていない）。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-5 | - | OK | 【購入ページ】起動前：支払方法のラジオ3つは inert=true・aria-disabled="true"（disabled ではない）で、枠（label）の透明度0.6、お届け先住所の select も inert・aria-disabled="true"・透明度0.6、氏名・電話番号は readOnly=true（disabled ではない）・透明度0.6、数量欄は readOnly=true、「＋新しい住所を追加」は disabled・透明度0.6、「注文を確定する」は disabled でない。座標クリックで未選択の支払方法（銀行振込）を押しても選択は変わらず（クレジットカードのまま）、select を押して ↓ Enter しても値は user_3 のまま（フォーカスも入らない）、氏名・電話番号にクリックして文字を打っても値は変わらない。Tab でも select・ラジオは飛ばされる。起動後：ラジオ・select は inert なし・aria-disabled なし・透明度1、氏名・電話番号・数量は readOnly=false、「＋新しい住所を追加」は disabled=false。位置・大きさ：購入ページの全要素（ラジオ3枚の枠・select・氏名・電話番号・数量・ボタン）の x, y, 幅, 高さは起動前と起動後で完全に同じ、ページ全体の高さも同じ（1897px）。見た目：起動前は全体が薄く表示され、選択中のクレジットカードの枠も薄い（前回までのボタン・数量欄の薄い表示と同じ系統）。【前回の対象】商品詳細・カート・マイページ・予約ページを同じ方法で調べた。商品詳細：「カートに入れる」は disabled・透明度0.6、起動後 disabled=false・透明度1。カート：数量欄 readOnly=true・「削除」disabled（0.6）、起動後はどちらも操作可。マイページ：「＋新しい住所を追加」と各「詳細」は disabled（0.6）、ページ送りは disabled（0.4。ページ送りは従来から disabled:opacity-40 のまま）、起動後は押せて、1ページ目の「前のページ」3件だけ disabled のまま。「退会する」は起動前から通常。予約ページ：数量欄・氏名・電話番号 readOnly、住所 select とラジオ2つ inert、「＋新しい住所を追加」disabled、「予約を確定する」は disabled でない。起動後はすべて操作可。位置のずれは商品詳細1要素・カート2要素・マイページ28要素・予約ページ8要素で0件（高さ 1058・896・3008・1805px も起動前後で同じ）。横スクロールなし（scrollWidth 399/414 でビューポート幅以下）。route は解除済み | screenshots/polish/S-5-purchase-review2-sp.png（購入・起動前）、S-5-purchase-after-review2-sp.png（購入・起動後）、S-5-purchase-fields-review2-sp.png、S-5-detail-review2-sp.png、S-5-cart-review2-sp.png、S-5-mypage-review2-sp.png、S-5-reservation-review2-sp.png |
| S-12 | - | OK | 【購入】初期の支払方法はクレジットカードのため、そのままでは代金引換の確定を起動前に作れなかった。そこで、起動後に支払方法を代金引換・お届け先住所を「東京都UAT区登録町9-9-9」に・氏名「UAT起動前確認太郎」・電話番号「09033334444」にしてから、フォームの order_token を JS で不正な値に書き換えて送り、検証エラー（「この画面は古くなっています」）で戻る方法で、表示中の値が復元された起動前の画面を作った（カートはじゃがいも2kg、数量2）。この画面（`window.Livewire` は undefined）では、代金引換が選択済み・住所 delivery_1・氏名・電話番号が復元され、数量欄 readOnly=true（値2）、ラジオ・select は inert=true、氏名は readOnly=true、金額は 小計660円・送料1,740円・代引手数料330円・合計2,730円。この状態で「注文を確定する」を押すと /purchase/thanks（ご注文完了）へ進んだ（Livewire は未起動のまま）。作られた purchase_histories id=221：delivery_name=UAT起動前確認太郎、東京都UAT区登録町9-9-9、delivery_tell=09033334444、payment_method=cod、shipping_fee=1,740円、cod_fee=330円、total_price=2,730円。purchase_history_items id=360：じゃがいも 2kg、数量2、小計660円。表示中の支払方法・住所・氏名・電話番号・数量どおりに注文された。注文確認メールが uat-buy@example.com に1通（件名「【しおや農園】ご注文ありがとうございます（注文番号：221）」）。【予約】/reservation/11（北の恵みセット10kg、数量1）で同じ方法（起動後に銀行振込・住所 delivery_1・氏名「UAT予約起動前太郎」・電話「09077778888」にして order_token を不正にし、エラーで戻った起動前の画面）。この画面は ラジオ・select inert、氏名・電話・数量 readOnly、銀行振込が選択済み。「予約を確定する」を押すと /reservation/bank-transfer（振込先のご案内）へ進み、振込金額 3,500円（小計1,450円＋送料2,050円）、数量 北の恵みセット 10kg × 1個、お届け先 UAT予約起動前太郎・09077778888・東京都UAT区登録町9-9-9 と、表示中の値が反映された。「戻る」で取り消し、/reservation/11 に戻った。reservations は増えていない（最新 id=134、2026-11-10 作成のまま）。なお、起動前は数量欄が変えられないため、数量は初期値（購入は2、予約は1）での確認 | screenshots/polish/S-12-purchase-before-review2-sp.png、S-12-purchase-thanks-review2-sp.png、S-12-reservation-before-review2-sp.png、S-12-reservation-banktransfer-review2-sp.png |
| S-14 | - | OK | 【購入】住所は6件以上あった（追加なし）。起動前：代金引換のカードを座標クリックしても選択は変わらず（クレジットカードのまま）、住所の select をクリックして ↓ Enter しても user_3（北海道札幌市UAT1-1）のまま。金額も 小計990円・送料1,530円・合計2,520円 のまま。見た目は薄い表示（透明度0.6）で押せないとわかる。起動後：代金引換を選ぶと 小計990円・送料1,530円・代引手数料330円・合計2,850円（330円が加算）、続けて住所を「東京都UAT区登録町9-9-9」に変えると 送料2,050円・代引手数料330円・合計3,370円（北海道1,530円→東京2,050円）、確認用お届け先住所も「東京都UAT区登録町9-9-9」に変わった。【予約】/reservation/11：起動前は銀行振込（customer_balance）のラジオを押しても選択は変わらず（クレジットカードのまま）、住所 select も user_3 のまま（金額 小計1,450円・送料1,530円・合計2,980円 のまま）。起動後は銀行振込を選べて、住所を東京都UAT区登録町9-9-9に変えると 送料2,050円・合計3,500円 に変わった（予約は代金引換なし） | screenshots/polish/S-14-before-purchase-review2-sp.png、S-14-after-purchase-review2-sp.png、S-14-purchase-full-review2-sp.png、S-14-before-reservation-review2-sp.png、S-14-after-reservation-review2-sp.png |
| S-15 | - | OK | 起動前：/purchase の氏名（UAT購入太郎）・電話番号（09011112222）をクリックして Ctrl+A・「起動前に入力」・Backspace を打っても値は変わらない（readOnly=true）。起動後：氏名を「UAT起動後太郎」、電話番号を「09055556666」にして、支払方法を代金引換に切り替える（更新リクエストが走り代引手数料が出る）と、氏名は「UAT起動後太郎」・電話番号は「09055556666」のまま。さらにクレジットカードへ戻しても同じ。readOnly は氏名・電話番号とも false | screenshots/polish/S-15-before-review2-sp.png、S-15-after-review2-sp.png |

- コンソールエラー：なし（error 0件・warning 0件。今回は livewire.js のリクエストを中止（abort）せず、遅らせてから通したため、前回の ERR_FAILED も出ていない）
- メール（Mailpit）：S-12 の購入（注文番号221）で uat-buy@example.com に注文確認メール1通を確認。予約は振込先の案内まで進んだだけで予約メールは送られない
- 判定保留・未実施：なし。SPの「-」・PCの「-」はシナリオの画面幅がPCまたはSPのみのため
- 気になった点：
  - S-12：起動前の確定は、初期の支払方法（クレジットカード）のままでは代金引換・銀行振込の確定を作れない。そのため、order_token を不正にして検証エラーで戻し、「支払方法・住所・氏名・電話番号が復元された起動前の画面」を作る方法を使った（アプリの操作としては通常ありえない手順。起動前に確定ボタンを押すとき、復元された値が送信されることの確認が目的）。初期の支払方法（クレジットカード）での起動前の確定（Stripe へ移る）は確認していない
  - 起動前の表示は、ウェブフォント・画像の読み込み前のため文字が通常と少し違って見えるが、位置の比較は読み込み完了後に行い、ずれは0件
  - ページ送りの薄さ（0.4）は、他のボタン・入力欄（0.6）より薄く表示される。これは今回の変更前から disabled:opacity-40 のままで、変更点ではない
- 追加したデータ：purchase_histories に1件（id=221。uat-buy@example.com、じゃがいも2kg ×2、代金引換、お届け先 UAT起動前確認太郎・09033334444・東京都UAT区登録町9-9-9、合計2,730円）、purchase_history_items に1件（id=360）。予約は reservations に追加なし（振込先の案内ページまで進んで「戻る」で取り消し。Stripe 側に銀行振込用の PaymentIntent が作られた可能性がある）。カートはセッション内のため DB への追加なし（実施中にカートの数量を3→2に変更）。住所の追加はしていない

## ユーザー確認チェックリスト

- [x] SP（実機またはブラウザのSP表示）で、ヘッダーのロゴと「しおや農園」の文字が収まって見え、窮屈に感じない
- [x] 購入ページで数量をエラーにしたとき、どの商品の欄が間違っているか赤枠でわかる
- [x] ページを開いた直後に、ボタンが一瞬押せない（薄く見える）状態が目障りではない
- [x] 管理者ログインで「ログイン」をダブルクリックしても、届くメールが1通だけ
- [x] 購入ページを開いた直後に、支払方法・住所・氏名・電話番号の欄が一瞬薄く見え、その後ふつうに入力・選択できる（違和感がない）

## 判定

- 受け入れ：済
- コメント：チェック項目をすべてクリアしているので受け入れ済とします
