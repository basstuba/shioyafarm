<?php

namespace Tests\Unit;

use App\Http\Requests\Concerns\ValidatesRecordId;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 削除・修正の入力のID（id）の検証の共通処理（ValidatesRecordId）のテスト
 */
class ValidatesRecordIdTest extends TestCase
{
    /**
     * トレイトの protected メソッドを呼べるようにした、検証用のクラス
     */
    private function subject(): object
    {
        return new class
        {
            use ValidatesRecordId {
                idRules as public;
                idMessages as public;
                redirectToIndexIfIdInvalid as public;
            }
        };
    }

    /**
     * IDの規則は、必須・1以上の整数
     *
     * @param  array<string, mixed>  $input
     */
    #[Test]
    #[DataProvider('ids')]
    public function 対象の識別子の規則は必須で1以上の整数(array $input, bool $valid): void
    {
        $validator = Validator::make($input, ['id' => $this->subject()->idRules()]);

        $this->assertSame($valid, $validator->passes());
    }

    /**
     * 入力と、規則を満たすか
     *
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function ids(): array
    {
        return [
            '1' => [['id' => '1'], true],
            '大きな数' => [['id' => '999999'], true],
            '未入力' => [[], false],
            '0' => [['id' => '0'], false],
            '負の数' => [['id' => '-1'], false],
            '小数' => [['id' => '1.5'], false],
            '文字' => [['id' => 'abc'], false],
        ];
    }

    /**
     * どの規則に外れても、同じ「見つかりません」の文言になる
     */
    #[Test]
    public function どの規則でも同じ文言になる(): void
    {
        $this->assertSame([
            'id.required' => 'お知らせが見つかりません。',
            'id.integer' => 'お知らせが見つかりません。',
            'id.min' => 'お知らせが見つかりません。',
        ], $this->subject()->idMessages('お知らせが見つかりません。'));
    }

    /**
     * IDのエラーがあるときは、一覧へ戻して案内を指定の名前の errors に入れる
     */
    #[Test]
    public function 識別子のエラーがあるときは一覧へ戻して案内を出す(): void
    {
        $validator = Validator::make(['id' => 'abc'], ['id' => $this->subject()->idRules()]);

        try {
            $this->subject()->redirectToIndexIfIdInvalid($validator, 'admin.items.index', 'item', '商品が見つかりません。');
            $this->fail('一覧へ戻す応答の例外が投げられるはず');
        } catch (HttpResponseException $e) {
            $this->assertSame(route('admin.items.index'), $e->getResponse()->headers->get('Location'));
            $errors = session('errors');
            $this->assertNotNull($errors);
            $this->assertSame('商品が見つかりません。', $errors->first('item'));
        }
    }

    /**
     * IDのエラーがなければ（ほかの項目のエラーだけなら）、何もしない
     */
    #[Test]
    public function 識別子のエラーがなければ何もしない(): void
    {
        $validator = Validator::make(['id' => '1', 'name' => ''], ['id' => $this->subject()->idRules(), 'name' => 'required']);
        $this->assertTrue($validator->fails());

        $this->subject()->redirectToIndexIfIdInvalid($validator, 'admin.items.index', 'item', '商品が見つかりません。');

        $this->addToAssertionCount(1);
    }
}
