---
paths:
  - "src/config/shipping.php"
  - "src/app/**/*Shipping*.php"
  - "src/app/**/*Purchase*.php"
  - "src/app/**/*Cart*.php"
  - "src/app/**/*Payment*.php"
  - "src/resources/views/shipping*"
  - "src/resources/views/shipping/**"
---

# 送料・決済の規約

## 送料計算

送料は `config/shipping.php` の設定から計算する（ヤマト運輸の料金を元に自作）。料金を処理の中に直接書かず、必ず `config('shipping.*')` から取得する。

計算の流れ：

1. **都道府県 → 地域**：`shipping.prefecture_to_area`（例：`'東京都' => '関東'`）
2. **合計重量 → 箱サイズ**：`shipping.sizes` を小さい順に見て、`max_weight`（kg）が合計重量以上になる最初のサイズ（60〜180）を選ぶ
3. **地域 × サイズ → 送料**：`shipping.fees[サイズ][地域]`（円、税込）

- 合計重量が30kg（180サイズの上限）を超える場合は2個口に分け、1個目を30kg、2個目を残りの重量として、それぞれのサイズで送料を出して合計する（例：35kg → 180サイズ（30kg）＋ 80サイズ（5kg））。
- `shipping.areas` は地域 → 都道府県の一覧で、送料案内ページ（`views/shipping/division-list.blade.php`）の表示に使う。
- 送料案内ページの料金表（`views/shipping/fee-list.blade.php`）も同じ設定から表示し、計算と表示の値がずれないようにする。

## 発送時期の文言

発送の時期は `config/shipping.php` の `ship_days_text`（'3〜5営業日'。購入・代金引換の「ご注文（お支払い）の確定後、〇〇以内に発送」）と `reservation_ship_period_text`（'9月中'。予約商品「北の恵みセット」の発送時期）から取る。画面・メール・特商法・商品一覧のご案内・銀行振込のご案内に「3〜5営業日」「9月中」を直書きせず、`config('shipping.ship_days_text')` / `config('shipping.reservation_ship_period_text')` を差し込む（変更を1か所で済ませ、文言がずれないようにする）。

## 決済

- 支払方法の値は `card`（クレジットカード）、`customer_balance`（銀行振込）、`cod`（代金引換）。
- 代金引換は手数料 ¥330 を加算する。
- 商品価格と支払方法の仕様は `docs/02-initial-settings.md` を正とする。
