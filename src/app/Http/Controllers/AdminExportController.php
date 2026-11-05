<?php

namespace App\Http\Controllers;

use App\Enums\ExportType;
use App\Exports\PurchaseExport;
use App\Http\Requests\ExportRequest;
use App\Services\AdminPurchaseService;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * 管理画面のエクスポート（.xlsx のダウンロード）
 *
 * 種類（type）で出力する内容を切り替える。検索条件は一覧と同じで、ページ番号は無視して条件に合う全件を出力する。
 */
class AdminExportController extends Controller
{
    /**
     * 検索条件に合う全件の .xlsx をダウンロードさせる
     */
    public function export(ExportRequest $request, AdminPurchaseService $service): BinaryFileResponse
    {
        Log::debug('AdminExportController start');

        $response = match ($request->type()) {
            // ファイル名は日本語（filename*）で、ASCII 側（filename）は有効な英数字の名前にする
            ExportType::Purchase => Excel::download($service->purchaseExport($request->conditions()), PurchaseExport::fileName())
                ->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, PurchaseExport::fileName(), PurchaseExport::asciiFileName()),
        };

        Log::debug('AdminExportController end');

        return $response;
    }
}
