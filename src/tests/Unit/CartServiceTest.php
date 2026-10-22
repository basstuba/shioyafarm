<?php

namespace Tests\Unit;

use App\Enums\CartAddResult;
use App\Models\Item;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Services\CartService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * カートサービスのテスト（セッションへの保存と金額の計算）
 */
class CartServiceTest extends TestCase
{
    private Store $session;

    /** @var MockInterface&ItemRepositoryInterface */
    private MockInterface $repository;

    private CartService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new Store('test', new ArraySessionHandler(120));
        $this->repository = Mockery::mock(ItemRepositoryInterface::class);
        $this->service = new CartService($this->repository, $this->session);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 商品モデルを作る（DBは使わない）
     */
    private function makeItem(int $id, int $price, bool $reservedOnly = false): Item
    {
        $item = new Item(['name' => '商品'.$id, 'weight' => 2, 'price' => $price, 'is_reserved_only' => $reservedOnly]);
        $item->id = $id;

        return $item;
    }

    /**
     * 商品を追加するとセッションに `[商品ID => 1]` で保存される
     */
    #[Test]
    public function 商品を追加するとセッションに商品_i_dと数量で保存される(): void
    {
        $this->repository->shouldReceive('findById')->with(3)->andReturn($this->makeItem(3, 330));

        $result = $this->service->addItem(3);

        $this->assertSame(CartAddResult::Added, $result);
        $this->assertSame([3 => 1], $this->session->get('cart'));
    }

    /**
     * すでにある商品を追加すると数量が1増え、他の商品は変わらない
     */
    #[Test]
    public function すでにある商品を追加すると数量が1増える(): void
    {
        $this->session->put('cart', [3 => 2, 5 => 1]);
        $this->repository->shouldReceive('findById')->with(3)->andReturn($this->makeItem(3, 330));

        $this->service->addItem(3);

        $this->assertSame([3 => 3, 5 => 1], $this->session->get('cart'));
    }

    /**
     * 9個の商品を追加すると10個になり、10個の商品は追加できず数量も変わらない（境界値）
     */
    #[Test]
    public function 上限の10個を超えては追加できない(): void
    {
        $this->session->put('cart', [3 => 9]);
        $this->repository->shouldReceive('findById')->with(3)->andReturn($this->makeItem(3, 330));

        $this->assertSame(CartAddResult::Added, $this->service->addItem(3));
        $this->assertSame([3 => 10], $this->session->get('cart'));

        $this->assertSame(CartAddResult::LimitReached, $this->service->addItem(3));
        $this->assertSame([3 => 10], $this->session->get('cart'));
    }

    /**
     * 予約限定の商品は追加できない
     */
    #[Test]
    public function 予約限定の商品は追加できない(): void
    {
        $this->repository->shouldReceive('findById')->with(7)->andReturn($this->makeItem(7, 5000, true));

        $this->assertSame(CartAddResult::Rejected, $this->service->addItem(7));
        $this->assertNull($this->session->get('cart'));
    }

    /**
     * 存在しない商品は追加できない
     */
    #[Test]
    public function 存在しない商品は追加できない(): void
    {
        $this->repository->shouldReceive('findById')->with(99)->andThrow(new ModelNotFoundException);

        $this->assertSame(CartAddResult::Rejected, $this->service->addItem(99));
        $this->assertNull($this->session->get('cart'));
    }

    /**
     * 数量を変更できる。カートにない商品は追加されない
     */
    #[Test]
    public function 数量を変更できるがカートにない商品は追加されない(): void
    {
        $this->session->put('cart', [3 => 2]);

        $this->service->updateQuantity(3, 7);
        $this->service->updateQuantity(4, 5);

        $this->assertSame([3 => 7], $this->session->get('cart'));
    }

    /**
     * カートに入っている商品の数量を取得でき、カートにない商品は null になる
     */
    #[Test]
    public function 商品の数量を取得できる(): void
    {
        $this->session->put('cart', [3 => 2]);

        $this->assertSame(2, $this->service->getQuantity(3));
        $this->assertNull($this->service->getQuantity(4));
    }

    /**
     * 商品を削除できる。カートにない商品の削除はエラーにならない
     */
    #[Test]
    public function 商品を削除できる(): void
    {
        $this->session->put('cart', [3 => 2, 5 => 1]);

        $this->service->removeItem(3);
        $this->service->removeItem(99);

        $this->assertSame([5 => 1], $this->session->get('cart'));
    }

    /**
     * 行は商品ID順で、金額は単価×数量、小計は金額の合計になる
     */
    #[Test]
    public function 行の金額と小計を計算する(): void
    {
        $this->session->put('cart', [5 => 1, 3 => 2]);
        $this->repository->shouldReceive('findByIds')->with([3, 5])->once()->andReturn(new Collection([
            $this->makeItem(3, 330),
            $this->makeItem(5, 795),
        ]));

        $lines = $this->service->getLines();

        $this->assertSame([3, 5], $lines->map(fn ($line) => $line->item->id)->all());
        $this->assertSame([660, 795], $lines->pluck('amount')->all());
        $this->assertSame(1455, $this->service->calculateSubtotal($lines));
    }

