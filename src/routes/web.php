<?php

use App\Http\Controllers\AddAddressController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminExportController;
use App\Http\Controllers\AdminHomeController;
use App\Http\Controllers\AdminPasswordResetController;
use App\Http\Controllers\AdminPurchaseController;
use App\Http\Controllers\AdminReservationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MyPageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReservationController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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
    // 購入完了（クレジットカード専用：Stripe の success_url。/purchase/{item?} に吸われないよう、先に定義する）
    Route::get('/purchase/success', [PurchaseController::class, 'purchaseSuccess'])->name('shop.checkout.success');
    // 銀行振込の振込先のご案内（/purchase/{item?} より前に定義する）と、その「戻る」（PaymentIntent をキャンセルして購入ページへ）
    Route::get('/purchase/bank-transfer', [PurchaseController::class, 'bankTransfer'])->name('shop.checkout.bank-transfer');
    Route::post('/purchase/bank-transfer/cancel', [PurchaseController::class, 'cancelBankTransfer'])->name('shop.checkout.bank-transfer.cancel');
    // 銀行振込の「振込み」（POST：PaymentIntent を確認して購入データを保存し、サンクスページへ）と、そのサンクスページ（銀行振込・代金引換。保存直後の1回だけ表示）
    Route::post('/purchase/bank-transfer/confirm', [PurchaseController::class, 'confirmBankTransfer'])->name('shop.checkout.bank-transfer.confirm');
    Route::get('/purchase/thanks', [PurchaseController::class, 'thanks'])->name('shop.checkout.thanks');
    // 購入ページ（商品IDなしはカートの中身、商品IDありはその商品1個から）
    // 商品IDは先頭が0でない18桁までの数字だけ（それ以外は404。グローバルな Route::pattern に頼らず、ここでも明示する）
    Route::get('/purchase/{item?}', [PurchaseController::class, 'index'])->where('item', '[1-9][0-9]{0,17}')->name('shop.checkout.index');
    // 注文の確定（カード：決済ページへ、銀行振込：振込先のご案内へ、代金引換：購入データを保存してサンクスページへ）
    // 同じセッションの同時リクエストは順番に処理する（後のリクエストが古いセッションを書き戻して、カートの減算や保存待ちの注文を上書きしない）
    Route::post('/payment', [PaymentController::class, 'payment'])
        ->block((int) config('purchase.begin_lock_ttl'), (int) config('purchase.session_lock_wait'))
        ->name('shop.checkout.payment');
    // 配送先住所の追加
    Route::post('/add-address', [AddAddressController::class, 'store'])->name('shop.checkout.add-address');

    // 予約（予約限定の商品1つを先払いで予約する。購入と同じ流れ）
    // 固定のパス（success, bank-transfer, thanks, payment）は /reservation/{item} より前に定義する
    // 予約完了（クレジットカード専用：Stripe の success_url）
    Route::get('/reservation/success', [ReservationController::class, 'reservationSuccess'])->name('shop.reservation.success');
    // 銀行振込の振込先のご案内と、その「戻る」（PaymentIntent をキャンセルして予約ページへ）
    Route::get('/reservation/bank-transfer', [ReservationController::class, 'bankTransfer'])->name('shop.reservation.bank-transfer');
    Route::post('/reservation/bank-transfer/cancel', [ReservationController::class, 'cancelBankTransfer'])->name('shop.reservation.bank-transfer.cancel');
    // 銀行振込の「振込み」（POST：PaymentIntent を確認して予約データを保存し、サンクスページへ）と、そのサンクスページ（保存直後の1回だけ表示）
    Route::post('/reservation/bank-transfer/confirm', [ReservationController::class, 'confirmBankTransfer'])->name('shop.reservation.bank-transfer.confirm');
    Route::get('/reservation/thanks', [ReservationController::class, 'thanks'])->name('shop.reservation.thanks');
    // 予約の確定（カード：決済ページへ、銀行振込：振込先のご案内へ）。同じセッションの同時リクエストは順番に処理する（購入の /payment と同じ）
    Route::post('/reservation/payment', [ReservationController::class, 'payment'])
        ->block((int) config('purchase.begin_lock_ttl'), (int) config('purchase.session_lock_wait'))
        ->name('shop.reservation.payment');
    // 予約ページ（予約限定の商品のみ。商品IDは先頭が0でない18桁までの数字）
    Route::get('/reservation/{item}', [ReservationController::class, 'index'])->where('item', '[1-9][0-9]{0,17}')->name('shop.reservation.index');
});

