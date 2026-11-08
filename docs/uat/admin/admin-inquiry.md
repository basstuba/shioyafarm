# UAT：管理画面のお問い合わせ一覧

| 項目 | 内容 |
|---|---|
| 対象ルート | GET /admin/inquiries（admin.inquiries.index）、GET /admin/inquiries/search（admin.inquiries.search）、POST /admin/inquiries/update（admin.inquiries.update） |
| ブランチ | feat/admin-inquiry |
| 根拠 | docs/03-page-list.md「管理画面お問い合わせ一覧ページ」、docs/04-feature-list.md、docs/05-table-specification.md（inquiries）、docs/07-basic-design.md、Figma お問い合わせ一覧（157:2）・問い合わせ詳細モーダル（164:3）、docs/uat/admin/admin-purchase.md・admin-user.md のユーザー決定 |
| 実施日 | 2026-11-08 |

注文一覧・予約一覧・会員一覧から引き継ぐ事項：

- レイアウト（共通のヘッダー・フッター、サイドメニュー、中央のページタイトル）、検索・リセットの色、表の文字の大きさ（本文16px・モーダルの値14px）、表の幅、ページ送り、SPのカード表示、Cache-Control: no-store。
- 「対応済」のチェックは、注文一覧の「発送済」のチェックと同じ形にする（押した瞬間に保存、24px・押せる範囲は高さ44px、チェックした行の地は #d4d4d4、保存できなかったらチェックを戻して案内）。Figmaの14pxのチェックボックスは使わない。
- 詳細モーダルの閉じ方は、注文一覧と同じく下の「閉じる」・背景の押下・Escキー。

お問い合わせ一覧で決める事項：

- 表は対応済（チェックボックス）・問い合わせ日（YYYY/MM/DD）・氏名（姓名）・「詳細」・「返信」の5列（Figmaのとおり）。最後の列「返信」は、会員一覧の「メール送信」と同じく右寄せ・青い下線の文字リンク。
- 「返信」のリンク先は /admin/mail/inquiry/{お問い合わせID}。メール作成ページは次のブランチで作るため、それまでは押すと404になる（会員一覧と同じ扱い）。
- 検索は、選択欄（対応済 / 非対応）と問い合わせ日（Figmaのとおり。氏名の検索はない）。選択欄の何も選ばない表示は、PC・SPとも「対応済 / 非対応」（11/8 ユーザー決定。SPも「すべて」にはせず、PCと同じ表示にする）。
- 詳細モーダルは問い合わせ日・氏名・メールアドレス・問い合わせ内容の4項目（Figmaの「姓」「名」は、一覧の表と同じ「氏名」の1項目にまとめる。11/8 ユーザー決定）。問い合わせ内容の改行はそのまま表示し、長いときはモーダルの中をスクロールする。
- 設計書のルート POST /admin/inquiries/update は、注文一覧の発送済みチェックと同じく `id` と `addressed` を JSON で受け取り JSON を返す。存在しないお問い合わせは404。
- 対応済みにしても、お客様にメールは送らない。
- セレクトボックスの矢印の統一（同じブランチで行う。11/8 ユーザー決定）：管理画面の検索の選択欄（注文一覧・予約一覧・会員一覧・お問い合わせ一覧。共通部品 x-admin-search-select）の矢印を、ブラウザ標準の矢印から、Figma（157:2）の矢印に揃える。下向きの三角形・色 #acacac・右端から約16px内側。日付の入力欄は変えない。
- お問い合わせのテストデータ用に InquiryFactory を作る。シーダーには入れない（公開側のお問い合わせフォームから送ったものだけが入る）。

## 受け入れ条件

