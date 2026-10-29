<?php

namespace App\Http\Controllers;

use App\Services\HomeService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * トップページ表示コントローラ
 */
class HomeController extends Controller
{
    /**
     * トップページを表示する（お知らせ・おすすめ商品）
     */
    public function __invoke(HomeService $homeService): View
    {
        Log::debug('HomeController start');

        $notices = $homeService->getNoticeList();

        $recommendItems = $homeService->getRecommendItems();

        Log::debug('HomeController end');

        return view('index', compact('notices', 'recommendItems'));
    }
}
