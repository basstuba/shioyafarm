# UAT：管理画面の会員一覧

| 項目 | 内容 |
|---|---|
| 対象ルート | GET /admin/users（admin.users.index）、GET /admin/users/search（admin.users.search）、DELETE /admin/users/delete（admin.users.delete） |
| ブランチ | feat/admin-user |
| 根拠 | docs/03-page-list.md「管理画面ユーザー一覧ページ」、docs/05-table-specification.md（users）、docs/07-basic-design.md、Figma 会員一覧（154:2）・会員詳細モーダル（156:45）・削除アラートモーダル（170:347）、docs/uat/admin/admin-reservation.md のユーザー決定 |
| 実施日 | 2026-11-06 |

注文一覧・予約一覧から引き継ぐ事項：

- レイアウト（共通のヘッダー・フッター、サイドメニュー、中央のページタイトル）、検索・リセットの色、表の文字の大きさ（本文16px・モーダルの値14px）、表の幅、ページ送り、SPのカード表示、Cache-Control: no-store。
- 氏名の検索は、姓名をつなげた文字列への部分一致で、スペースの有無は問わない。

会員一覧で決める事項：

- 表は状態・氏名・電話番号・詳細・メール送信の5列（Figmaの4列に「状態」を足す）。状態は「会員」「退会済」。
- 検索は、選択欄（会員 / 退会済）と氏名。Figmaにない選択欄は、注文一覧の発送状況と同じ形にする。
- 詳細モーダルはFigmaの5項目に、退会済みの会員だけ退会日を足す。閉じ方は注文一覧と同じく下の「閉じる」。
- 物理削除は、退会済みの会員の詳細モーダルの中に置き、Figmaの削除アラートモーダルで確認する。未発送の注文・予約が残っている会員は削除できない。
- 設計書にないルート DELETE /admin/users/delete を足す。
- 「メール送信」は退会済みの会員にも出す。メール作成ページは未実装のため、メール機能のブランチまでは押すと404になる。
- Stripe の顧客データは削除しない。
- 物理削除で発送済みの予約が消えると、その分だけ予約可能数の残りが増える。物理削除は取引の終了後で、予約の受付も終わっている想定のため許容し、設計書に注記する。
- 物理削除してもStripeの決済履歴は残る。代金引換の売上は運送会社の明細で確認できるため、サイトには控えを残さない。

## 受け入れ条件

| ID | 条件 |
|---|---|
| AC-1 | 未ログイン・会員としてのログインでは /admin/users、/admin/users/search を開けず、/admin/login へ移動する |
| AC-2 | 管理者としてログインすると、共通のヘッダー・フッター、サイドメニュー、中央のページタイトル「会員一覧」、検索欄、表、ページ送りが表示される。サイドメニューとトップページの「会員一覧」のリンクから開ける |
| AC-3 | 表の列は状態・氏名（姓名）・電話番号・「詳細」・「メール送信」で、新しく登録した会員から順に1ページ10件表示される。退会済みの会員も含まれる |
| AC-4 | 11件以上あるときはページ送りが表示され、ページを移動できる。10件以下のときはページ送りが出ない。存在しないページ番号を開くと最終ページが表示される |
| AC-5 | 氏名を入れて検索すると、姓名の部分一致で表示される。「山田太郎」「山田 太郎」「山田　太郎」「山田」「太郎」のどれでも見つかる。「%」「_」は文字として扱う |
| AC-6 | 選択欄で「会員」を選ぶと退会していない会員だけ、「退会済」を選ぶと退会済みの会員だけが表示される。氏名と組み合わせると、両方に合う会員だけが表示される。どちらも空で検索すると全件が表示される |
| AC-7 | 検索後も入力した値が検索欄に残り、ページを移動しても検索条件が保たれる。「リセット」を押すと、検索欄が空になり全件の表示に戻る |
| AC-8 | 該当する会員がないときは、表の代わりに「該当する会員はいません。」と表示され、ページ送りは出ない |
| AC-9 | 選択欄に不正な値を送ると、/admin/users に戻って検索欄の近くにエラーが表示され、画面は壊れない |
| AC-10 | 「詳細」を押すと、モーダルに姓・名・住所（都道府県と市区町村以降）・電話番号・メールアドレスが表示され、「閉じる」で閉じる。パスワードなどの認証情報は表示されない |
| AC-11 | 退会済みの会員の詳細には、退会日（YYYY/MM/DD）と赤い「削除する」が表示される。退会していない会員の詳細には、どちらも表示されない |
| AC-12 | 「削除する」を押すと「削除します。よろしいですか？」と「注文・予約の記録も消えます。必要なら先にエクスポートしてください。」の確認が表示される。「キャンセル」を押すと確認だけが閉じ、会員は削除されない |
| AC-13 | 確認で「削除する」を押すと、会員と、その会員の注文・予約・振込情報・配送先住所が完全に削除され、削除の前に見ていた検索条件・ページの一覧に戻って、検索欄の下（エラーと同じ場所）に「会員を削除しました。」が緑の文字（背景・枠なし）で表示される。一覧・注文一覧・予約一覧から消え、他の会員のデータは変わらない |
| AC-14 | 未発送の注文または予約が1件でも残っている退会済みの会員は削除できず、削除の前に見ていた検索条件・ページの一覧に戻って「未発送の注文・予約があるため削除できません。」と表示され、データは何も変わらない |
| AC-15 | 退会していない会員、存在しない会員は、リクエストを直接送っても削除できない。未ログイン・会員としてのログインでも削除できない |
| AC-16 | 削除した会員のメールアドレスは、会員登録で使えるようになる |
| AC-17 | 「メール送信」は、退会済みの会員を含むすべての行に表示され、リンク先は /admin/mail/user/{会員ID} である |
| AC-18 | SPでは検索欄が縦積みになり、表はカード形式になる。文字は16px以上（モーダルの値は14px。確認モーダルのボタンの文字もSPは16px）、ボタン・リンクは高さ44px以上で、横スクロールが出ない。確認モーダルが画面からはみ出さない |
| AC-19 | ログアウトしたあとにブラウザの戻るボタンを押しても会員一覧の内容が表示されず、/admin/login へ移動する |
| AC-20 | 氏名に HTML の記号が含まれていても、文字のまま表示される |
| AC-21 | 注文一覧・予約一覧の表示と動きは、これまでと変わらない |