    /**
     * カートが空のときは商品を取得せず、行も小計も0になる
     */
    #[Test]
    public function カートが空のときは行がなく小計は0になる(): void
    {
        $this->repository->shouldNotReceive('findByIds');

        $lines = $this->service->getLines();

        $this->assertTrue($lines->isEmpty());
        $this->assertSame(0, $this->service->calculateSubtotal($lines));
    }

    /**
     * DBに存在しない商品は行にも小計にも含めない
     */
    #[Test]
    public function 存在しない商品は行にも小計にも含めない(): void
    {
        $this->session->put('cart', [3 => 2, 99 => 4]);
        $this->repository->shouldReceive('findByIds')->with([3, 99])->andReturn(new Collection([$this->makeItem(3, 330)]));

        $lines = $this->service->getLines();

        $this->assertCount(1, $lines);
        $this->assertSame(660, $this->service->calculateSubtotal($lines));
    }

    /**
     * 予約限定の商品は行にも小計にも含めない
     */
    #[Test]
    public function 予約限定の商品は行にも小計にも含めない(): void
    {
        $this->session->put('cart', [3 => 2, 7 => 1]);
        $this->repository->shouldReceive('findByIds')->with([3, 7])->andReturn(new Collection([
            $this->makeItem(3, 330),
            $this->makeItem(7, 5000, true),
        ]));

        $lines = $this->service->getLines();

        $this->assertSame([3], $lines->map(fn ($line) => $line->item->id)->all());
        $this->assertSame(660, $this->service->calculateSubtotal($lines));
    }

    /**
     * 存在しない商品と予約限定の商品は、見つかったらセッションのカートから取り除く
     */
    #[Test]
    public function 存在しない商品と予約限定の商品はセッションから取り除く(): void
    {
        $this->session->put('cart', [3 => 2, 7 => 1, 99 => 4]);
        $this->repository->shouldReceive('findByIds')->with([3, 7, 99])->andReturn(new Collection([
            $this->makeItem(3, 330),
            $this->makeItem(7, 5000, true),
        ]));

        $this->service->getLines();

        $this->assertSame([3 => 2], $this->session->get('cart'));
    }

    /**
     * 取り除くものが無ければセッションは書き換えない
     */
    #[Test]
    public function 全部有効ならセッションを変えない(): void
    {
        $this->session->put('cart', [3 => 2]);
        $this->repository->shouldReceive('findByIds')->andReturn(new Collection([$this->makeItem(3, 330)]));

        $this->service->getLines();

        $this->assertSame([3 => 2], $this->session->get('cart'));
    }

    /**
     * セッションの値が不正でもエラーにせず、範囲外の数量は1〜10に丸める
     */
    #[Test]
    public function セッションの不正な値は取り除くか範囲に丸める(): void
    {
        $this->session->put('cart', ['abc' => 2, 0 => 1, 3 => 'x', 4 => 50, 5 => -2, 6 => 3]);
        $this->repository->shouldReceive('findByIds')->with([4, 5, 6])->andReturn(new Collection([
            $this->makeItem(4, 100),
            $this->makeItem(5, 100),
            $this->makeItem(6, 100),
        ]));

        $lines = $this->service->getLines();

        $this->assertSame([10, 1, 3], $lines->pluck('quantity')->all());

        $this->session->put('cart', 'broken');
        $this->assertTrue($this->service->getLines()->isEmpty());
    }

    /**
     * 購入した商品の数量だけがカートから減り、購入していない商品は残る
     */
    #[Test]
    public function 購入した商品の数量だけカートから減らす(): void
    {
        $this->session->put('cart', [3 => 5, 5 => 1, 7 => 2]);

        $this->service->reduceQuantities([3 => 2, 5 => 1]);

        // 3 は 5 - 2 = 3 個、5 は 0 個になって削除、7 は購入していないので残る
        $this->assertSame([3 => 3, 7 => 2], $this->session->get('cart'));
    }

    /**
     * 購入した数量がカートの数量以上なら削除する。カートにない商品は無視する
     */
    #[Test]
    public function 購入した数量がカート以上なら削除しカートにない商品は無視する(): void
    {
        $this->session->put('cart', [3 => 2, 7 => 4]);

        $this->service->reduceQuantities([3 => 5, 99 => 1]);

        $this->assertSame([7 => 4], $this->session->get('cart'));
    }

    /**
     * すべて購入するとカートは空になる
     */
    #[Test]
    public function 全部購入するとカートは空になる(): void
    {
        $this->session->put('cart', [3 => 2, 5 => 1]);

        $this->service->reduceQuantities([3 => 2, 5 => 1]);

        $this->assertSame([], $this->session->get('cart'));
    }
}
