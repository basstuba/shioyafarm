<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * カートコントローラ
 */
class CartController extends Controller
{
    /**
     * カートページを表示
     *
     * 商品の一覧・数量変更・削除は Livewire の cart-list コンポーネントが担当する。
     */
    public function index(): View
    {
        Log::debug('CartController start');

        Log::debug('CartController end');

        return view('shop.cart');
    }
}