## シナリオ

| ID | 対応AC | 画面幅 | 前提 | 手順 | 期待結果 |
|---|---|---|---|---|---|
| S-1 | AC-1 | PC | 未ログイン | /admin/users、/admin/users/search を開く | どちらも /admin/login へ移動する |
| S-2 | AC-1 | PC | 会員としてログイン | /admin/users を開く | /admin/login へ移動する |
| S-3 | AC-2 | PC/SP | 管理者でログイン | トップページとサイドメニューの「会員一覧」を押す | 会員一覧が開き、ヘッダー・フッター・サイドメニュー・タイトル・検索欄・表が表示される |
| S-4 | AC-3 | PC | 会員と退会済みの会員がいる | /admin/users を開く | 5列で、新しく登録した順に10件表示され、状態に「会員」「退会済」が出る |
| S-5 | AC-4 | PC | 会員が11件以上いる | 2ページ目へ移動し、?page=9999 も開く | ページを移動でき、存在しないページは最終ページになる |
| S-6 | AC-4 | PC | 検索結果が10件以下 | 検索する | ページ送りが出ない |
| S-7 | AC-5 | PC | 「山田太郎」の会員がいる | 「山田太郎」「山田 太郎」「山田　太郎」「山田」「太郎」で順に検索する | どれでも見つかる |
| S-8 | AC-5 | PC | 氏名に「%」を含まない会員がいる | 「%」「_」で検索する | 全件にならず、該当なしの表示になる |
| S-9 | AC-6 | PC | 会員と退会済みの会員がいる | 選択欄を「会員」「退会済」に切り替えて検索し、氏名とも組み合わせる | 条件に合う会員だけが表示される |
| S-10 | AC-6 | PC | | どちらも空で検索する | 全件が表示される |
| S-11 | AC-7 | PC | 検索結果が11件以上 | 検索して2ページ目へ移動し、「リセット」を押す | 条件と入力値が保たれ、リセットで全件の表示に戻る |
| S-12 | AC-8 | PC/SP | | 存在しない氏名で検索する | 「該当する会員はいません。」が表示され、ページ送りは出ない |
| S-13 | AC-9 | PC | | /admin/users/search?status=abc を開く | /admin/users に戻ってエラーが表示される |
| S-14 | AC-10 | PC/SP | 退会していない会員がいる | 「詳細」を押し、「閉じる」を押す | 5項目が正しく表示され、退会日と「削除する」は出ず、閉じられる |
| S-15 | AC-11 | PC/SP | 退会済みの会員がいる | 「詳細」を押す | 5項目に加えて退会日と「削除する」が表示される |
| S-16 | AC-12 | PC/SP | 退会済みの会員がいる | 「削除する」を押し、「キャンセル」を押す | 確認に2つの文が表示され、キャンセルで閉じて、会員は残っている |
| S-17 | AC-13 | PC | 発送済みの注文・予約、振込情報、配送先住所を持つ退会済みの会員がいる | 確認で「削除する」を押す | 「会員を削除しました。」が表示され、会員と関連データがDBから消え、注文一覧・予約一覧にも出ない。他の会員のデータは残る |
| S-18 | AC-13 | SP | 注文・予約のない退会済みの会員がいる | 確認で「削除する」を押す | 削除され、一覧に戻って案内が表示される |
| S-19 | AC-14 | PC | 未発送の注文を持つ退会済みの会員がいる | 確認で「削除する」を押す | 「未発送の注文・予約があるため削除できません。」が表示され、データは変わらない |
| S-20 | AC-14 | PC | 未発送の予約を持つ退会済みの会員がいる | 確認で「削除する」を押す | S-19 と同じ |
| S-21 | AC-14 | PC | S-19 の会員の注文を発送済みにする | もう一度削除する | 削除できる |
| S-22 | AC-15 | PC | 退会していない会員がいる | 管理者のセッションで、その会員のIDと存在しないIDを DELETE /admin/users/delete へ直接送る | どちらも削除されず、エラーになる |
| S-23 | AC-15 | PC | 退会済みの会員がいる | 未ログイン、会員としてのログインで DELETE /admin/users/delete へ直接送る | 削除されず、/admin/login へ移動する |
| S-24 | AC-16 | PC | S-18 で削除した会員のメールアドレス | そのメールアドレスで /register から登録する | 登録できる |
| S-25 | AC-17 | PC/SP | 会員と退会済みの会員がいる | 「メール送信」のリンク先を確かめる | すべての行にあり、/admin/mail/user/{会員ID} である（押すと404で問題ない） |
| S-26 | AC-18 | SP | 会員が11件以上いる | 一覧・検索・詳細・確認を操作する | 検索欄が縦積み、表がカードになり、文字とボタンの大きさを満たし、横スクロールが出ない |
| S-27 | AC-19 | PC | 管理者でログインして一覧を開く | ログアウトして、ブラウザの戻るボタンを押す | 内容が表示されず、/admin/login へ移動する |
| S-28 | AC-20 | PC | 氏名が `<b>テスト</b>` の会員がいる | 一覧と詳細を開く | 文字のまま表示される |
| S-29 | AC-21 | PC | | 注文一覧・予約一覧を開き、検索・詳細・ページ送りを操作する | これまでと変わらない |