| ID | 条件 |
|---|---|
| AC-1 | 未ログイン・会員としてのログインでは /admin/inquiries、/admin/inquiries/search を開けず、/admin/login へ移動する |
| AC-2 | 管理者としてログインすると、共通のヘッダー・フッター、サイドメニュー、中央のページタイトル「お問い合わせ一覧」、検索欄、表、ページ送りが表示される。サイドメニューとトップページの「お問い合わせ一覧」のリンクから開け、サイドメニューでは「お問い合わせ一覧」が太字とグレーの地で示される |
| AC-3 | 表の列は対応済（チェックボックス）・問い合わせ日（YYYY/MM/DD、日本時間）・氏名（姓名）・「詳細」・「返信」で、新しいお問い合わせから順に1ページ10件表示される |
| AC-4 | 11件以上あるときはページ送りが表示され、ページを移動できる。10件以下のときはページ送りが出ない。存在しないページ番号を開くと最終ページが表示される |
| AC-5 | 問い合わせ日を入れて検索すると、その日（日本時間）のお問い合わせだけが表示される |
| AC-6 | 選択欄で「対応済」を選ぶと対応済みだけ、「非対応」を選ぶと未対応だけが表示される。問い合わせ日と組み合わせると、両方に合うものだけが表示される。どちらも空で検索すると全件が表示される |
| AC-7 | 検索後も入力した値が検索欄に残り、ページを移動しても検索条件が保たれる。「リセット」を押すと、検索欄が空になり全件の表示に戻る |
| AC-8 | 該当するお問い合わせがないときは、表の代わりに「該当するお問い合わせはありません。」と表示され、ページ送りは出ない |
| AC-9 | 問い合わせ日に日付でない値、選択欄に不正な値を送ると、/admin/inquiries に戻って検索欄の近くにエラーが表示され、画面は壊れない |
| AC-10 | 「対応済」のチェックを入れると、ページを移動せずにその場で保存され、再読み込みしても対応済みのままである。外すと未対応に戻る。公開側のフォームから届いたお問い合わせは未対応で始まる |
| AC-11 | 保存できなかったときはチェックが元に戻り、保存できなかった旨の案内が表示される |
| AC-12 | 対応済みの行（SPはカード）は地が #d4d4d4 になり、チェックの操作と同時に切り替わる。未対応の行は白と #f9f9f9 の交互のままで、文字色は変わらない |
| AC-13 | チェックボックスは24pxで、押せる範囲は高さ44px以上である。読み上げで、どのお問い合わせのチェックかがわかる |
| AC-14 | 未ログイン・会員としてのログインでは対応済みを変更できない。存在しないお問い合わせは変更できず（404）、他のお問い合わせも変わらない。対応済みにしてもメールは送られない |
| AC-15 | 「詳細」を押すと、モーダルに問い合わせ日・氏名（姓名）・メールアドレス・問い合わせ内容の4項目が表示される。問い合わせ内容の改行はそのまま表示され、長いときはモーダルの中をスクロールできる。「閉じる」・背景の押下・Escキーで閉じる |
| AC-16 | 「返信」はすべての行に表示され、リンク先は /admin/mail/inquiry/{お問い合わせID} である |
| AC-17 | 氏名・メールアドレス・問い合わせ内容に HTML の記号が含まれていても、文字のまま表示される |
| AC-18 | SPでは検索欄が縦積みになり、表はカード形式になる。文字は16px以上（モーダルの値は14px）、ボタン・リンクは高さ44px以上で、横スクロールが出ない。詳細モーダルが画面からはみ出さない |
| AC-19 | ログアウトしたあとにブラウザの戻るボタンを押してもお問い合わせ一覧の内容が表示されず、/admin/login へ移動する |
| AC-20 | 注文一覧・予約一覧・会員一覧・お問い合わせ一覧の検索の選択欄の矢印が、Figma（157:2）と同じ下向きの三角形（#acacac）にそろっている。PC・SPとも、矢印が選択中の文字と重ならず、選択欄を押すと選択肢が開く。フォーカスの見た目はこれまでと同じ |
| AC-21 | 注文一覧・予約一覧・会員一覧の表示と動きは、矢印のほかはこれまでと変わらない。公開側のお問い合わせフォームの送信もこれまでどおり動く |

## シナリオ

