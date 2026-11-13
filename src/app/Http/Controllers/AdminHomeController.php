<?php

namespace App\Http\Controllers;

use App\Services\AdminMaterialService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面のトップページ
 */
class AdminHomeController extends Controller
{
    /**
     * 管理画面のトップ（各画面へのリンクの2枚のカード。ヘッダー・フッターはトップ専用のレイアウト）
     *
     * 残りわずかの資材が1件以上あるときは、カードの上にその件数と資材一覧へのリンクを出す（件数はビューに渡す）。
     */
    public function index(AdminMaterialService $materialService): View
    {
        Log::debug('AdminHomeController start');

        $view = view('admin.index', ['lowMaterialCount' => $materialService->countLow()]);

        Log::debug('AdminHomeController end');

        return $view;
    }
}
