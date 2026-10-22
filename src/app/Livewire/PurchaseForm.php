<?php

namespace App\Livewire;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Http\Requests\CartRequest;
use App\Livewire\Concerns\WithDeliveryForm;
use App\Models\User;
use App\Services\AreaService;
use App\Services\CartService;
use App\Services\DeliveryAddressService;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * 購入ページの注文内容（数量・お届け先・支払方法・金額）と、住所追加モーダル
 *
 * 数量の変更・住所の切り替え・支払方法の選択で、ページを再読み込みせずに小計・送料・代引手数料・合計を更新する。
 * 「注文を確定する」は通常のフォーム送信（POST /payment）で、PurchaseRequest が検証する。
 * 検証エラーで戻ったときと、Stripe の決済ページから「戻る」で戻ったときは、入力していた内容を復元する。
 * 購入ページを開くたびに、二重送信を防ぐ注文トークンを発行してフォームに入れる（PurchaseRequest の order_token）。
 */
class PurchaseForm extends Component
{
    use WithDeliveryForm;

    /** 購入の入り口（cart: カートの中身、item: 商品詳細から1商品） */
    #[Locked]
    public string $source = 'cart';

    /** 商品詳細から購入する場合の商品ID */
    #[Locked]
    public ?int $itemId = null;

    /**
     * 購入ページを開いたときに読み込んだ商品ID（クライアントからは変更できない）
     *
     * 数量の入力欄に載せてよい商品は、これだけ。
     *
     * @var list<int>
     */
    #[Locked]
    public array $itemIds = [];

    /**
     * 商品ごとの数量（商品ID => 数量）
     *
     * 入力途中の不正な値（0・11など）もそのまま持つ。金額の計算では1〜10に収める。
     * キーは読み込んだ商品ID（itemIds）だけ。それ以外のキーが送られてきたら取り除く（updatedQuantities）。
     *
     * @var array<int, int|string>
     */
    public array $quantities = [];

    /**
     * 注文トークン（「注文を確定する」を1回しか受け付けないための、1回限りのトークン）
     *
     * 購入ページを開いたとき（mount）に1回だけ発行する。再描画では変えず、クライアントからも変更できない。
     */
    #[Locked]
    public string $orderToken = '';

    /**
     * 初期値を設定する
     *
     * 優先順位は、検証エラーで戻ったときの入力 > 「戻る」で戻った直後の保存待ちの注文 > 会員登録の内容。
     */
    public function mount(PurchaseService $purchaseService, DeliveryAddressService $addressService, ?int $itemId = null): void
    {
        $user = $this->user();
        $source = $itemId === null ? PurchaseSource::Cart : PurchaseSource::Item;

        $this->itemId = $itemId;
        $this->source = $source->value;
        $this->quantities = $purchaseService->initialQuantities($source, $itemId);
        $this->itemIds = array_keys($this->quantities);
        $this->fillDeliveryDefaults($user, $addressService);

        // 決済ページ（Stripe Checkout）や振込先のご案内の「戻る」で戻った直後（1回だけ）は、保存待ちの注文の入力を復元する
        $pending = session()->get(PurchaseService::RESTORE_FLASH_KEY) === true ? $purchaseService->getPendingOrder($user) : null;
        if ($pending !== null && $pending->matches($source, $itemId)) {
            $this->deliveryName = $pending->deliveryName;
            $this->deliveryTell = $pending->deliveryTell;
            $this->applyAddressId($user, $addressService, $pending->addressId);
            $this->paymentMethod = $pending->paymentMethod->value;
            if ($source === PurchaseSource::Item) {
                foreach ($pending->items as $item) {
                    $this->quantities[$item['item_id']] = $item['quantity'];
                }
            }
        }

        $this->restoreOldInput($user, $addressService);

        // 検証エラーで戻ったときは、使っていないトークンならそのまま使い回す。それ以外は新しく発行する
        $previousToken = session()->getOldInput('order_token');
        $this->orderToken = $purchaseService->issueOrderToken($user, is_string($previousToken) ? $previousToken : null);
    }

    /**
     * 数量が更新されたとき、読み込んだ商品以外のキーを取り除く
     *
     * クライアントが任意の商品IDを追加して、購入ページに表示していない商品の数量を送れないようにする。
     */
    public function updatedQuantities(): void
    {
        $this->quantities = array_intersect_key($this->quantities, array_flip($this->itemIds));
    }

