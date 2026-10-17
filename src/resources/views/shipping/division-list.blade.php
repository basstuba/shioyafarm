<table class="mx-auto w-full max-w-[352px] border-collapse text-center [&_td]:border [&_th]:border">
    <thead>
        <tr>
            <th scope="col" class="bg-line px-2.5 py-1.5 text-xl font-extrabold">サイズ</th>
            <th scope="col" class="bg-line px-2.5 py-1.5 text-xl font-extrabold">重量</th>
        </tr>
    </thead>
    <tbody>
        @foreach(config('shipping.sizes') as $size => $data)
            <tr>
                <th scope="row" class="bg-cream-200 px-2 py-1 text-base font-extrabold">{{ $size }}サイズ</th>
                <td class="bg-white px-2 py-1 text-base">{{ $data['label'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
