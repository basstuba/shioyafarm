---
paths:
  - "src/tests/**"
---

# テストの規約

## 書き方

- テストメソッドには `test_` 接頭辞を付けず、`#[Test]` アトリビュートを付ける（`use PHPUnit\Framework\Attributes\Test;`）。
- メソッド名は日本語で、何を確認するかがわかる名前にする（例：`おすすめ商品は3件表示される()`）。
- クラスとメソッドには日本語のdocblockを付ける。

```php
use PHPUnit\Framework\Attributes\Test;

/**
 * おすすめ商品はおすすめのものだけをID順に3件表示する
 */
#[Test]
public function おすすめ商品は3件表示される(): void
{
    // ...
}
```

## 配置とデータ

- ルート単位の動作確認は `tests/Feature/`、Serviceなどのロジック単体は `tests/Unit/` に置く。Featureテストは機能ごとに1クラス（例：`ItemIndexTest`）にする。
- DBを使うテストは `RefreshDatabase` を使い、データは Factory で作る。Seederの内容に依存しない。
- URLは直書きせず `route('...')` で組み立てる。

## 最低限書くテスト

1つのルートにつき、該当するものはすべて書く。

- 正常に表示・処理される（ステータス、使うView、Viewに渡すデータ）
- バリデーションエラーになる（FormRequestがあるルート）
- 未ログインの場合はリダイレクトされる（認証が必要なルート）
- 境界値（件数の上限、並び順、0件のときの表示など）
- 金額の計算（送料・代引手数料・合計など）は、`config('shipping.*')` の値を使って期待値を組み立てる