| ID | 対応AC | 画面幅 | 前提 | 手順 | 期待結果 |
|---|---|---|---|---|---|
| S-1 | AC-1 | PC | 未ログイン | /admin/inquiries と /admin/inquiries/search を開く | どちらも /admin/login へ移動する |
| S-2 | AC-1, AC-14 | PC | 会員としてログイン中、管理者としては未ログイン | /admin/inquiries を開く。CSRFトークン付きで POST /admin/inquiries/update を送る | どちらも /admin/login へ移動し（JSONの送信は401またはログインへの移動）、対応済みは変わらない |
| S-3 | AC-2, AC-3 | PC | 管理者としてログイン中。お問い合わせが12件以上ある（日付・対応済みが混在） | トップページの「お問い合わせ一覧」を押す。サイドメニューの「お問い合わせ一覧」も押す | どちらからも開き、各部品が表示される。新しい順に10件、列が AC-3 のとおり。サイドメニューの「お問い合わせ一覧」が太字とグレーの地。Figma（157:2）と配置が大きく違わない |
| S-4 | AC-4 | PC/SP | S-3 と同じ | ページ送りで2ページ目へ移動し、前のページへ戻る。?page=999 を開く | 11件目以降が表示され、戻ると1ページ目。999では最終ページが表示される |
| S-5 | AC-4 | PC | 検索で10件ちょうどに絞れるデータがある | 10件になる条件で検索する | 10件表示され、ページ送りが出ない |
| S-6 | AC-5 | PC | 2026-11-07 23:59 と 2026-11-08 00:00（日本時間）のお問い合わせがある | 問い合わせ日に 2026/11/08 を入れて検索する | 2026-11-08 00:00 のお問い合わせだけが表示される |
| S-7 | AC-6 | PC/SP | 対応済み・未対応のお問い合わせがある | 「対応済」で検索、「非対応」で検索、問い合わせ日と「非対応」を組み合わせて検索、両方空で検索する | それぞれ対応済みだけ・未対応だけ・両方に合うものだけ・全件が表示される |
| S-8 | AC-7 | PC | 検索結果が11件以上になるデータがある | 検索して2ページ目へ移動し、「リセット」を押す | 2ページ目でも検索条件と入力値が残る。リセットで検索欄が空になり、/admin/inquiries の全件表示に戻る |
| S-9 | AC-8 | PC | どれにも合わない日付がある | その日付で検索する | 「該当するお問い合わせはありません。」が表示され、表とページ送りは出ない |
| S-10 | AC-9 | PC | 管理者としてログイン中 | /admin/inquiries/search?date=abc、?addressed=9 を開く | /admin/inquiries に戻り、検索欄の近くにエラーが表示される。500にならない |
| S-11 | AC-10, AC-12 | PC | 未対応のお問い合わせがある | 「対応済」のチェックを入れ、再読み込みする。次にチェックを外して再読み込みする | 入れるとその場で行の地が #d4d4d4 になり、再読み込み後もチェックと地が残る。外すと白／#f9f9f9 に戻り、再読み込み後も未対応 |
| S-12 | AC-10 | PC | 公開側のお問い合わせフォームが使える | 公開側からお問い合わせを送り、一覧を開く | 先頭に新しいお問い合わせが未対応で表示される |
| S-13 | AC-11 | PC | 未対応のお問い合わせがある | ネットワークを遮断する（またはDBで行を消す）してからチェックを入れる | チェックが元に戻り、保存できなかった旨の案内が表示される。行の地も戻る |
| S-14 | AC-13 | PC/SP | お問い合わせがある | チェックボックスの大きさと押せる範囲、アクセシビリティツリーの名前を確認する | 24px、押せる範囲は高さ44px以上。名前に、どのお問い合わせか（氏名と問い合わせ日など）が含まれる |
| S-15 | AC-14 | PC | 管理者としてログイン中 | 存在しないIDで POST /admin/inquiries/update を送る。addressed に真偽値でない値を送る | 存在しないIDは404、不正な値は422で、どのお問い合わせも変わらない。Mailpit にメールが届かない |
| S-16 | AC-15 | PC/SP | 改行を含み、長い問い合わせ内容のお問い合わせがある | 「詳細」を押す。閉じる・背景の押下・Escキーで閉じる | 問い合わせ日・氏名・メールアドレス・問い合わせ内容の4項目が表示され、改行がそのまま出る。長い内容はモーダルの中でスクロールできる。3つの方法で閉じる |
| S-17 | AC-16 | PC/SP | お問い合わせがある | 各行の「返信」の href を確認し、1つ押す | すべての行に「返信」があり、href が /admin/mail/inquiry/{ID}。押すと404（メール作成ページは次のブランチ） |
| S-18 | AC-17 | PC | 氏名・メールアドレス・内容に `<b>x</b>` や `<script>` を含むお問い合わせがある（DBに直接入れる） | 一覧と詳細を開く | 記号が文字のまま表示され、太字やスクリプトの実行にならない |
| S-19 | AC-18 | SP | お問い合わせがある | 一覧・検索・カード・詳細モーダルを確認する | 検索欄が縦積み、表はカード。文字16px以上（モーダルの値14px）、ボタン・リンクの高さ44px以上、横スクロールなし、モーダルがはみ出さない |
| S-20 | AC-19 | PC | 管理者としてログイン中 | お問い合わせ一覧を開いてからログアウトし、ブラウザの戻るを押す | 一覧の内容は表示されず、/admin/login へ移動する |
| S-21 | AC-20 | PC/SP | 管理者としてログイン中 | 注文一覧・予約一覧・会員一覧・お問い合わせ一覧の選択欄を見比べ、選んで開く | 4画面とも矢印が下向きの三角形（#acacac）で、選択中の文字と重ならない。押すと選択肢が開き、選べる。フォーカスの枠はこれまでと同じ |
| S-22 | AC-21 | PC/SP | 注文・予約・会員がある | 注文一覧・予約一覧・会員一覧で検索・ページ送り・詳細・チェックを操作する。公開側のお問い合わせフォームから送信する | これまでどおり動き、矢印のほかの見た目は変わらない。お問い合わせはサンクスページへ進み、一覧に載る |

