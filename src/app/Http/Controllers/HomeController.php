<?php

namespace App\Http\Controllers;

use App\Services\HomeService;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

/**
 * トップページ表示コントローラ
 */
class HomeController extends Controller
{
    public function __invoke(HomeService $homeService): View
    {
        Log::debug('HomeController start');

        $notices = $homeService->getNoticeList();

        $recommendItems = $homeService->getRecommendItems();

        Log::debug('HomeController end');

        return view('index', compact('notices', 'recommendItems'));
    }
}
