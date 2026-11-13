{{-- マイページのお届け先住所一覧と住所追加モーダル（幅600pxの列。SPは左右16pxの余白で横いっぱい） --}}
<div class="mx-auto max-w-[632px] px-4 pb-[33px]">
    <div class="mt-[37px] flex justify-end">
        <x-outline-button size="address" type="button" livewire wire:click="openAddressModal">＋新しい住所を追加</x-outline-button>
    </div>

    @if ($addresses->isEmpty())
        <p class="mt-[30px] text-center text-base">登録されているお届け先住所はありません</p>
    @else
        <ul class="mt-[30px]">
            @foreach ($addresses as $address)
                <li wire:key="address-{{ $address->id }}" class="border-b border-primary-dark py-[11px] text-base break-words">{{ $address->delivery_prefecture }}{{ $address->delivery_city }}</li>
            @endforeach
        </ul>
    @endif

    @include('livewire.partials.pager', ['paginator' => $addresses, 'pageName' => \App\Livewire\MyPageAddressList::PAGE_NAME, 'topClass' => 'mt-[47px]'])

    {{-- 購入ページと同じ住所追加モーダル --}}
    @include('livewire.partials.address-modal')
</div>