## 実施結果

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | OK | - | 未ログインで /admin/inquiries・/admin/inquiries/search・/admin/inquiries/search?date=abc を開くと、いずれも /admin/login へ移動 | screenshots/admin-inquiry/S-1-pc.png |
| S-2 | OK | - | 会員（test@test.com）でログイン中、/admin/inquiries・/admin/inquiries/search は /admin/login へ移動。XSRF付きの POST /admin/inquiries/update（id=5, addressed=true）は 401 {"message":"Unauthenticated."}。CSRFなしは 419。対応済みの件数は6件のまま変化なし | - |
| S-3 | OK | - | トップページ（/admin）の「お問い合わせ一覧」リンクと、注文一覧のサイドメニューの「お問い合わせ一覧」の両方から /admin/inquiries を開けた。タイトル・検索欄（選択欄・問い合わせ日・検索・リセット）・表・ページ送りが表示。新しい順10件、列は対応済・問い合わせ日・氏名・詳細・返信。サイドメニューの現在地は font-weight 700・背景 #ededed・aria-current=page。表の本文16px、チェック24px、白と #f9f9f9 の交互。Figma（157:2）との照合は目視のみ（大きな違いなし）。表見出しの「詳細」「返信」の列は文字なし（見出し行が空） | screenshots/admin-inquiry/S-3-pc.png |
| S-4 | OK | OK | 39件（4ページ。最終ページ9件）。2ページ目に11〜20件目、「1ページ目」リンクで ?page=1 に戻り10件。?page=999 は最終ページ（PC8件・SP9件、データ追加前後の差）が表示され、ページ送りが残る。SPも横スクロールなし（scrollWidth=clientWidth） | screenshots/admin-inquiry/S-4-pc.png、screenshots/admin-inquiry/S-4-sp.png |
| S-5 | OK | - | 2026-11-03 で検索すると10件ちょうど、ページ送りなし | screenshots/admin-inquiry/S-5-pc.png |
| S-6 | OK | - | 2026/11/08 で検索すると 2026-11-08 00:00 の「UAT境界 当日」だけ（1件）。2026/11/07 で検索すると 23:59 の「UAT境界 前日」だけ（1件） | screenshots/admin-inquiry/S-6-pc.png |
| S-7 | OK | OK | PC：対応済→6件すべて済、非対応→未対応のみ（10件＋ページ送り）、非対応＋2026-11-05→2件（UAT未対応 花子1・2）、両方空→全件（ページ送りあり）。SPも件数・絞り込みが同じ（対応済6件・非対応＋日付2件・非対応10件・全件10件）。選択欄の空の表示はSPで「すべて」、PCで「対応済 / 非対応」 | screenshots/admin-inquiry/S-7-pc.png、screenshots/admin-inquiry/S-7-sp.png |
| S-8 | OK | - | 「非対応」で検索→2ページ目へ（URL /admin/inquiries/search?addressed=0&page=2）。選択欄は「非対応」のまま、ページ送りのリンクも addressed=0 を保持。「リセット」で /admin/inquiries（検索欄が空・全件10件）に戻った | screenshots/admin-inquiry/S-8-pc.png |
| S-9 | OK | - | 2000-01-01 で検索すると「該当するお問い合わせはありません。」が表示され、表・ページ送りなし | screenshots/admin-inquiry/S-9-pc.png |
| S-10 | OK | - | ?date=abc、?addressed=9、両方のどれも /admin/inquiries へ戻り（200）、検索欄の下に「問い合わせ日は日付で入力してください。」「対応状況は「対応済」「非対応」のどちらかを選んでください。」が表示。500にならない。表は全件表示のまま | screenshots/admin-inquiry/S-10-pc.png、screenshots/admin-inquiry/S-10-addressed-pc.png |
| S-11 | OK | OK | PC：UAT未対応 花子1 のチェックを入れると POST 200、行の地が #d4d4d4（文字色は #2e2e2e のまま）、再読み込み後も残る。外すと 200、白に戻り再読み込み後も未対応。SP：UAT未対応 花子2 のカードで同様（ラベルの左下端を押しても反応。#d4d4d4）。確認後、どちらも未対応に戻した | screenshots/admin-inquiry/S-11-pc.png、screenshots/admin-inquiry/S-11-sp.png |
| S-12 | OK | OK | 公開側 /inquiry からPC（UAT公開 フォーム）・SP（UAT公開SP 花子）で送信。どちらも /inquiry/thanks へ進み、一覧の先頭に未対応（チェックなし）で表示。Mailpit の件数は送信前後とも18件のまま（問い合わせ送信でメールは出ない） | screenshots/admin-inquiry/S-12-thanks-pc.png、screenshots/admin-inquiry/S-12-pc.png、screenshots/admin-inquiry/S-22-thanks-sp.png |
| S-13 | OK | - | 専用の行（UAT失敗 確認用）を作って確認。(1) 通信を遮断（POSTを abort）：チェックが元に戻り、「保存できませんでした。もう一度お試しください。」が表示、行の地は白に戻る。(2) DBで行を消してからチェック：POST 404、チェックが戻り「お問い合わせが見つかりません。ページを再読み込みしてください。」が表示、地は白 | screenshots/admin-inquiry/S-13-network-pc.png、screenshots/admin-inquiry/S-13-pc.png |
| S-14 | OK | OK | チェックは24×24px、押せる範囲（label）はPC 91×44px・SP 80×44px。アクセシビリティ名は「UAT境界 当日（2026/11/08）のお問い合わせを対応済みにする」の形（氏名と日付を含む）。「詳細」「返信」にも氏名入りの名前（「…のお問い合わせの詳細を見る」「…に返信」） | - |
| S-15 | OK | - | 管理者としてXSRF付きで POST。id=999999 → 404（「お問い合わせが見つかりません。」）。addressed が 'abc'・'yes'・[1] → 422、addressed なし → 422、id なし → 422、id='x' → 422。対応済みの件数（6件）・id=25 の状態は変化なし。Mailpit は18件のまま |  |
| S-16 | OK | OK | 問い合わせ日・氏名・メールアドレス・問い合わせ内容の4項目。改行はそのまま表示（40行の内容。内容欄が内部スクロールし、scrollTop=300が効く。PCは320/1600px、SPは358/1600px）。「閉じる」・背景の押下・Escの3通りで閉じる（PC・SP）。SPはモーダル 367×730px で画面内（はみ出しなし）。項目名16px・値14px、「閉じる」はSP 81×44px | screenshots/admin-inquiry/S-16-pc.png、screenshots/admin-inquiry/S-16-sp.png |
| S-17 | OK | OK | 一覧10行すべてに「返信」があり、href は /admin/mail/inquiry/{ID}（PC・SPとも一致。先頭は新規の ID 40）。押すとPCで /admin/mail/inquiry/9 が404、SPで /admin/mail/inquiry/21 が404（想定どおり） | screenshots/admin-inquiry/S-17-pc.png、screenshots/admin-inquiry/S-17-sp.png |
| S-18 | OK | OK | 氏名「<b>UAT太字</b> <i>x</i>」、メール「<b>x</b>@ex.com」、内容「<b>x</b> <script>alert(1)</script> & "q"」が、一覧・詳細（PC・SP）とも文字のまま表示。b/i/script 要素は作られず、alert ダイアログも出ない。既存の「UAT スクリプト」（内容 <script>alert(1)</script>）の詳細も文字のまま | screenshots/admin-inquiry/S-18-pc.png、screenshots/admin-inquiry/S-18-sp.png |
| S-19 | - | OK | 検索欄は縦積み（選択欄・日付 367×48px、検索・リセット 176×48px）、表はカード（thead 非表示）。カード内の文字はすべて16px、「詳細」66×44px・「返信」64×44px・チェック24px（押せる範囲 80×44px）。モーダルの値14px。横スクロールなし。対応済みのカードは #d4d4d4、未対応のカードは全部白（SPでは白と #f9f9f9 の交互にならない） | screenshots/admin-inquiry/S-19-sp.png |
| S-20 | OK | - | 一覧を開いてログアウトすると /admin/login へ。ブラウザの戻るで /admin/login のまま、一覧の内容は表示されない。一覧の応答ヘッダーは Cache-Control: max-age=0, must-revalidate, no-cache, no-store, private | screenshots/admin-inquiry/S-20-pc.png |
| S-21 | OK | OK | 注文・予約・会員・お問い合わせの4画面とも、select は appearance:none、矢印は 12×8px の下向き三角形（clip-path）・色 rgb(172,172,172)＝#acacac・select の右端から16px内側・縦中央（ずれ0px）。右余白 40px で選択中の文字と重ならない（PC「発送状況」「会員 / 退会済」「対応済 / 非対応」、SP「すべて」）。矢印の位置を押しても select が受け取る（elementFromPoint が SELECT、フォーカスされる）。選択して検索できる（PC・SP）。フォーカスは 2px solid #2e2e2e で、日付欄と同じ。ブラウザの選択肢リストはヘッドレスのため画像では確認できず（キー操作・selectOption で選択肢が選べることを確認）。以前の見た目との差分は比較できていない | screenshots/admin-inquiry/S-21-purchase-pc.png、screenshots/admin-inquiry/S-21-reservations-pc.png、screenshots/admin-inquiry/S-21-users-pc.png、screenshots/admin-inquiry/S-21-inquiries-pc.png、screenshots/admin-inquiry/S-21-zoom-inquiries-pc.png、screenshots/admin-inquiry/S-21-purchase-sp.png、screenshots/admin-inquiry/S-21-reservations-sp.png、screenshots/admin-inquiry/S-21-users-sp.png、screenshots/admin-inquiry/S-21-inquiries-sp.png |
| S-22 | OK | OK | PC・SPとも、注文一覧（発送済で検索8件）・予約一覧（未発送で検索10件→2ページ目）・会員一覧（退会済で検索10件→2ページ目）で、検索・選択値の保持・ページ送り・「詳細」モーダルの開閉（Esc）が動いた。チェック：注文一覧（PC）・予約一覧（PC）・注文一覧（SP）で発送済みの切替が POST 200・地の色が #d4d4d4 に変わり、元に戻した（注文一覧PCは測った行が取消済の行でずれたため、地の色はSPの確認で代替）。公開側のお問い合わせフォームはサンクスページへ進み、一覧に載る。矢印以外の見た目の差は、以前の画面と並べては見ていない | screenshots/admin-inquiry/S-22-purchase-detail-pc.png、screenshots/admin-inquiry/S-22-reservations-detail-pc.png、screenshots/admin-inquiry/S-22-users-detail-pc.png、screenshots/admin-inquiry/S-22-purchase-detail-sp.png、screenshots/admin-inquiry/S-22-reservations-detail-sp.png、screenshots/admin-inquiry/S-22-users-detail-sp.png |

