{{--
    配送料金一覧表。
    - $areas：表に出す地域（地域名 => 県名の配列）。省略すると config('shipping.areas') の全地域（PC用の1つの表）
    - $compact：true のとき、SP用に余白・文字を小さくして固定幅（table-fixed）にする。3地域ずつの小さな表を縦に並べるために使う
    料金は config('shipping.fees') の「サイズ => [地域名 => 金額]」から、表に出す地域の列だけを取る
--}}
@php
    $areas ??= config('shipping.areas');
    $compact ??= false;
@endphp
<table @class(['w-full border-collapse text-center [&_td]:border [&_th]:border', 'table-fixed' => $compact])>
    @if ($compact)
        <colgroup>
            <col class="w-20">
            @foreach($areas as $area => $prefectures)
                <col>
            @endforeach
        </colgroup>
    @endif
    <thead>
        <tr>
            <th scope="col" @class(['bg-line font-extrabold', 'px-1 py-1.5 text-sm' => $compact, 'px-2.5 py-1.5 text-xl' => ! $compact])>お届け先</th>

            @foreach($areas as $area => $prefectures)
                <th scope="col" @class(['bg-line font-extrabold', 'px-1 py-1.5 text-base' => $compact, 'px-2.5 py-1.5 text-xl whitespace-nowrap' => ! $compact])>{{ $area }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="bg-surface px-2 py-1">&nbsp;</td>

            @foreach($areas as $area => $prefectures)
                <td @class(['bg-surface text-base', 'px-1 py-1' => $compact, 'px-2 py-1' => ! $compact])>
                    <ul @class(['my-4' => ! $compact, 'my-2 text-sm' => $compact])>
                        @foreach($prefectures as $prefecture)
                            <li class="whitespace-nowrap">{{ $prefecture }}</li>
                        @endforeach
                    </ul>
                </td>
            @endforeach
        </tr>
        @foreach(config('shipping.fees') as $size => $fees)
            <tr>
                <th scope="row" @class(['bg-cream-200 font-extrabold whitespace-nowrap', 'px-1 py-1 text-sm' => $compact, 'px-2 py-1 text-base' => ! $compact])>{{ $size }}サイズ</th>

                @foreach($areas as $area => $prefectures)
                    <td @class(['bg-white py-1 text-base', 'px-1' => $compact, 'px-2' => ! $compact])>{{ isset($fees[$area]) ? number_format($fees[$area]) : '―' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
