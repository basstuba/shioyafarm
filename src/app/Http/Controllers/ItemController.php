<?php

namespace App\Http\Controllers;

use App\Services\ItemService;
use App\Services\ReservationLimitService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 商品コントローラ
 */
class ItemController extends Controller
{
    /**
     * 商品一覧ページを表示
     */
    public function index(ItemService $itemService): View
    {
        Log::debug('ItemController start');

        $itemGroups = $itemService->getItemsGroupedByName();

        Log::debug('ItemController end');

        return view('shop.items', compact('itemGroups'));
    }

    /**
     * 商品詳細ページを表示
     */
    public function show(int $item, ItemService $itemService, ReservationLimitService $reservationLimitService): View
    {
        Log::debug('ItemController start');

        $foundItem = $itemService->getItem($item);

        Log::debug('ItemController end');

        return view('shop.item_detail', [
            'item' => $foundItem,
            // 予約限定の商品が予約可能数に達していたら、「予約する」を「予約受付終了」にする
            'reservationClosed' => $foundItem->is_reserved_only && $reservationLimitService->isClosed($foundItem),
        ]);
    }
}