- コンソールエラー：画面の不具合に由来するものはなし。出たものはすべて意図した操作の結果（S-13 の通信遮断・DB行削除後の保存失敗、S-15 の404/422/419の確認、S-17 の返信の404）。
- メール（Mailpit）：対応済みの切替、S-15 の不正リクエスト、公開フォームの送信で、メールは届かない（件数18のまま）。
- 追加したデータ：inquiries に ID 20〜38 の19件と、公開フォームから送信した ID 40（UAT公開 フォーム）・ID 41（UAT公開SP 花子）。
  - ID 20「UAT境界 前日」（2026-11-07 23:59:00）、ID 21「UAT境界 当日」（2026-11-08 00:00:00）
  - ID 22〜24「UAT対応済 太郎1〜3」（2026-11-05 10:01〜10:03、対応済み）、ID 25・26「UAT未対応 花子1・2」（2026-11-05 12:01・12:02）
  - ID 27「UAT長文 一郎」（2026-11-06 09:00、40行の内容）、ID 28「<b>UAT太字</b> <i>x</i>」（2026-11-04 09:00、HTML記号入り）
  - ID 29〜38「UAT十件 次郎1〜10」（2026-11-03 01:00〜10:00、3・6・9人目が対応済み）
  - ID 39「UAT失敗 確認用」は S-13 のために作り、DBで削除した（現在は存在しない）
  - 既存の UAT お問い合わせ（ID 5〜19）はそのまま使用。メールアドレスはすべて uat-inq-*@example.com
  - 注文一覧・予約一覧のチェックは切り替えて元に戻した（データは変化なし）

