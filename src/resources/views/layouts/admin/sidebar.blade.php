{{--
    管理画面のサイドメニュー（layouts/admin/app の中。状態 open・spOpen は body の x-data）
    - PC（lg以上）：幅200pxで開く。「メニューを閉じる」で幅56pxの帯に畳み、帯のボタン（縦書きの「メニュー」）で開く。地 admin-bar・右に1pxの線
    - SP（lg未満）：ヘッダーの「メニュー」ボタン（spOpen）で本文の上に開閉する。初期状態は閉じている。PCの状態（open）は使わない
    リンクは App\Support\AdminNav。表示中のページは太字と灰色の地。ホバー・フォーカスの下線は管理画面トップのカードのリンクと同じ太さ・距離（underline-thick）で、色は AdminNav の linkClass。
    開閉のクラスは Alpine の :class で切り替える。サーバーが出す class は初期状態（PCは開、SPは閉）と同じにしてあり、Alpine が起動するまで aside ごと隠す（x-cloak。保存された状態と違う見た目が一瞬出ないようにする）。
    開閉のアニメーション（約200ms。prefers-reduced-motion のときは動かさない＝motion-safe:）：
    - transition のクラスは、Alpine の起動が終わってから（body の ready が true になってから）付ける。再読み込み・ページ移動では動かさない
    - PC：aside の幅を 200px ⇔ 56px に変える。中身は幅200px固定のまま aside の overflow-hidden で隠し、中身と帯は opacity で入れ替える（折り返し・潰れを出さない）
    - SP：メニューの高さを grid-template-rows の 0fr ⇔ 1fr で変える（x-collapse は PC で高さ0になる恐れがあるため使わない）。余白は内側の要素に付ける（外側に付けると0frでも高さが残る）
    - 閉じている側は visibility: hidden にして Tab でフォーカスが入らないようにする。visibility は transition の終わりに切り替わるので、閉じる動きの間は見える
--}}
@php
    $home = \App\Support\AdminNav::home();
    $groups = \App\Support\AdminNav::groups();
    $linkClass = 'flex min-h-12 items-center rounded-[5px] pr-2 text-lg text-ink underline-thick hover:underline focus-visible:underline lg:min-h-11 lg:text-base';
    $arrow = 'size-5 shrink-0';
@endphp
<aside class="lg:w-50 lg:shrink-0 lg:border-r lg:border-line lg:bg-admin-bar lg:relative lg:overflow-hidden" x-cloak x-bind:class="{ 'lg:w-50': open, 'lg:w-14': ! open, 'motion-safe:lg:transition-[width] motion-safe:lg:duration-200 motion-safe:lg:ease-out': ready }" aria-label="管理画面のメニュー">
    {{-- 開いているときのメニュー（SPはspOpenで、PCはopenで表示する）。外側が開閉の枠（SPは高さ、PCは透明度）、内側の2段目で余白と地を付ける --}}
    <div id="admin-menu" class="invisible grid grid-rows-[0fr] lg:visible lg:block lg:w-50" x-bind:class="{ 'visible grid-rows-[1fr]': spOpen, 'invisible grid-rows-[0fr]': ! spOpen, 'lg:visible lg:opacity-100': open, 'lg:invisible lg:opacity-0': ! open, 'motion-safe:transition-[grid-template-rows,opacity,visibility] motion-safe:duration-200 motion-safe:ease-out': ready }">
        <div class="min-h-0 overflow-hidden">
            <div class="border-b border-line bg-admin-bar p-4 lg:border-b-0 lg:bg-transparent">
                <button type="button" class="hidden min-h-11 w-full cursor-pointer items-center gap-2 rounded-[5px] pr-2 text-base hover:underline focus-visible:underline lg:flex" aria-expanded="true" x-ref="sidebarClose" x-on:click="open = false; $nextTick(() => requestAnimationFrame(() => $refs.sidebarOpen.focus()))">
                    <svg class="{{ $arrow }}" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4 6 10l6 6" /></svg>
                    メニューを閉じる
                </button>
                <hr class="my-2 hidden border-line lg:block">

                <nav aria-label="管理画面のページ">
                    <ul>
                        <li>
                            @php($current = \App\Support\AdminNav::isCurrent($home))
                            <a @class([$linkClass, $home['linkClass'], 'lg:pl-2', 'bg-admin-bar-end font-bold' => $current]) href="{{ $home['url'] }}"{!! $current ? ' aria-current="page"' : '' !!}>{{ $home['label'] }}</a>
                        </li>
                    </ul>
                    @foreach ($groups as $group)
                        <p class="mt-4 text-base lg:text-sm {{ $group['titleClass'] }}">{{ $group['title'] }}</p>
                        <ul>
                            @foreach ($group['links'] as $link)
                                @php($current = \App\Support\AdminNav::isCurrent($link))
                                <li>
                                    <a @class([$linkClass, $group['linkClass'], 'pl-5', 'bg-admin-bar-end font-bold' => $current]) href="{{ $link['url'] }}"{!! $current ? ' aria-current="page"' : '' !!}>{{ $link['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </nav>
            </div>
        </div>
    </div>

    {{-- 畳んだときの帯（PCだけ）。幅44pxのボタンで開く。メニューの上に重ねて置き、透明度で入れ替える --}}
    <div class="hidden p-1.5 lg:absolute lg:inset-y-0 lg:left-0 lg:block lg:w-14 lg:invisible lg:opacity-0" x-bind:class="{ 'lg:visible lg:opacity-100': ! open, 'lg:invisible lg:opacity-0': open, 'motion-safe:lg:transition-[opacity,visibility] motion-safe:lg:duration-200 motion-safe:lg:ease-out': ready }">
        <button type="button" class="flex w-11 cursor-pointer flex-col items-center gap-2 rounded-[5px] py-3 text-base hover:bg-admin-bar-end focus-visible:bg-admin-bar-end" aria-label="メニューを開く" aria-expanded="false" aria-controls="admin-menu" x-bind:aria-expanded="open" x-ref="sidebarOpen" x-on:click="open = true; $nextTick(() => requestAnimationFrame(() => $refs.sidebarClose.focus()))">
            <svg class="{{ $arrow }}" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m8 4 6 6-6 6" /></svg>
            <span class="[writing-mode:vertical-rl]">メニュー</span>
        </button>
    </div>
</aside>
