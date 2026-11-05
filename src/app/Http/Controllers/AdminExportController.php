<?php

namespace App\Http\Controllers;

use App\Enums\AdminListType;
use App\Exports\AdminOrderExport;
use App\Http\Requests\ExportRequest;
use App\Services\AdminPurchaseService;
use App\Services\AdminReservationService;
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
    public function export(ExportRequest $request, AdminPurchaseService $purchaseService, AdminReservationService $reservationService): BinaryFileResponse
    {
        Log::debug('AdminExportController start');

        $export = match ($request->type()) {
            AdminListType::Purchase => $purchaseService->purchaseExport($request->conditions()),
            AdminListType::Reservation => $reservationService->reservationExport($request->conditions()),
        };
        $response = $this->download($export);

        Log::debug('AdminExportController end');

        return $response;
    }

    /**
     * エクスポートをダウンロードの応答にする
     *
     * ファイル名は日本語（filename*）で、ASCII 側（filename）は有効な英数字の名前にする。
     */
    private function download(AdminOrderExport $export): BinaryFileResponse
    {
        return Excel::download($export, $export::fileName())
            ->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $export::fileName(), $export::asciiFileName());
    }
}
