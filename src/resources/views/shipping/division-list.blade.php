<div>
    <table class="division-list">
        <thead>
            <tr>
                <th scope="col" class="division-list__title">サイズ</th>
                <th scope="col" class="division-list__title">重量</th>
            </tr>
        </thead>
        <tbody>
            @foreach(config('shipping.sizes') as $size => $data)
                <tr>
                    <th scope="row" class="division__type">{{ $size }}サイズ</th>
                    <td class="division__weight">{{ $data['label'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
