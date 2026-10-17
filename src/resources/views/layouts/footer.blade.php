<footer class="self-end">
    <div class="flex flex-col gap-4 bg-cream-200 px-4 py-3 font-maru text-ink in-[.legal-page]:bg-cream-50 md:flex-row md:items-center md:justify-between">
        <div class="md:w-1/2">
            <div>
                <a class="flex items-center no-underline" {{--href="{{ route('top.index') }}"--}}>
                    <img class="h-auto w-[61px] cursor-pointer" src="{{ asset('storage/image/tool/shioya-farm-logo-small.webp') }}" alt="" />
                    <span class="text-[28px] font-medium text-primary">しおや農園</span>
                </a>
            </div>
            <dl class="my-2.5 flex flex-wrap items-center gap-x-15 gap-y-2 text-xs">
                <div>
                    <dt class="mb-1.5">所在地</dt>
                    <dd>〒091-2345 北海道架空郡架空町567-34</dd>
                </div>
                <div>
                    <dt class="mb-1.5">電話番号</dt>
                    <dd>01234-5-6789</dd>
                </div>
            </dl>
            <div>
                <span class="text-xs">&copy; Shioya Farm</span>
            </div>
        </div>
        <nav>
            <ul class="flex flex-wrap items-center gap-x-10 gap-y-2 md:justify-end">
                <li>
                    <a class="text-sm underline-accent hover:underline" href="{{ route('info.shipping-fee') }}">送料案内</a>
                </li>
                <li>
                    <a class="text-sm underline-accent hover:underline" href="{{ route('info.terms-of-service') }}">利用規約</a>
                </li>
                <li>
                    <a class="text-sm underline-accent hover:underline" href="{{ route('info.privacy-policy') }}">プライバシーポリシー</a>
                </li>
                <li>
                    <a class="text-sm underline-accent hover:underline" href="{{ route('info.commercial-transaction')}}">特定商取引法に基づく表記</a>
                </li>
            </ul>
        </nav>
    </div>
</footer>
