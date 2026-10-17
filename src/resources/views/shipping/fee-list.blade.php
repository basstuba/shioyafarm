<table class="w-full border-collapse text-center [&_td]:border [&_th]:border">
    <thead>
        <tr>
            <th scope="col" class="bg-line px-2.5 py-1.5 text-xl font-extrabold">お届け先</th>

            @foreach(config('shipping.areas') as $area => $prefectures)
                <th scope="col" class="bg-line px-2.5 py-1.5 text-xl font-extrabold whitespace-nowrap">{{ $area }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="bg-surface px-2 py-1">&nbsp;</td>

            @foreach(config('shipping.areas') as $area => $prefectures)
                <td class="bg-surface px-2 py-1 text-base">
                    <ul class="my-4">
                        @foreach($prefectures as $prefecture)
                            <li class="whitespace-nowrap">{{ $prefecture }}</li>
                        @endforeach
                    </ul>
                </td>
            @endforeach
        </tr>
        @foreach(config('shipping.fees') as $size => $fees)
            <tr>
                <th scope="row" class="bg-cream-200 px-2 py-1 text-base font-extrabold whitespace-nowrap">{{ $size }}サイズ</th>

                @foreach($fees as $fee)
                    <td class="bg-white px-2 py-1 text-base">{{ number_format($fee) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
