<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithClampedPagination;
use App\Livewire\Concerns\WithOrderDetailModal;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Models\User;
use App\Services\OrderDetail;
use App\Services\OrderHistoryService;
use App\Support\LoginUser;
use Illuminate\View\View;
use Livewire\Component;

/**
 * マイページの購入履歴（5件ずつのページ送り）と、詳細モーダル
 *
 * 一覧も詳細も、ログイン中の会員の購入履歴だけを取得する。
 */
class MyPagePurchaseHistory extends Component
{
    use WithClampedPagination;
    use WithOrderDetailModal;

    /** ページ番号の名前（お届け先住所・予約履歴とは別のページ番号にする） */
    public const string PAGE_NAME = 'purchasePage';

    protected function findDetail(OrderHistoryService $historyService, User $user, int $id): ?OrderDetail
    {
        return $historyService->findPurchaseDetail($user, $id);
    }

    public function render(OrderHistoryService $historyService): View
    {
        $user = LoginUser::require();
        $purchases = $this->loadClamped(fn () => $historyService->paginatePurchases($user, self::PAGE_NAME), self::PAGE_NAME);

        $rows = $purchases->getCollection()->map(fn (Purchase_history $purchase): array => [
            'id' => $purchase->id,
            'date' => $purchase->created_at?->format('Y/m/d'),
            'names' => $purchase->purchaseHistoryItems
                ->map(fn (Purchase_history_item $line): string => $line->item->display_name)
                ->all(),
            'total' => $purchase->total_price,
        ]);

        return view('livewire.my-page-purchase-history', [
            'purchases' => $purchases,
            'rows' => $rows,
            'detail' => $this->currentDetail($historyService, $user),
        ]);
    }
}