// マイページ（未ログインはログインページへ。ログイン後に開こうとしたページへ戻る）
Route::middleware('auth')->group(function () {
    // マイページ（登録情報・お届け先住所・購入履歴・予約履歴・退会手続き）
    Route::get('/mypage', [MyPageController::class, 'index'])->name('shop.mypage.index');
    // 登録情報変更ページ
    Route::get('/mypage/edit', [MyPageController::class, 'edit'])->name('shop.mypage.edit');
    // 登録情報の更新
    Route::post('/mypage/update', [MyPageController::class, 'update'])->name('shop.mypage.update');
    // 退会（論理削除）
    Route::delete('/mypage/delete', [MyPageController::class, 'delete'])->name('shop.mypage.delete');
});

// お問い合わせフォーム
Route::get('/inquiry', [InquiryController::class, 'create'])->name('top.inquiry');
Route::post('/inquiry/create', [InquiryController::class, 'store'])->middleware('throttle:inquiry')->name('top.inquiry.store');
Route::get('/inquiry/thanks', [InquiryController::class, 'thanks'])->name('top.inquiry.thanks');

// 送料案内
Route::view('/shipping-fee', 'shipping')->name('info.shipping-fee');

// 利用規約
Route::view('/terms-of-service', 'terms_of_service')->name('info.terms-of-service');

// プライバシーポリシー
Route::view('/privacy-policy', 'privacy_policy')->name('info.privacy-policy');

// 特定商取引法に基づく表記
Route::view('/commercial-transaction', 'law')->name('info.commercial-transaction');

// 管理画面（会員の認証とは別の admin ガード）
Route::prefix('admin')->group(function () {
    // ログイン（管理者としてログイン中なら /admin へ。試行回数は同じメールアドレスで1分5回まで）
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'create'])->name('admin.login');
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login')->name('admin.login.store');

        // パスワード再設定（会員の再設定とは別の仕組み。送信は IP アドレスあたり1分5回まで。会員のものと同じ password-reset の上限で、ルートごとに数える）
        Route::get('/forgot-password', [AdminPasswordResetController::class, 'create'])->name('admin.password.request');
        Route::post('/forgot-password', [AdminPasswordResetController::class, 'store'])->middleware('throttle:password-reset')->name('admin.password.email');
        Route::get('/reset-password/{token}', [AdminPasswordResetController::class, 'edit'])->name('admin.password.reset');
        Route::post('/reset-password', [AdminPasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('admin.password.update');
    });

    // 未ログインは /admin/login へ（会員の /login ではない。bootstrap/app.php）。
    // 応答はブラウザに保存させない（no-store。ログアウト後に戻るボタンで管理画面が表示されないようにする）
    Route::middleware(['auth:admin', 'no-store'])->group(function () {
        Route::get('/', [AdminHomeController::class, 'index'])->name('admin.home');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

        // 注文一覧（検索・発送済みの更新。詳細のモーダルは Livewire の AdminPurchaseDetail）
        Route::get('/purchase', [AdminPurchaseController::class, 'index'])->name('admin.purchase.index');
        Route::get('/purchase/search', [AdminPurchaseController::class, 'search'])->name('admin.purchase.search');
        Route::post('/purchase/update', [AdminPurchaseController::class, 'update'])->name('admin.purchase.update');

        // 予約一覧（検索・発送済みの更新。詳細のモーダルは Livewire の AdminReservationDetail）
        Route::get('/reservations', [AdminReservationController::class, 'index'])->name('admin.reservations.index');
        Route::get('/reservations/search', [AdminReservationController::class, 'search'])->name('admin.reservations.search');
        Route::post('/reservations/update', [AdminReservationController::class, 'update'])->name('admin.reservations.update');

        // エクスポート（type で種類を指定する。注文一覧のほか、予約一覧でも使う）
        Route::post('/export', [AdminExportController::class, 'export'])->name('admin.export');
    });
});

// 存在しないURL（404）。定義のないURLは web ミドルウェアを通らずセッションが使えず、ログイン中でも未ログインのヘッダーになるため、
// fallback で web ミドルウェアを通してから 404 を返す（errors/404.blade.php が描画される）。ナビは top.* と同じ。
// - Route::fallback() は GET だけに一致し、存在しないURLへの POST などが 405 になるため、OPTIONS 以外のメソッドで受ける
//   （OPTIONS は Laravel が既存のルートに Allow ヘッダーで答えるのに任せる）
// - 404 を返すだけなので CSRF の確認は外す（外さないと、トークンなしの POST が 404 ではなく 419 になる）
// - POST専用のURLを GET で開いたときも、405 ではなくこの 404 になる
Route::match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], '{fallbackPlaceholder}', function () {
    abort(404);
})->where('fallbackPlaceholder', '.*')->fallback()->withoutMiddleware(ValidateCsrfToken::class)->name('top.not-found');
