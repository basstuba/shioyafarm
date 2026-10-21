<?php

use App\Http\Controllers\AddAddressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\Route;

// トップページ
Route::get('/', HomeController::class)->name('top.index');

// 商品一覧
Route::get('/items', [ItemController::class, 'index'])->name('shop.items.index');

// 商品詳細
Route::get('/items/{item}', [ItemController::class, 'show'])->name('shop.items.show');

// カート（未ログインはログインページへ。ログイン後にカートへ戻る）
Route::get('/cart', [CartController::class, 'index'])->middleware('auth')->name('shop.cart');

// 購入（未ログインはログインページへ。ログイン後に購入ページへ戻る）
Route::middleware('auth')->group(function () {
    // 購入完了（/purchase/{item?} に吸われないよう、先に定義する）
    Route::get('/purchase/success', [PurchaseController::class, 'purchaseSuccess'])->name('shop.checkout.success');
    // 銀行振込の振込先のご案内（/purchase/{item?} より前に定義する）と、その「戻る」（PaymentIntent をキャンセルして購入ページへ）
    Route::get('/purchase/bank-transfer', [PurchaseController::class, 'bankTransfer'])->name('shop.checkout.bank-transfer');
    Route::post('/purchase/bank-transfer/cancel', [PurchaseController::class, 'cancelBankTransfer'])->name('shop.checkout.bank-transfer.cancel');
    // 銀行振込の「振込み」（POST：PaymentIntent を確認して購入データを保存し、サンクスページへ）と、そのサンクスページ（保存直後の1回だけ表示）
    Route::post('/purchase/bank-transfer/confirm', [PurchaseController::class, 'confirmBankTransfer'])->name('shop.checkout.bank-transfer.confirm');
    Route::get('/purchase/thanks', [PurchaseController::class, 'thanks'])->name('shop.checkout.thanks');
    // 購入ページ（商品IDなしはカートの中身、商品IDありはその商品1個から）
    // 商品IDは先頭が0でない18桁までの数字だけ（それ以外は404。グローバルな Route::pattern に頼らず、ここでも明示する）
    Route::get('/purchase/{item?}', [PurchaseController::class, 'index'])->where('item', '[1-9][0-9]{0,17}')->name('shop.checkout.index');
    // 注文の確定（決済ページへ移動）
    Route::post('/payment', [PaymentController::class, 'payment'])->name('shop.checkout.payment');
    // 配送先住所の追加
    Route::post('/add-address', [AddAddressController::class, 'store'])->name('shop.checkout.add-address');
});

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
