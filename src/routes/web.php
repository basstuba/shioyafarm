<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ItemController;
use Illuminate\Support\Facades\Route;

// トップページ
Route::get('/', HomeController::class)->name('top.index');

// 商品一覧
Route::get('/items', [ItemController::class, 'index'])->name('shop.items.index');

// 商品詳細
Route::get('/items/{item}', [ItemController::class, 'show'])->name('shop.items.show');

// カート（未ログインはログインページへ。ログイン後にカートへ戻る）
Route::get('/cart', [CartController::class, 'index'])->middleware('auth')->name('shop.cart');

// お問い合わせフォーム
Route::get('/inquiry', [InquiryController::class, 'create'])->name('top.inquiry');
Route::post('/inquiry/create', [InquiryController::class, 'store'])->middleware('throttle:inquiry')->name('top.inquiry.store');
Route::get('/inquiry/thanks', [InquiryController::class, 'thanks'])->name('top.inquiry.thanks');

// 送料案内
Route::get('/shipping-fee', function () {
    return view('shipping');
})->name('info.shipping-fee');

// 利用規約
Route::get('/terms-of-service', function () {
    return view('terms_of_service');
})->name('info.terms-of-service');

// プライバシーポリシー
Route::get('/privacy-policy', function () {
    return view('privacy_policy');
})->name('info.privacy-policy');

// 特定商取引法に基づく表記
Route::get('/commercial-transaction', function () {
    return view('law');
})->name('info.commercial-transaction');