### 再UAT（レビュー修正後・2026-11-08）

変更箇所（即時保存の共通部品化、aria-label の変更、対応済み更新の行ロック、日付検索の共通ヘルパー）の再確認。前回の結果は上の表のまま。

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-6 | OK | - | 日付2026-11-08で検索→日本時間 00:00 の「UAT境界 当日」を含む11/08の4件（当日に追加した公開フォーム分などを含む）、11/07で検索→23:59の「UAT境界 前日」の1件だけ。境界は前回と同じ | screenshots/admin-inquiry/S-6-recheck-pc.png |
| S-10 | OK | - | date=abc、addressed=9、date=2026-02-30（実在しない日付）、date=abc&addressed=9 のどれも /admin/inquiries へ戻り（200）、検索欄の下にエラー（「問い合わせ日は日付で入力してください。」「対応状況は「対応済」「非対応」のどちらかを選んでください。」）。500にならない | screenshots/admin-inquiry/S-10-recheck-pc.png |
| S-11 | OK | OK | PC：UAT未対応 花子1 を入れる→POST 200、地 #d4d4d4・文字色 #2e2e2e、再読み込み後も残る。外す→200、地は元の #f9f9f9 に戻り、再読み込み後も未対応。SPはS-19の確認で花子2を同様に確認（下記）。確認後、未対応に戻した | screenshots/admin-inquiry/S-11-recheck-pc.png |
| S-13 | OK | OK | PC・SP：通信を遮断してチェック→チェックが戻り、地も元に戻り、「保存できませんでした。もう一度お試しください。」。PCは行（UAT再失敗 確認用）をDBで削除してからチェック→404、チェックが戻り「お問い合わせが見つかりません。ページを再読み込みしてください。」 | screenshots/admin-inquiry/S-13-network-recheck-pc.png、screenshots/admin-inquiry/S-13-recheck-pc.png、screenshots/admin-inquiry/S-13-network-recheck-sp.png |
| S-14 | OK | OK | 新しい aria-label は「UAT未対応 花子1（2026/11/05）のお問い合わせの対応済み」の形で、チェックの状態（入れる前後）が変わっても同じ。チェック24×24px、押せる範囲 PC 91×44px・SP 80×44px | - |
| S-15 | OK | - | 存在しないID（999999）と削除済みID（42）→404。addressed が 'abc'・'yes'・[1]、addressed なし、id なし、id='x' → 422。対応済みの件数（6件）・ID 25 は変化なし。Mailpit は18件のまま | - |
| S-19（チェック部分） | - | OK | SPのカード：花子2の押せる範囲（80×44px）の左下端を押す→200、カードの地 #d4d4d4・文字色 #2e2e2e、再読み込み後も残る。外す→200、白に戻る。横スクロールなし（399/399）。通信遮断で戻り、案内が出る。確認後、未対応に戻した | screenshots/admin-inquiry/S-19-recheck-sp.png |
| S-22 拡張：注文一覧（発送済みチェック） | OK | OK | 注文210：入れる→POST /admin/purchase/update 200、地 #d4d4d4、再読み込み後も残る。外す→200、元の地（PC #f9f9f9、SP 白）に戻り再読み込み後も未発送。aria-label は「注文210の発送済み」（状態によらず同じ）。押せる範囲 PC 72×44px・SP 80×44px、チェック24px。通信遮断：チェックが戻り「保存できませんでした。もう一度お試しください。」。別画面で取消された注文（215をDBで取消にしてから押す）：409、チェックが戻り「取り消されているため、発送状況を変更できません。ページを再読み込みしてください。」。取消済みの行：チェックなし、文字 #6c6c6c（灰色）、地は未発送の行と同じ（白と #f9f9f9 の交互）、PC・SPとも。確認後、すべて元に戻した | screenshots/admin-inquiry/S-22-recheck-purchase-pc.png、screenshots/admin-inquiry/S-22-recheck-purchase-on-pc.png、screenshots/admin-inquiry/S-22-recheck-purchase-network-pc.png、screenshots/admin-inquiry/S-22-recheck-purchase-409-pc.png、screenshots/admin-inquiry/S-22-recheck-purchase-sp.png、screenshots/admin-inquiry/S-22-recheck-purchase-on-sp.png、screenshots/admin-inquiry/S-22-recheck-purchase-network-sp.png |
| S-22 拡張：予約一覧（発送済みチェック） | OK | OK | 予約124で上と同じ確認。aria-label は「予約124の発送済み」。POST /admin/reservations/update 200、地 #d4d4d4、再読み込み後も保持、外すと戻る。通信遮断で巻き戻しと案内。別画面で取消された予約（128をDBで取消にしてから押す）は409で同じ案内。取消済みの行（121・117・116）：チェックなし、灰色文字 #6c6c6c、地は未発送と同じ。PC・SP | screenshots/admin-inquiry/S-22-recheck-reservations-pc.png、screenshots/admin-inquiry/S-22-recheck-reservations-on-pc.png、screenshots/admin-inquiry/S-22-recheck-reservations-network-pc.png、screenshots/admin-inquiry/S-22-recheck-reservations-409-pc.png、screenshots/admin-inquiry/S-22-recheck-reservations-sp.png、screenshots/admin-inquiry/S-22-recheck-reservations-on-sp.png、screenshots/admin-inquiry/S-22-recheck-reservations-network-sp.png |
| S-22 拡張：注文・予約の日付検索 | OK | OK | 日本時間の境界：注文215（2026-01-14 23:59:00）・216（2026-01-15 00:00:00）、予約128（同23:59）・129（同00:00）を追加。2026-01-15で検索→PC・SPとも216／129の1件だけ、2026-01-14で検索（PC）→215／128の1件だけ。不正な日付：date=abc・date=2026-02-30 → 一覧に戻り（200）「注文日は日付で入力してください。」「予約日は日付で入力してください。」、shipped=9 → 「発送状況は「未発送」「発送済」「取消済」のどれかを選んでください。」。500にならない。SPも横スクロールなし | screenshots/admin-inquiry/S-22-recheck-purchase-date-pc.png、screenshots/admin-inquiry/S-22-recheck-reservations-date-pc.png、screenshots/admin-inquiry/S-22-recheck-purchase-invalid-pc.png、screenshots/admin-inquiry/S-22-recheck-reservations-invalid-pc.png、screenshots/admin-inquiry/S-22-recheck-purchase-invalid-sp.png、screenshots/admin-inquiry/S-22-recheck-reservations-invalid-sp.png |