## 実施結果

| ID | PC | SP | 備考 | スクリーンショット |
|---|---|---|---|---|
| S-1 | OK | - | 再レビュー修正後の再UAT。未ログインで /admin/users、/admin/users/search を開くと、どちらも /admin/login へ移動した | screenshots/admin-user/S-1-pc.png |
| S-2 | OK | - | 再レビュー修正後の再UAT。会員（mypage-uat）でログイン中に /admin/users を開くと /admin/login へ移動した | screenshots/admin-user/S-2-pc.png |
| S-3 | OK | OK | 再レビュー修正後の再UAT。PC・SPとも、トップページの「会員一覧」とサイドメニューの「会員一覧」（SPは「メニュー」を開く。高さ48px）から /admin/users が開いた。共通のヘッダー・フッター・サイドメニュー・中央のタイトル・検索欄・表が表示され、サイドメニューの「会員一覧」は太字。SPは検索欄が縦積み・表がカード形式 | screenshots/admin-user/S-3-pc.png、screenshots/admin-user/S-3-sp.png、screenshots/admin-user/S-3-home-pc.png、screenshots/admin-user/S-3-home-sp.png、screenshots/admin-user/S-3-menu-sp.png |
| S-4 | OK | - | 再レビュー修正後の再UAT。列は状態・氏名・電話番号・詳細・メール送信の5列。新しい登録順に10件、状態に「会員」「退会済」が混在（退会済みも含む） | screenshots/admin-user/S-3-pc.png |
| S-5 | OK | - | 再レビュー修正後の再UAT。5ページ（実施時の会員45件）。2ページ目は10件、?page=9999 は最終の5ページ目（5件）が表示された。ページ送りは「<,1,2,3,…,5,>」の形 | screenshots/admin-user/S-5-pc.png |
| S-6 | OK | - | 再レビュー修正後の再UAT。「UAT山田」で検索すると1件でページ送りが出ない。入力値は残る。ほかの10件以下の検索（会員＋UAT退会＝0件、退会済＋山田＝0件など）でもページ送りは出ない | screenshots/admin-user/S-6-pc.png |
| S-7 | OK | - | 再レビュー修正後の再UAT。「山田太郎」「山田 太郎」「山田　太郎」「山田」はすべて UAT山田 太郎の1件のみ。「太郎」は部分一致のため、UAT再登録2 太郎・UAT 購入太郎・仮野 太郎も出る（AC-5 の部分一致どおり）。追加：「Mary Ann」「maryann」は UAT Mary Ann の1件 | screenshots/admin-user/S-7-pc.png |
| S-8 | OK | - | 再レビュー修正後の再UAT。氏名に「%」を含む会員「UAT%記号 会員」（前回追加。残してある）で確認：「%」＝その1件だけ（全件にならない）、「UAT%」＝その1件のみ、「%記号」＝その1件のみ。「記号%会」・「a%」・「%%」・「100%」・「_」＝該当なし。「%」「_」は文字として扱われている | screenshots/admin-user/S-8-pc.png |
| S-9 | OK | - | 再レビュー修正後の再UAT。「会員」＝会員のみ（10件・2ページ）、「退会済」＝退会済みのみ（10件・3ページ）、「会員」＋「UAT退会」＝0件、「退会済」＋「UAT戻り2」＝UAT戻り2 の10件、「退会済」＋「山田」＝0件、「会員」＋「山田」＝UAT山田 太郎の1件。入力値と選択は検索後も残る | screenshots/admin-user/S-9-pc.png |
| S-10 | OK | - | 再レビュー修正後の再UAT。選択を「会員 / 退会済」、氏名を空で検索すると全件（5ページ・1ページ10件） | screenshots/admin-user/S-10-pc.png |
| S-11 | OK | - | 再レビュー修正後の再UAT。「会員」＋「UAT」で検索（2ページ）し、2ページ目へ移動すると URL に status=member&name=UAT&page=2 が残り、選択・氏名も残る（6件）。「リセット」で /admin/users・検索欄が空・全件（5ページ）に戻る | screenshots/admin-user/S-11-pc.png |
| S-12 | OK | OK | 再レビュー修正後の再UAT。PC・SPとも「存在しない氏名ZZZ」で「該当する会員はいません。」が表示され、ページ送りは出ない | screenshots/admin-user/S-12-pc.png、screenshots/admin-user/S-12-sp.png |
| S-13 | OK | - | 再レビュー修正後の再UAT。/admin/users/search?status=abc は /admin/users へ移動（200）し、検索欄の下に「状態は「会員」か「退会済」を選んでください。」。全件の表は表示される。追加：status[]=x は同じ文言、name[]=x は「氏名は文字で入力してください。」、status=abc&name=UAT山田 は氏名の欄が「UAT山田」のまま残る。500にならない | screenshots/admin-user/S-13-pc.png |
| S-14 | OK | OK | 再レビュー修正後の再UAT。会員「UAT山田 太郎」の詳細に5項目が正しく表示され、退会日と「削除する」は出ない。パスワード・トークン・Stripe の顧客IDの記載なし。「閉じる」・Esc で閉じる。項目：ラベル16px・太字・行の高さ20px・文字色 #2e2e2e、値14px・上の余白8px、下の線は1px #e6e6e6・下の余白4px（会員・注文・予約の詳細で同じ。注文・予約の商品内訳だけは線なし・値16px）　【3回目の修正後に再確認】PC・SPとも、退会していない会員「UAT山田 太郎」の詳細は5項目で、退会日と「削除する」は出ず、「閉じる」で閉じる。会員・注文・予約の「詳細」ボタンでモーダルが開き、閉じる。ボタンはSP 66×44px（白地・枠1px #acacac・角丸10px・16px）、PC 64×44px（枠なし・下線・16px）。項目の文字（ラベル16px／値14px、商品内訳は値16px・線なし）と線（1px）は、3画面で同じ。横スクロールなし。OK | screenshots/admin-user/S-14-pc.png、screenshots/admin-user/S-14-sp.png、screenshots/admin-user/S-ui-detail-purchase-pc.png、screenshots/admin-user/S-ui-detail-reservations-pc.png |
| S-15 | OK | OK | 再レビュー修正後の再UAT。退会済み「UAT退会済み テスト」の詳細に5項目に加えて退会日「2026/10/26」と赤い「削除する」。ボタンはPC 130×30・14px、SP 130×44・16px、フォントは本文と同じ Inter（丸ゴシックでない）・Medium、背景 #dd4a48（rgb(221,74,72)）・白文字。SPのモーダルは382×622px で画面に収まる　【3回目の修正後に再確認】PC・SPとも、退会済み「UAT退会済み テスト」の詳細に5項目＋退会日「2026/10/26」＋「削除する」が出る。詳細の「削除する」は、414px・768px・1023px が 130×44・16px、1024px・1280px が 130×30・14px（フォントはどれも Inter）。OK | screenshots/admin-user/S-15-pc.png、screenshots/admin-user/S-15-sp.png |
| S-16 | OK | OK | 再レビュー修正後の再UAT。確認に「削除します。よろしいですか？」と「注文・予約の記録も消えます。必要なら先にエクスポートしてください。」が表示される。確認の「削除する」は PC 130×30・14px／SP 130×44・16px、背景 #dd4a48・白文字、「キャンセル」は背景 #e6e6e6（rgb(230,230,230)）・文字 #4f4f4f（rgb(79,79,79)）で、どちらもフォントは Inter（本文と同じ）。ホバーで地の色が変わり影が付く（削除する＝rgb(220,3,0)、キャンセル＝rgb(205,205,205)）。「キャンセル」で確認だけが閉じ（詳細は残る）、会員もDBに残る。確認を開く→キャンセル→また開くで「削除する」が押せる（有効）。Esc も確認だけを閉じる。SPの確認は382×216px で画面内、横スクロールなし、本文16px。送信直後は「削除する」が無効になり（二重送信防止）、キャンセル→開き直しで有効に戻る。失敗後にブラウザの戻るで戻ると、一覧は再読み込みされ（ページは Cache-Control: no-store のため bfcache で復元されない）、詳細→削除する→確認で「削除する」は有効。補足：前回は pageshow（persisted）で無効を解除する処理があったが、今回のビューにはなく、pageshow を擬似的に発火させても無効のままで、キャンセル→開き直しで有効に戻る（実ブラウザの bfcache 復元は no-store のため起きない想定で、実機の確認はしていない）　【3回目の修正後に再確認】①ボタン：確認の「削除する」「キャンセル」も、414px・768px・1023px で 130×44・16px、1024px・1280px で 130×30・14px。②フォーカス（PC 1280×800・SP 414×896とも）：確認を開くと「キャンセル」にフォーカスが当たる。Tab を6回・Shift+Tab を6回押しても、フォーカスは確認の「削除する」と「キャンセル」の間を行き来し、確認の外へ出ない。「キャンセル」・Esc・背景クリックのどれでも、確認だけが閉じて詳細は残り、フォーカスは詳細の「削除する」に戻る。そのあと確認をもう一度開くと「キャンセル」にフォーカスが当たり、「削除する」は有効（PCでは、キャンセル→再度開く→削除まで実施。SPでも削除まで実施）。OK | screenshots/admin-user/S-16-pc.png、screenshots/admin-user/S-16-sp.png、screenshots/admin-user/S-16-submitting-pc.png、screenshots/admin-user/S-16-back-pc.png |
| S-17 | OK | - | 再レビュー修正後の再UAT。退会済み「UAT三削除 一郎」（発送済みの注文#125〈明細2・振込情報1〉・予約#81〈振込情報1〉、配送先住所2件、password_reset_tokens 1行、sessions 1行）を、「退会済」で絞った検索結果の2ページ目（/admin/users/search?status=withdrawn&name=&page=2）から削除すると、同じ条件・同じ2ページ目に戻り「会員を削除しました。」。DB件数の削除前→後：users 45→44、purchase_histories 122→121、purchase_history_items 259→257、purchase_bank_transfers 26→25、reservations 79→78、reservation_bank_transfers 28→27、delivery_addresses 24→22、password_reset_tokens 6→5（その会員のメールの行だけ消え、ほかの行は残る）。sessions は、その会員のID（44）の uat-sess-3A を含め、1行も消えない（意図した仕様）。会員・注文・予約の検索に出ない。管理者と同じIDの会員の削除（今回の重点）：退会済み会員「UAT同ID 会員」（ID 47）と同じID 47の管理者「UAT同ID管理者」（uat-admin-sameid@example.com）を tinker で作り、その管理者でログインして sessions に user_id=47 の行ができていることを確かめたうえで、画面から会員47を削除した。「会員を削除しました。」が表示され、ログアウトされず、続けて /admin/users・/admin も開け、sessions の user_id=47 の行はそのまま残った。その会員のメールの password_reset_tokens の行は消えた（admins の ID 47 は残る）　【3回目の修正後に再確認】PC：退会済み「UAT四削除 一郎」（発送済みの注文#127・明細1・振込情報1、password_reset_tokens 1行つき）を、確認を開く→キャンセル→再度開く→「削除する」で削除。同じ検索条件（status=withdrawn&name=UAT四削除&page=1）に戻り「会員を削除しました。」。DB：users 50→49、purchase_histories 121→120、purchase_history_items 257→256、purchase_bank_transfers 26→25、password_reset_tokens 11→10（その会員のメールの行だけ消え、ほかの10行は残る）。他のテーブル・会員は変化なし。OK　【受け入れ指摘の修正後に再確認】PC（1280×800）：退会済み「UAT五削除 一郎」（password_reset_tokens 1行つき）を削除すると、同じ検索条件に戻り、検索欄の下（エラーと同じ場所）に「会員を削除しました。」が1回だけ表示された。要素は p（role=status）で、文字色 #427835（rgb(66,120,53)）・背景なし・枠なし・中央寄せ・16px。ページ上部の枠つきの案内には出ない（画面全体に1回だけ）。再読み込みで消えた。OK | screenshots/admin-user/S-17-confirm-pc.png、screenshots/admin-user/S-17-pc.png、screenshots/admin-user/S-17-sameid-pc.png |、screenshots/admin-user/S-17-flash-pc.png
| S-18 | - | OK | 再レビュー修正後の再UAT。SPで注文・予約のない退会済み「UAT三削除 SP花子」を「退会済」の検索結果の2ページ目から削除すると、同じ条件・2ページ目に戻り「会員を削除しました。」（16px）。横スクロールなし。users 43→42、他のテーブルの件数は変わらず　【3回目の修正後に再確認】SP：退会済み「UAT四削除 SP花子」（password_reset_tokens 1行つき）を、確認を開く→キャンセル→再度開く→「削除する」で削除。同じ検索条件に戻り「会員を削除しました。」（16px）。横スクロールなし。users 49→48、password_reset_tokens 10→9（その会員の行だけ）。OK　【受け入れ指摘の修正後に再確認】SP（414×896）：退会済み「UAT五削除 SP花子」を削除すると、同じ検索条件に戻り、検索欄の下に「会員を削除しました。」が1回だけ、文字色 #427835・背景なし・枠なし・中央寄せ・16pxで表示された。横スクロールなし。再読み込みで消えた。OK | screenshots/admin-user/S-18-sp.png |、screenshots/admin-user/S-18-flash-sp.png
| S-19 | OK | - | 再レビュー修正後の再UAT。未発送の注文を持つ退会済み「UAT未発送3 注文」を、「退会済」の検索結果の2ページ目から削除しようとすると、同じ条件・2ページ目（表示も同じ10件）に戻り「未発送の注文・予約があるため削除できません。」。SPでも同じ（16px・横スクロールなし）。DB件数は変わらず、注文は未発送のまま　【3回目の修正後に再確認】PC・SPとも、未発送の注文・予約が残る場合の案内「未発送の注文・予約があるため削除できません。」は変わらない（今回の確認は S-20 の会員で実施。SPは16px・横スクロールなし）。DB件数は変化なし。OK　【受け入れ指摘の修正後に再確認】未発送の失敗（PC・SP。「UAT未発送 予約」を氏名つき検索から削除）：同じ場所（検索欄の下）に、赤字（rgb(217,48,37)）・中央寄せ・16px で「未発送の注文・予約があるため削除できません。」が1回だけ出る（変わらない）。OK | screenshots/admin-user/S-19-return-pc.png、screenshots/admin-user/S-19-return-sp.png |、screenshots/admin-user/S-19-flash-pc.png、screenshots/admin-user/S-19-flash-sp.png
| S-20 | OK | - | 再レビュー修正後の再UAT。未発送の予約を持つ退会済み「UAT未発送 予約」を、氏名つき検索（status=withdrawn&name=UAT未発送）と、全件の一覧の3ページ目から削除しようとすると S-19 と同じ文言で削除できず、同じ条件・ページに戻る。SPでも同じ。DB件数は変わらず。password_reset_tokens の行も残る　【3回目の修正後に再確認】未発送の予約を持つ退会済み「UAT未発送 予約」を、氏名つき検索（status=withdrawn&name=UAT未発送）から PC・SP で削除しようとすると、同じ検索条件に戻り「未発送の注文・予約があるため削除できません。」が表示され、削除されない。password_reset_tokens の行も残る。OK | screenshots/admin-user/S-19-return-pc.png、screenshots/admin-user/S-19-return-sp.png |
| S-21 | OK | - | 再レビュー修正後の再UAT。S-19 の会員の注文（#126）を tinker で発送済みにしてから、氏名つき検索（status=withdrawn&name=UAT未発送）から削除すると、同じ検索条件に戻り「会員を削除しました。」（「UAT未発送 予約」だけが残る）。purchase_histories 121→120、purchase_history_items 257→256、password_reset_tokens 5→4　【3回目の修正後に再確認】今回は、注文を発送済みにしてから削除する流れは再実施していない（変更箇所に含まれないため。成功する削除は S-17・S-18 で確認） | screenshots/admin-user/S-21-pc.png |
| S-22 | OK | - | 再レビュー修正後の再UAT。管理者のセッション・CSRFトークン付きで、退会していない会員（UAT非退会 直接）、存在しないID（99999）、abc、IDなしを DELETE /admin/users/delete へ送ると、どれも302で /admin/users に戻り、画面に「退会していない会員、または存在しない会員は削除できません。」が表示される。実際の DELETE メソッド、Accept: application/json でも302。Referer が会員一覧・検索結果のときはそのURLに、外部URLや注文一覧のときは /admin/users に戻る。CSRFトークンなしは 419。会員・DBの件数は変化なし　【3回目の修正後に再確認】419：（1）確認の form の _token を書き換えて「削除する」を送る（PC・SP）、（2）確認を開いたまま別タブで /admin のログアウト→再ログインして、元のタブで「削除する」を送る（PC）。どちらも見ていた一覧（status=withdrawn&name=UAT四削除&page=1。同じ検索条件・6件）に戻り、「ページの有効期限が切れました。もう一度削除してください。」が表示され、削除されない（会員61・62はDBに残る。SPは16px・横スクロールなし）。（3）確認を開いたまま別タブでログアウトし、ログアウトしたまま元のタブで「削除する」を送ると、/admin/login（管理者用ログイン）へ移動し、削除されない（会員63は残る）。OK　【受け入れ指摘の修正後に再確認】419（確認の _token を書き換えて送信。PC・SP）：同じ場所に赤字・中央寄せ・16px で「ページの有効期限が切れました。もう一度削除してください。」が1回だけ出て、削除されない。注文一覧・予約一覧（PC・SP、検索結果も）の検索欄の下には、これまでと同じ「エクスポート」のほか何も増えていない。/admin のログアウトの _token を書き換えた有効期限切れの案内「ページの有効期限が切れました。もう一度ログアウトしてください。」は、これまでどおり枠つき（1px・背景 #f5f5f5）で上部に出る（PC・SP）。OK | screenshots/admin-user/S-22-pc.png |、screenshots/admin-user/S-22-419-flash-pc.png、screenshots/admin-user/S-22-419-flash-sp.png、screenshots/admin-user/S-ui-logout419-pc.png、screenshots/admin-user/S-ui-logout419-sp.png
| S-23 | OK | - | 再レビュー修正後の再UAT。会員（mypage-uat）でログイン中と未ログインで、退会済みの会員（UAT退会 直接）に対して CSRFトークン付きで DELETE /admin/users/delete を送ると、どちらも302で /admin/login。会員は削除されていない | - |
| S-24 | OK | - | 再レビュー修正後の再UAT。S-18 で削除した uat-del3-sp@example.com で /register から登録でき、/items へ移動した（会員「UAT再登録3 次郎」）。対照として、退会済みで残っている uat-unship-resv@example.com では「このメールアドレスはすでに登録されています」と出て登録できない | screenshots/admin-user/S-24-pc.png、screenshots/admin-user/S-24-withdrawn-email-pc.png |
| S-25 | OK | OK | 再レビュー修正後の再UAT。PC（5ページ全43行）・SPとも、退会済みを含むすべての行に「メール送信」があり、リンク先は /admin/mail/user/{会員ID}。SPは高さ44px。/admin/mail/user/22 を開くと404（想定どおり） | screenshots/admin-user/S-25-pc.png、screenshots/admin-user/S-25-sp.png |
| S-26 | - | OK | 再レビュー修正後の再UAT。SP（414×896）：検索欄は状態・氏名が縦に並び、検索とリセットは横2つ。表はカード形式。文字は16px以上（h1 28px、カードのラベル・値、検索欄・ボタン、ページ送り、案内・エラー、確認モーダルの文言とボタンとも16px。詳細モーダルの値は14px・ラベル16px）。高さは、選択欄・氏名欄・検索・リセット48px、詳細ボタン・メール送信・ページ送り・モーダルの「閉じる」・「削除する」・確認の2つのボタンが44px。サイドメニューの「会員一覧」48px。横スクロールなし（一覧・検索結果・2ページ目・エラー表示・削除後の案内・確認モーダル表示中。768px・1024px でも）。確認モーダルは画面内に収まる　【3回目の修正後に再確認】SP（414×896）：一覧の選択欄・氏名欄 48px、検索・リセット 48px、詳細ボタン・メール送信・ページ送り 44px、文字は16px以上（モーダルの値を除く）、横スクロールなし。確認モーダルは382×216px で画面内、ボタンは130×44px・16px。確認の中でのフォーカス移動後も、はみ出しや横スクロールなし。OK | screenshots/admin-user/S-26-sp.png、screenshots/admin-user/S-26-search-sp.png、screenshots/admin-user/S-26-error-sp.png、screenshots/admin-user/S-16-sp.png、screenshots/admin-user/S-18-sp.png |
| S-27 | OK | - | 再レビュー修正後の再UAT。ログアウトして /admin/login に移動後、ブラウザの戻るで会員一覧の内容は表示されず /admin/login のまま | screenshots/admin-user/S-27-pc.png |
| S-28 | OK | - | 再レビュー修正後の再UAT。氏名が <b>テスト</b> の会員は、一覧では「<b>テスト</b> UAT」と文字のまま表示され（b要素0個）、詳細の姓にも文字のまま。氏名「<b>」で検索すると1件出て、入力値も文字のまま残る | screenshots/admin-user/S-28-pc.png |
| S-29 | OK | OK | 再レビュー修正後の再UAT。検索欄・詳細モーダルの外枠・ボタンの部品化後の注文一覧（120件・12ページ）・予約一覧（78件・8ページ）を、PC・SPの両方で確認。タイトル・列・ページ送り（2ページ目10件）・サイドメニューの現在地・検索（氏名「UAT山田」＝注文8件・予約6件）・発送状況の検索・リセット・日付エラー（/search?date=abc で「注文日／予約日は日付で入力してください。」、エクスポートは押せない）・エクスポート（PC・SPとも waitForEvent('download') で受け取り。注文一覧_20261107.xlsx 257行・予約一覧_20261107.xlsx 79行、見出し9列、数式0件）・発送済みチェック（PC=注文120・予約73、SP=注文118・予約57：行が #d4d4d4、POST 200、再読み込み後も維持、外すと戻る。実施後は未発送に戻した）・詳細モーダル（PC=閉じる・Esc、SP=閉じる）が、これまでどおり動く。部品の見た目の確認：①検索欄の入力欄・選択欄は、会員・注文・予約の3画面で同じ（PC高さ40px／SP 48px、枠 1px #acacac、角丸10px、背景 #f9f9f9、左余白12px、文字16px、フォーカスは 2px の実線 #2e2e2e・間隔2px、プレースホルダー #acacac）。②選択欄の未選択の表示は、PC（1024px以上）が「会員 / 退会済」「発送済 / 未発送」、SP（1023px以下）が「すべて」。画面幅を 1280→1100→1024→1023→768→414→1280 と変えた直後も切り替わり、SP幅で開き直しても「すべて」、値を選ぶとその値が出る（会員・注文・予約とも）。③詳細モーダルの項目は注文・予約・会員で同じ（S-14）。④マイページの退会のボタンは変わらず（Kiwi Maru・14px・PC 130×30／SP 130×44、背景 #dd4a48・白文字、確認のキャンセルは #e6e6e6・#4f4f4f）。SPの検索・リセット・エクスポート・詳細・ページ送りは高さ44px以上、横スクロールなし　【3回目の修正後に再確認】「詳細」ボタン：注文一覧・予約一覧・会員一覧の PC・SPとも、押すとモーダルが開き、「閉じる」で閉じる。見た目はこれまでと同じ（SP 66×44px・白地・枠1px #acacac・角丸10px・16px、PC 64×44px・枠なし・下線・16px）。注文・予約の詳細の項目も同じ（商品内訳は線なし・値16px、ほかは値14px・線1px）。横スクロールなし。OK | screenshots/admin-user/S-29-purchase-pc.png、screenshots/admin-user/S-29-purchase-error-pc.png、screenshots/admin-user/S-29-purchase-check-pc.png、screenshots/admin-user/S-29-purchase-detail-pc.png、screenshots/admin-user/S-29-reservations-pc.png、screenshots/admin-user/S-29-reservations-error-pc.png、screenshots/admin-user/S-29-reservations-check-pc.png、screenshots/admin-user/S-29-reservations-detail-pc.png、screenshots/admin-user/S-29-purchase-sp.png、screenshots/admin-user/S-29-purchase-error-sp.png、screenshots/admin-user/S-29-purchase-check-sp.png、screenshots/admin-user/S-29-purchase-detail-sp.png、screenshots/admin-user/S-29-reservations-sp.png、screenshots/admin-user/S-29-reservations-error-sp.png、screenshots/admin-user/S-29-reservations-check-sp.png、screenshots/admin-user/S-29-reservations-detail-sp.png、screenshots/admin-user/S-ui-search-users-pc.png、screenshots/admin-user/S-ui-search-purchase-pc.png、screenshots/admin-user/S-ui-search-reservations-pc.png、screenshots/admin-user/S-ui-search-users-sp.png、screenshots/admin-user/S-ui-search-purchase-sp.png、screenshots/admin-user/S-ui-search-reservations-sp.png、screenshots/admin-user/S-ui-mypage-withdraw-pc.png、screenshots/admin-user/S-ui-mypage-withdraw-sp.png |

