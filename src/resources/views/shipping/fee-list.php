<div>
    <table class="fee-list">
        <thead>
            <tr>
                <th scope="col" class="area">お届け先</th>

                @foreach(config('shipping.areas') as $area => $prefectures)
                    <th scope="col" class="area__name">{{ $area }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td scope="row" class="space">&nbsp;</td>

                @foreach(config('shipping.areas') as $area => $prefectures)
                    <td class="prefecture__name">
                        <ul>
                            @foreach($prefectures as $prefecture)
                                <li>{{ $prefecture }}</li>
                            @endforeach
                        </ul>
                    </td>
                @endforeach
            </tr>
            @foreach(config('shipping.fees') as $size => $fees)
                <tr>
                    <th scope="row" class="division-type">{{ $size }}サイズ</th>

                    @foreach($fees as $fee)
                        <td class="division__fee">{{ number_format($fee) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