- コンソールエラー：画面の不具合に由来するものはなし。出たものはすべて意図した操作の結果（通信遮断の ERR_FAILED、404・409・422 の確認）。
- メール（Mailpit）：対応済み・発送済みの切替、不正リクエストでメールは届かない（18件のまま）。
- 追加したデータ（再UAT）：
  - orders（purchase_histories）に ID 215「UAT再 境界前」（2026-01-14 23:59:00）、ID 216「UAT再 境界後」（2026-01-15 00:00:00）。明細は各1件（item_id 1）。会員 ID 88 の注文で、一覧の注文者名は会員名の「UAT五巡 太郎」と表示される
  - reservations に ID 128「UAT再 境界前」（同23:59:00）、ID 129「UAT再 境界後」（同00:00:00）
  - inquiries に ID 42「UAT再失敗 確認用」を S-13 のために作り、DBで削除した（現在は存在しない）
  - 切り替えたデータ（注文210・215、予約124・128、お問い合わせ25・26）はすべて元に戻した（215・128 は取消にしてから canceled_at を空に戻した）

### 再UAT（SPの選択欄の表示・2026-11-08）

ユーザー決定で、検索の選択欄の「何も選ばない」表示を SP でも PC と同じ文言にした（x-admin-search-select の Alpine の切り替えを削除）。前回の S-7・S-21 の備考にある「SPで「すべて」」は、この再UATで置き換わった（SPも PC と同じ文言）。前回の行は変更していない。

