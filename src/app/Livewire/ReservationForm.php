<?php

namespace App\Livewire;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Http\Requests\CartRequest;
use App\Livewire\Concerns\WithDeliveryForm;
use App\Models\Item;
use App\Services\AreaService;
use App\Services\CartService;
use App\Services\DeliveryAddressService;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * 予約ページの予約内容（数量・お届け先・支払方法・金額）と、住所追加モーダル
 *
 * 数量の変更・住所の切り替え・支払方法の選択で、ページを再読み込みせずに小計・送料・合計を更新する。
 * 「予約を確定する」は通常のフォーム送信（POST /reservation/payment）で、ReservationRequest が検証する。
 * 検証エラーで戻ったときと、Stripe の決済ページから「戻る」で戻ったときは、入力していた内容を復元する。
 * 予約ページを開くたびに、二重送信を防ぐ注文トークンを発行してフォームに入れる（ReservationRequest の order_token）。
 * 予約はカートを使わず、予約限定の商品1つだけを扱う。
 *
 * @property-read Item $item
 * @property-read int $maxQuantity
 */
class ReservationForm extends Component
{
    use WithDeliveryForm;

    /** 予約する商品ID（クライアントからは変更できない） */
    #[Locked]
    public int $itemId = 0;

    /**
     * 数量
     *
     * 入力途中の不正な値（0・11など）もそのまま持つ。金額の計算では1〜10に収める。
     */
    public int|string $quantity = CartService::MIN_QUANTITY;

    /**
     * 注文トークン（「予約を確定する」を1回しか受け付けないための、1回限りのトークン）
     *
     * 予約ページを開いたとき（mount）に1回だけ発行する。再描画では変えず、クライアントからも変更できない。
     */
    #[Locked]
    public string $orderToken = '';

    /**
     * 初期値を設定する
     *
     * 優先順位は、検証エラーで戻ったときの入力 > 「戻る」で戻った直後の保存待ちの予約 > 会員登録の内容。
     */
    public function mount(ReservationService $reservationService, DeliveryAddressService $addressService, int $itemId): void
    {
        $user = $this->user();
        $item = $reservationService->findReservableItem($itemId);

        $this->itemId = $item->id;
        $this->fillDeliveryDefaults($user, $addressService);

        // 決済ページ（Stripe Checkout）や振込先のご案内の「戻る」で戻った直後（1回だけ）は、保存待ちの予約の入力を復元する
        $pending = session()->get(ReservationService::RESTORE_FLASH_KEY) === true ? $reservationService->getPendingOrder($user) : null;
        if ($pending !== null && $pending->matches(PurchaseSource::Item, $item->id)) {
            $this->deliveryName = $pending->deliveryName;
            $this->deliveryTell = $pending->deliveryTell;
            $this->applyAddressId($user, $addressService, $pending->addressId);
            $this->paymentMethod = $pending->paymentMethod->value;
            $this->quantity = $pending->items[0]['quantity'];
        }

        /** @var array<string, mixed> $oldInput */
        $oldInput = session()->getOldInput();
        $this->restoreDeliveryOldInput($user, $addressService, $oldInput);
        if (array_key_exists('quantity', $oldInput)) {
            // 数量を空欄で送信した場合も、空欄のまま復元する
            $this->quantity = $this->restoredQuantity($oldInput['quantity']);
        }

        // 予約で選べない支払方法（代金引換）は選択済みにしない
        if (! in_array($this->paymentMethod, PaymentMethod::reservationValues(), true)) {
            $this->paymentMethod = PaymentMethod::Card->value;
        }

        // 検証エラーで戻ったときは、使っていないトークンならそのまま使い回す。それ以外は新しく発行する
        $previousToken = session()->getOldInput('order_token');
        $this->orderToken = $reservationService->issueOrderToken($user, is_string($previousToken) ? $previousToken : null);
    }

    /**
     * 数量を変更する
     *
     * 1〜10の整数で、予約可能数の残りを超えない値だけ受け付ける（予約を確定するときにもサーバー側で確認する）。
     * エラーのときは数量を変えず、数量の欄にエラーを表示する。
     */
    public function updateQuantity(mixed $quantity): void
    {
        $this->resetErrorBag('quantity');

        $max = $this->maxQuantity;
        $messages = CartRequest::quantityMessages();
        $messages['quantity.max'] = $max < CartService::MAX_QUANTITY
            ? "予約可能数を超えています（残り{$max}個）"
            : $messages['quantity.max'];

        $validator = Validator::make(
            ['quantity' => is_string($quantity) ? trim($quantity) : (is_int($quantity) ? $quantity : null)],
            ['quantity' => 'required|integer|min:'.CartService::MIN_QUANTITY.'|max:'.$max],
            $messages,
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first('quantity');
            if (is_int($this->quantity) && $this->quantity >= CartService::MIN_QUANTITY && $this->quantity <= $max) {
                $message .= "（数量は{$this->quantity}個のままです）";
            }
            $this->addError('quantity', $message);

            return;
        }

        $this->quantity = (int) $validator->validated()['quantity'];
    }

    public function render(ReservationService $reservationService, DeliveryAddressService $addressService, AreaService $areaService): View
    {
        $user = $this->user();
        $item = $this->item;

        // 改ざんされた値・他人の住所のときは、会員登録の住所に戻す
        $address = $this->resolveAddress($user, $addressService);

        $quantity = $reservationService->clampQuantity(is_numeric($this->quantity) ? (int) $this->quantity : 0);

        // 送料の地域が見つからない都道府県（設定にない住所）のときは、描画でエラーにせず、住所欄にエラーを出して予約を確定できなくする
        $shippingError = null;
        try {
            $summary = $reservationService->summarize($item, $quantity, $address['prefecture']);
        } catch (InvalidArgumentException) {
            $summary = $reservationService->summarize($item, $quantity, null);
            $shippingError = 'このお届け先住所は送料を計算できません。別の住所を選択してください';
        }

        return view('livewire.reservation-form', [
            'item' => $item,
            'summary' => $summary,
            'shippingError' => $shippingError,
            'addressOptions' => $addressService->getOptions($user),
            'confirmAddress' => $address['prefecture'].$address['city'],
            'prefectures' => $areaService->getPrefectures(),
            'paymentOptions' => self::paymentOptions(),
            'minQuantity' => CartService::MIN_QUANTITY,
            'maxQuantity' => max(CartService::MIN_QUANTITY, $this->maxQuantity),
        ]);
    }

    /**
     * 支払方法の選択肢（値・表示名・説明・アイコン）。予約は代金引換を選べない
     *
     * @return list<array{value: string, label: string, description: string, icon: string}>
     */
    public static function paymentOptions(): array
    {
        return array_values(array_filter(
            PurchaseForm::paymentOptions(),
            fn (array $option): bool => in_array($option['value'], PaymentMethod::reservationValues(), true),
        ));
    }

    /**
     * 予約する商品（予約限定でなくなっていたときは404）
     *
     * 1回のリクエストの中では1回だけ取得する（数量の検証と描画で重複して問い合わせない）。
     */
    #[Computed]
    public function item(): Item
    {
        return app(ReservationService::class)->findReservableItem($this->itemId);
    }

    /**
     * 数量の入力の上限（10と残数の小さい方。残数の集計もリクエストの中で1回だけ行う）
     */
    #[Computed]
    public function maxQuantity(): int
    {
        return app(ReservationService::class)->maxQuantity($this->item);
    }
}