- コンソールエラー：なし（全操作を通じたエラーは、想定どおり404になる /admin/mail/user/22 を開いたときの「Failed to load resource: 404」1件のみ）
- メール（Mailpit）：対象外
- 追加したデータ（今回の再UAT。前回までの追加分も残っている）：
  - users に tinker で追加（パスワードは password123）：
    - UAT三削除 一郎（uat-del3-ship@example.com、退会済み、発送済みの注文#125〈明細2・振込情報1〉・予約#81〈振込情報1〉・配送先住所2件、password_reset_tokens 1行、sessions の uat-sess-3A）→S-17で削除
    - UAT三削除 SP花子（uat-del3-sp@example.com、退会済み、関連なし）→S-18で削除
    - UAT未発送3 注文（uat-unship3-order@example.com、退会済み、未発送の注文#126、password_reset_tokens 1行）→S-21で発送済みにしてから削除
    - UAT同ID 会員（uat-sameid-member@example.com、退会済み、ID 47、password_reset_tokens 1行）→削除。あわせて admins に同じID 47の管理者「UAT同ID管理者」（uat-admin-sameid@example.com / sameid1234）を追加（残してある。既存の管理者は変更していない）
    - UAT戻り2 01〜10（uat-backb01〜10@example.com、退会済み、関連なし。画面の確認用）→残っている
  - S-24 の /register で会員「UAT再登録3 次郎」（uat-del3-sp@example.com、password123）を1件登録した（残してある）。
  - S-21 のため purchase_histories #126 を tinker で発送済みにした（その後、会員ごと削除）。S-29 の発送済みチェックは、実施後にすべて未発送へ戻した。
  - 前回までに追加した会員（UAT未発送 予約〈#79〉、UAT非退会 直接、UAT退会 直接、<b>テスト</b> UAT、UAT%記号 会員、UAT戻り 05〜13、UAT再登録 花子、UAT再登録2 太郎、sessions の uat-sess-20・uat-sess-other など）は、そのまま使った。既存のデータ（test@test.com、uat-buy など）は変更・削除していない。S-14〜16 は既存の退会済み「UAT退会済み テスト」で確認し、キャンセルまでにした。
  - 3回目の修正後の再確認で追加した会員（tinker。パスワードは password123。いずれも退会済みで password_reset_tokens 1行つき）：UAT四削除 一郎（uat-del4-ship@example.com、発送済みの注文#127・明細1・振込情報1つき）→S-17で削除、UAT四削除 SP花子（uat-del4-sp@example.com）→S-18で削除、UAT四削除 四一〜四五（uat-del4-b1〜b5@example.com、関連なし。419の確認用で、四四の分はSPの419確認に使った。いずれも削除されていない）→残っている。3回目の再確認後の件数：users 48件、purchase_histories 120件、purchase_history_items 256件、purchase_bank_transfers 25件、reservations 78件、reservation_bank_transfers 27件、delivery_addresses 22件、password_reset_tokens 9件。
  - 受け入れ指摘の修正後の再確認で追加した会員（tinker。パスワードは password123。いずれも退会済みで password_reset_tokens 1行つき）：UAT五削除 一郎（uat-del5-a@example.com）→S-17で削除、UAT五削除 SP花子（uat-del5-sp@example.com）→S-18で削除。419 の確認には、削除されない UAT四削除 四一（uat-del4-b1@example.com）を使い、削除されなかった。再確認後の件数：users 46件、purchase_histories 120件、purchase_history_items 256件、purchase_bank_transfers 25件、reservations 78件、reservation_bank_transfers 27件、delivery_addresses 22件、password_reset_tokens 7件。なお、前回の3回目の再確認の終了時点では UAT四削除 四一〜四五が残っていたが、今回の開始時には 四四・四五（uat-del4-b4・b5）がなかった（この再確認での削除操作ではない。前回終了時 users 48件 → 今回開始時 46件）。四一〜四三は残っている。
  - 件数：実施前（今回の追加後）は users 46件、purchase_histories 122件、reservations 79件、password_reset_tokens 7件。実施後は users 43件、purchase_histories 120件、purchase_history_items 256件、purchase_bank_transfers 25件、reservations 78件、reservation_bank_transfers 27件、delivery_addresses 22件、password_reset_tokens 4件、sessions 19件（物理削除では減らない。実施中のログインで増えた分を含む）。

## ユーザー確認チェックリスト

- [x] PCの会員一覧のレイアウトが、注文一覧・予約一覧と揃っていて違和感がない
- [x] 「状態」の列と検索の選択欄で、退会済みの会員を見分けやすい
- [x] 詳細モーダルの項目が、会員の確認に足りている
- [x] 「削除する」の位置と確認の文言が、取り消せない操作として十分に慎重である
- [x] 未発送で削除できないときの案内がわかりやすい
- [x] スマホで検索・カード・詳細・確認モーダルが見やすく、押しやすい
- [x] 注文一覧・予約一覧の見た目と動きが、これまでと変わっていない

## 判定

- 受け入れ：済
- コメント：チェック項目をすべてクリアしているので受け入れ済とします