| 画面 | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| 注文一覧 /admin/purchase | OK | OK | 何も選ばないときの表示は「発送状況」（選択肢：発送状況／未発送／発送済／取消済）。「すべて」は画面のどこにも出ない。「発送済」で検索→検索欄が「発送済」のまま。リセット→/admin/purchase で「発送状況」に戻る。矢印と文言の間はSP 262px・PC 95px で重ならない。横スクロールなし | screenshots/admin-inquiry/S-21-blank-label-purchase-sp.png、screenshots/admin-inquiry/S-21-blank-label-purchase-selected-sp.png、screenshots/admin-inquiry/S-21-blank-label-purchase-pc.png、screenshots/admin-inquiry/S-21-blank-label-purchase-selected-pc.png |
| 予約一覧 /admin/reservations | OK | OK | 「発送状況」。「未発送」で検索→「未発送」が残る。リセット→「発送状況」。矢印との間隔はSP 262px・PC 95px | screenshots/admin-inquiry/S-21-blank-label-reservations-sp.png、screenshots/admin-inquiry/S-21-blank-label-reservations-selected-sp.png、screenshots/admin-inquiry/S-21-blank-label-reservations-pc.png、screenshots/admin-inquiry/S-21-blank-label-reservations-selected-pc.png |
| 会員一覧 /admin/users | OK | OK | 「会員 / 退会済」（選択肢：会員 / 退会済／会員／退会済）。「退会済」で検索→残る。リセット→「会員 / 退会済」。矢印との間隔はSP 231px・PC 64px | screenshots/admin-inquiry/S-21-blank-label-users-sp.png、screenshots/admin-inquiry/S-21-blank-label-users-selected-sp.png、screenshots/admin-inquiry/S-21-blank-label-users-pc.png、screenshots/admin-inquiry/S-21-blank-label-users-selected-pc.png |
| お問い合わせ一覧 /admin/inquiries | OK | OK | 「対応済 / 非対応」。「非対応」で検索→残る。リセット→「対応済 / 非対応」。矢印との間隔はSP 215px・PC 48px | screenshots/admin-inquiry/S-21-blank-label-inquiries-sp.png、screenshots/admin-inquiry/S-21-blank-label-inquiries-selected-sp.png、screenshots/admin-inquiry/S-21-blank-label-inquiries-pc.png、screenshots/admin-inquiry/S-21-blank-label-inquiries-selected-pc.png |

- 矢印（#acacac の下向き三角形）と文言の間隔は、文字幅の実測（canvas）で、矢印の左端−文言の右端を出した値。全画面・全幅で正（重ならない）。
- コンソールエラー：なし。
- 追加したデータ：なし（検索の操作だけ）。

## ユーザー確認チェックリスト

- [x] PCのお問い合わせ一覧のレイアウトが、注文一覧・会員一覧と揃っていて違和感がない
- [x] 「対応済」のチェックと行の地の色で、対応したかどうかを見分けやすい
- [x] 詳細モーダルで、問い合わせ内容を読みやすい（改行・長い内容）
- [x] 「返信」の位置と見た目がわかりやすい
- [x] スマホで検索・カード・詳細が見やすく、チェックを押しやすい
- [x] 4つの一覧の選択欄の矢印がそろっていて、Figmaに近い
- [x] 注文一覧・予約一覧・会員一覧の見た目と動きが、矢印のほかは変わっていない

## 判定

- 受け入れ：済
- コメント：チェック項目をすべてクリアしているので受け入れ済とします
