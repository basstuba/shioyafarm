<header class="header">
    <div class="header-inner">
        <div class="header-logo">
            <a class="header-logo__link" {{--href="{{ route('top') }}"--}}> <!-- ルートを設定するまでの仮実装 -->
                <img class="header-logo__img" src="{{ asset('storage/image/tool/shioya-farm-logo-small.webp') }}" alt="" />
                <span class="header-logo__text">しおや農園</span>
            </a>
        </div>
        <nav class="header-nav">
            @foreach ($navItems as $item)
                @if (isset($item['method']) && $item['method'] === 'post')
                    <form class="nav-item" action="{{ $item['url'] }}" method="POST">
                        @csrf
                        <button class="nav-item__logout" type="submit">
                            <img class="nav-item__icon"  src="{{ asset('storage/' . $item['icon']) }}" alt="" />
                            <span class="nav-item__label">{{ $item['label'] }}</span>
                        </button>
                    </form>
                @else
                    <a class="nav-item" href="{{ $item['url'] }}">
                        <img class="nav-item__icon" src="{{ asset('storage/' . $item['icon']) }}" alt="" />
                        <span class="nav-item__label">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
    </div>
</header>