<?php

namespace Tests\Unit;

use App\Support\MaterialFormState;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 資材一覧のモーダルの初期の状態（入力の誤り・419 で戻ったときだけ、入力を残して開く）のテスト
 */
class MaterialFormStateTest extends TestCase
{
    /**
     * 直前に送った入力（old）を用意する
     *
     * @param  array<string, mixed>  $input
     */
    private function oldInput(array $input): void
    {
        $session = $this->app->make('session.store');
        $session->put('_old_input', $input);
        request()->setLaravelSession($session);
    }

    /**
     * エラーを持った ViewErrorBag を作る
     *
     * @param  array<string, string>  $messages
     */
    private function errors(array $messages): ViewErrorBag
    {
        return (new ViewErrorBag)->put('default', new MessageBag($messages));
    }

    /**
     * 一覧を普通に開いたときは、モーダルは閉じていて、追加の空の状態（在庫数・基準値は0）
     */
    #[Test]
    public function 普通に開いたときはモーダルは閉じている(): void
    {
        $state = MaterialFormState::fromOld(new ViewErrorBag);

        $this->assertSame(
            ['formOpen' => false, 'mode' => 'create', 'id' => '', 'name' => '', 'stock' => '0', 'location' => '', 'threshold' => '0', 'showErrors' => false, 'invalid' => ['name' => false, 'stock' => false, 'location' => false, 'threshold' => false]],
            $state,
        );
    }

    /**
     * 追加の空の入力は、一覧を普通に開いたときの初期値と同じ（在庫数・基準値は0）
     */
    #[Test]
    public function 追加の空の入力は初期値と同じである(): void
    {
        $blank = MaterialFormState::blank();

        $this->assertSame(['mode' => 'create', 'id' => '', 'name' => '', 'stock' => '0', 'location' => '', 'threshold' => '0'], $blank);
        $initial = MaterialFormState::fromOld(new ViewErrorBag);
        foreach ($blank as $key => $value) {
            $this->assertSame($value, $initial[$key]);
        }
    }

    /**
     * 項目か form にエラーがあるときは、送った入力を残してモーダルを開く。old に id があれば修正
     */
    #[Test]
    public function 項目のエラーがあるときは入力を残して開く(): void
    {
        $this->oldInput(['name' => '箱', 'stock' => '-1', 'location' => '倉庫', 'threshold' => '3']);
        $state = MaterialFormState::fromOld($this->errors(['stock' => 'エラー']));

        $this->assertTrue($state['formOpen']);
        $this->assertTrue($state['showErrors']);
        $this->assertSame('create', $state['mode']);
        $this->assertSame(['箱', '-1', '倉庫', '3', ''], [$state['name'], $state['stock'], $state['location'], $state['threshold'], $state['id']]);
        $this->assertSame(['name' => false, 'stock' => true, 'location' => false, 'threshold' => false], $state['invalid']);

        $this->oldInput(['id' => '7', 'name' => '箱', 'stock' => '1', 'location' => '倉庫', 'threshold' => '3']);
        $state = MaterialFormState::fromOld($this->errors(['form' => '有効期限']));

        $this->assertTrue($state['formOpen']);
        $this->assertSame(['edit', '7'], [$state['mode'], $state['id']]);
    }

    /**
     * 資材が見つからない案内（material・delete）だけのときは、モーダルを開かず、入力も使わない
     */
    #[Test]
    public function 見つからない案内だけのときは開かない(): void
    {
        $this->oldInput(['name' => '箱']);

        $state = MaterialFormState::fromOld($this->errors(['material' => '資材が見つかりません。', 'delete' => '資材が見つかりません。']));

        $this->assertFalse($state['formOpen']);
        $this->assertSame('', $state['name']);
    }

    /**
     * 配列などの文字列にできない入力は、空文字にする
     */
    #[Test]
    public function 文字列でない入力は空にする(): void
    {
        $this->oldInput(['name' => ['a'], 'stock' => '1']);

        $state = MaterialFormState::fromOld($this->errors(['name' => 'エラー']));

        $this->assertSame('', $state['name']);
    }
}
