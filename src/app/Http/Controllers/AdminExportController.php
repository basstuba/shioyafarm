<?php

namespace App\Http\Controllers;

use App\Enums\AdminListStatus;
use App\Enums\AdminListType;
use App\Exports\AdminOrderExport;
use App\Http\Requests\ExportRequest;
use App\Services\AdminListCriteria;
use App\Services\AdminPurchaseService;
use App\Services\AdminReservationService;
use Illuminate\Support\Facades\Date;
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
        // 発送状況が「取消済」のときは、ファイル名で取消済みの一覧だとわかるようにする
        $response = $this->download($export, AdminListCriteria::fromConditions($request->conditions())->status === AdminListStatus::Canceled);

        Log::debug('AdminExportController end');

        return $response;
    }

    /**
     * エクスポートをダウンロードの応答にする
     *
     * ファイル名は日本語（filename*）で、ASCII 側（filename）は有効な英数字の名前にする。
     */
    private function download(AdminOrderExport $export, bool $canceled): BinaryFileResponse
    {
        // 日時は1回だけ取り、日本語と ASCII のファイル名の日付を揃える（日付の変わり目をまたいでもずれない）
        $now = Date::now();

        return Excel::download($export, $export::fileName($canceled, $now))
            ->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $export::fileName($canceled, $now), $export::asciiFileName($canceled, $now));
    }
}
