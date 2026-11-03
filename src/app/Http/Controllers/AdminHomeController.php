<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面のトップページ
 */
class AdminHomeController extends Controller
{
    /**
     * 管理画面のトップ（いまは見出しとログアウトだけの仮のページ）
     */
    public function index(): View
    {
        Log::debug('AdminHomeController start');

        $view = view('admin.index');

        Log::debug('AdminHomeController end');

        return $view;
    }
}