    /**
     * 商品の数量を変更する
     *
     * CartRequest と同じルール・メッセージで検証する。エラーのときは数量を変えず、その商品の行にエラーを表示する。
     * カートから来た場合は、カートの数量も変える。
     */
    public function updateQuantity(CartService $cartService, int $itemId, mixed $quantity): void
    {
        $this->resetErrorBag("quantities.{$itemId}");

        if (! array_key_exists($itemId, $this->quantities)) {
            return;
        }

        $validator = Validator::make(
            ['quantity' => is_string($quantity) ? trim($quantity) : (is_int($quantity) ? $quantity : null)],
            ['quantity' => CartRequest::quantityRules()],
            CartRequest::quantityMessages(),
        );

        if ($validator->fails()) {
            $current = $this->quantities[$itemId];
            $message = $validator->errors()->first('quantity');
            if (is_int($current) && $current >= CartService::MIN_QUANTITY && $current <= CartService::MAX_QUANTITY) {
                $message .= "（数量は{$current}個のままです）";
            }
            $this->addError("quantities.{$itemId}", $message);

            return;
        }

        $valid = (int) $validator->validated()['quantity'];
        $this->quantities[$itemId] = $valid;
        if ($this->source === PurchaseSource::Cart->value) {
            $cartService->updateQuantity($itemId, $valid);
        }
    }

    public function render(PurchaseService $purchaseService, DeliveryAddressService $addressService, AreaService $areaService): View
    {
        $user = $this->user();

        // 改ざんされた値・他人の住所のときは、会員登録の住所に戻す
        $address = $this->resolveAddress($user, $addressService);

        $lines = $purchaseService->buildLines($this->numericQuantities());
        $method = PaymentMethod::tryFrom($this->paymentMethod);

        // 送料の地域が見つからない都道府県（設定にない住所）のときは、描画でエラーにせず、住所欄にエラーを出して注文を確定できなくする
        $shippingError = null;
        try {
            $summary = $purchaseService->summarize($lines, $address['prefecture'], $method);
        } catch (InvalidArgumentException) {
            $summary = $purchaseService->summarize($lines, null, $method);
            $shippingError = 'このお届け先住所は送料を計算できません。別の住所を選択してください';
        }

        return view('livewire.purchase-form', [
            'lines' => $lines,
            'summary' => $summary,
            'shippingError' => $shippingError,
            'addressOptions' => $addressService->getOptions($user),
            'confirmAddress' => $address['prefecture'].$address['city'],
            'prefectures' => $areaService->getPrefectures(),
            'paymentOptions' => self::paymentOptions(),
            'minQuantity' => CartService::MIN_QUANTITY,
            'maxQuantity' => CartService::MAX_QUANTITY,
        ]);
    }

    /**
     * 支払方法の選択肢（値・表示名・説明・アイコン）
     *
     * @return list<array{value: string, label: string, description: string, icon: string}>
     */
    public static function paymentOptions(): array
    {
        return [
            ['value' => PaymentMethod::Card->value, 'label' => 'クレジットカード', 'description' => 'Visa / Master / JCB', 'icon' => 'image/icon/credit-card-icon.webp'],
            ['value' => PaymentMethod::CustomerBalance->value, 'label' => '銀行振込', 'description' => '銀行口座にお振込み', 'icon' => 'image/icon/bank-icon.webp'],
            ['value' => PaymentMethod::Cod->value, 'label' => '代金引換', 'description' => '受け取り時にお支払い', 'icon' => 'image/icon/box-icon.webp'],
        ];
    }

    /**
     * 数量を金額計算用の整数にする（数字でない値は0とし、計算側で1〜10に収める）
     *
     * @return array<int, int>
     */
    private function numericQuantities(): array
    {
        return array_map(fn (int|string $quantity): int => is_numeric($quantity) ? (int) $quantity : 0, $this->quantities);
    }

    /**
     * 検証エラー（または住所追加）で戻ったときの入力を復元する
     */
    private function restoreOldInput(User $user, DeliveryAddressService $addressService): void
    {
        /** @var array<string, mixed> $oldInput */
        $oldInput = session()->getOldInput();

        $this->restoreDeliveryOldInput($user, $addressService, $oldInput);

        $rows = $oldInput['items'] ?? null;
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row) || ! isset($row['item_id']) || ! is_scalar($row['item_id']) || ! array_key_exists('quantity', $row)) {
                    continue;
                }
                // 数量を空欄で送信した場合も、空欄のまま復元する
                $id = (int) $row['item_id'];
                if (array_key_exists($id, $this->quantities)) {
                    $this->quantities[$id] = $this->restoredQuantity($row['quantity']);
                }
            }
        }
    }
}
