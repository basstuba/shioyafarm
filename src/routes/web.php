<?php

use Illuminate\Support\Facades\Route;

// 送料案内
Route::get('/shipping-fee', function () {
    return view('shipping');
})->name('shipping-fee');

// 利用規約
Route::get('/terms-of-service', function () {
    return view('terms-of-service');
})->name('terms-of-service');

// プライバシーポリシー
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

// 特定商取引法に基づく表記
Route::get('/commercial-transaction', function () {
    return view('law');
})->name('commercial-transaction');