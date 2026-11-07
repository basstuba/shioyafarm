{{--
    購入履歴・予約履歴の表（マイページ用。幅600px。親の列の幅に合わせる）
    - $rows：['id', 'date', 'names'（商品名の配列）, 'total', 'canceled'（取消済みか）] の配列のコレクション
    - $dateLabel：日付の列の見出し（注文日／予約日）
    - $emptyMessage：0件のときの文言
    列の幅は PC では 日付134・商品名192・合計金額157・詳細（残り117）px、SP では 22%・30%・26%・残り。SPでも4列のまま、セルの左右の余白を詰めて収める。
    取消済みの行は、日付の下に「取消済」と出す（履歴には残す）。
    表が親より広くなる場合に備え、ページ全体が横スクロールしないよう overflow-x-auto で囲む。
--}}
@if ($rows->isEmpty())
    <p class="mt-[45px] text-center text-base">{{ $emptyMessage }}</p>
@else
    <div class="mt-[45px] overflow-x-auto">
        <table class="w-full table-fixed text-left text-xs">
            <colgroup>
                <col class="w-[22%] md:w-[134px]">
                <col class="w-[30%] md:w-[192px]">
                <col class="w-[26%] md:w-[157px]">
                <col>
            </colgroup>
            <thead>
                <tr class="h-[35px] bg-action-hover">
                    <th scope="col" class="border-b-2 border-primary-dark px-1 font-maru text-base font-medium whitespace-nowrap md:pr-0 md:pl-5">{{ $dateLabel }}</th>
                    <th scope="col" class="border-b-2 border-primary-dark px-1 font-maru text-base font-medium md:pr-0 md:pl-5">商品名</th>
                    <th scope="col" class="border-b-2 border-primary-dark px-1 font-maru text-base font-medium whitespace-nowrap md:pr-0 md:pl-5">合計金額</th>
                    <th scope="col" class="border-b-2 border-primary-dark px-0.5 md:pr-0 md:pl-5"><span class="sr-only">詳細</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr wire:key="{{ $keyPrefix }}-{{ $row['id'] }}" class="even:bg-cream-100">
                        <td class="border-b border-primary-dark px-1 py-3.5 align-middle whitespace-nowrap md:pr-0 md:pl-5">
                            {{ $row['date'] }}
                            @if ($row['canceled'])
                                <span class="block font-bold text-muted">取消済</span>
                            @endif
                        </td>
                        <td class="border-b border-primary-dark px-1 py-3.5 align-middle break-words md:pr-0 md:pl-5">
                            @foreach ($row['names'] as $name)
                                <p>{{ $name }}</p>
                            @endforeach
                        </td>
                        <td class="border-b border-primary-dark px-1 py-3.5 align-middle whitespace-nowrap md:pr-0 md:pl-5">{{ number_format($row['total']) }}円</td>
                        <td class="border-b border-primary-dark px-0.5 py-1 align-middle md:py-[7px] md:pr-0 md:pl-5">
                            <button type="button" wire:click="openDetail({{ $row['id'] }})" class="inline-flex h-11 w-[65px] cursor-pointer items-center justify-center rounded-[10px] bg-action-hover font-maru text-base text-primary-text transition-[background-color,box-shadow] duration-200 hover:bg-detail-hover hover:shadow-button focus-visible:bg-detail-hover focus-visible:shadow-button focus-visible:outline-none md:h-[30px]" aria-label="{{ $dateLabel }}{{ $row['date'] }}の詳細を見る">詳細</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
