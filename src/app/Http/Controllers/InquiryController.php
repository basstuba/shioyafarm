<?php

namespace App\Http\Controllers;

use App\Http\Requests\InquiryRequest;
use App\Services\InquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * お問い合わせコントローラ
 */
class InquiryController extends Controller
{
    /** サンクスページを「送信直後だけ」表示するためのセッションのフラッシュキー */
    private const string SUBMITTED_KEY = 'inquiry_submitted';

    /**
     * お問い合わせフォームページ表示
     */
    public function create(): View
    {
        Log::debug('InquiryController create start');

        Log::debug('InquiryController create end');

        return view('inquiry');
    }

    /**
     * お問い合わせを保存してサンクスページへ移動
     */
    public function store(InquiryRequest $request, InquiryService $inquiryService): RedirectResponse
    {
        Log::debug('InquiryController store start');

        /** @var array{last_name: string, first_name: string, email: string, detail: string} $data */
        $data = $request->validated();
        $inquiryService->createInquiry($data);

        Log::debug('InquiryController store end');

        return redirect()->route('top.inquiry.thanks')->with(self::SUBMITTED_KEY, true);
    }

    /**
     * お問い合わせサンクスページ表示
     *
     * 送信直後（フラッシュあり）だけ表示する。直接開いた場合や再読み込みした場合はフォームへ戻す。
     */
    public function thanks(): View|RedirectResponse
    {
        Log::debug('InquiryController thanks start');

        if (! session()->has(self::SUBMITTED_KEY)) {
            Log::debug('InquiryController thanks end (redirect)');

            return redirect()->route('top.inquiry');
        }

        Log::debug('InquiryController thanks end');

        return view('inquiry_thanks');
    }
}
