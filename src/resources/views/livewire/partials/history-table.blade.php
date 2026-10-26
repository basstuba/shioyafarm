{{--
    購入履歴・予約履歴の表（マイページ用。見た目は仮置き）
    - $rows：['id', 'date', 'names'（商品名の配列）, 'total'] の配列のコレクション
    - $dateLabel：日付の列の見出し（注文日／予約日）
    - $emptyMessage：0件のときの文言
    横に広がる表は、ページ全体が横スクロールしないよう overflow-x-auto で囲む。
--}}
@if ($rows->isEmpty())
    <p class="py-4 text-center text-base md:text-lg">{{ $emptyMessage }}</p>
@else
    <div class="overflow-x-auto rounded-[10px] bg-white shadow-panel">
        <table class="w-full text-left text-sm md:text-base">
            <thead class="border-b border-line bg-cream-200">
                <tr>
                    <th scope="col" class="px-2 py-3 font-medium whitespace-nowrap md:px-4">{{ $dateLabel }}</th>
                    <th scope="col" class="px-2 py-3 font-medium md:px-4">商品名</th>
                    <th scope="col" class="px-2 py-3 text-right font-medium whitespace-nowrap md:px-4">合計金額</th>
                    <th scope="col" class="px-2 py-3 text-center font-medium whitespace-nowrap md:px-4"><span class="sr-only">詳細</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($rows as $row)
                    <tr wire:key="{{ $keyPrefix }}-{{ $row['id'] }}">
                        <td class="px-2 py-3 align-top whitespace-nowrap md:px-4">{{ $row['date'] }}</td>
                        <td class="px-2 py-3 align-top break-words md:px-4">
                            @foreach ($row['names'] as $name)
                                <p>{{ $name }}</p>
                            @endforeach
                        </td>
                        <td class="px-2 py-3 text-right align-top whitespace-nowrap md:px-4">{{ number_format($row['total']) }}円</td>
                        <td class="px-2 py-3 text-center align-top md:px-4">
                            <button type="button" wire:click="openDetail({{ $row['id'] }})" class="inline-flex h-11 min-w-14 cursor-pointer items-center justify-center rounded-[30px] border-2 border-action bg-white px-2 font-maru text-sm text-action transition-colors hover:bg-action-hover hover:text-action-hover-text focus-visible:bg-action-hover focus-visible:outline-none md:px-4 md:text-base" aria-label="{{ $dateLabel }}{{ $row['date'] }}の詳細を見る">詳細</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
