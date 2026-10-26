{{-- マイページのお届け先住所一覧と住所追加モーダル。見た目は仮置き（Figmaの上限リセット後に仕上げる） --}}
<div>
    <div class="mb-4 flex justify-end">
        <x-outline-button type="button" wire:click="openAddressModal">＋新しい住所を追加</x-outline-button>
    </div>

    @if ($addresses->isEmpty())
        <p class="py-4 text-center text-base md:text-lg">登録されているお届け先住所はありません</p>
    @else
        <ul class="divide-y divide-line rounded-[10px] bg-white shadow-panel">
            @foreach ($addresses as $address)
                <li wire:key="address-{{ $address->id }}" class="px-4 py-3 text-base break-words md:px-6 md:text-lg">{{ $address->delivery_prefecture }}{{ $address->delivery_city }}</li>
            @endforeach
        </ul>
    @endif

    @include('livewire.partials.pager', ['paginator' => $addresses, 'pageName' => \App\Livewire\MyPageAddressList::PAGE_NAME])

    {{-- 購入ページと同じ住所追加モーダル --}}
    @include('livewire.partials.address-modal')
</div>
