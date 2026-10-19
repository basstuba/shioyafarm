<?php

namespace App\Http\Controllers;

use App\Services\ItemService;
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
}
