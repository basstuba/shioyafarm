<header class="fixed top-0 left-0 z-10 h-header w-full backdrop-blur-[10px]">
    <div class="flex items-center justify-between gap-4 bg-white/80 px-1 py-px font-maru">
        <div class="w-24 shrink-0 text-center">
            <a class="no-underline" {{--href="{{ route('top.index') }}"--}}>
                <img class="mx-auto h-auto w-[62px] cursor-pointer" src="{{ asset('storage/image/tool/shioya-farm-logo-small.webp') }}" alt="" />
                <span class="block cursor-pointer text-center text-xs font-medium text-primary">しおや農園</span>
            </a>
        </div>
        <nav class="flex flex-wrap items-center justify-end gap-x-4 gap-y-1">
            @foreach ($navItems as $item)
                @if (isset($item['method']) && $item['method'] === 'post')
                    <form action="{{ $item['url'] }}" method="POST">
                        @csrf
                        <button class="group flex cursor-pointer flex-col items-center gap-0.5 md:flex-row" type="submit">
                            <img class="w-6 md:w-5" src="{{ asset('storage/' . $item['icon']) }}" alt="" />
                            <span class="text-[10px] text-ink underline-accent group-hover:underline md:text-sm">{{ $item['label'] }}</span>
                        </button>
                    </form>
                @else
                    <a class="group flex flex-col items-center gap-0.5 md:flex-row" href="{{ $item['url'] }}">
                        <img class="w-6 md:w-5" src="{{ asset('storage/' . $item['icon']) }}" alt="" />
                        <span class="text-[10px] text-ink underline-accent group-hover:underline md:text-sm">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
    </div>
</header>
