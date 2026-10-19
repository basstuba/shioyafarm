@php
    // SPでもヘッダーに出す項目と、「メニュー」ボタンで開くパネルに入れる項目に分ける（PCはすべてヘッダーに並べる）
    [, $menuItems] = \App\Support\Nav::split($navItems);
@endphp
{{--
    開閉は Livewire 同梱の Alpine.js で行う。ラッパーはレイアウトに影響しないよう contents にする。
    - 開いている間は body のスクロールを止める
    - 開いたらパネルの最初の項目にフォーカスを移し、閉じたら「メニュー」ボタンに戻す（リンクを押して移動する場合は戻さない）
    - 開いている間、Tabは「メニュー」ボタンとパネルの項目の中で循環させる
--}}
<div
    class="contents"
    x-data="{
        open: false,
        focusables() {
            return [this.$refs.menuButton, ...this.$refs.menuPanel.querySelectorAll('a[href], button:not([disabled])')]
        },
        toggle() {
            this.open = !this.open
            if (this.open) {
                // x-show は表示を setTimeout で行うため、その後に実行されるよう setTimeout を挟む
                this.$nextTick(() => setTimeout(() => this.$refs.menuPanel.querySelector('a[href], button:not([disabled])')?.focus()))
            } else {
                this.$refs.menuButton.focus()
            }
        },
        close(refocus = true) {
            this.open = false
            if (refocus) this.$refs.menuButton.focus()
        },
        trap(event) {
            if (!this.open) return
            const items = this.focusables()
            const index = items.indexOf(document.activeElement)
            const last = items.length - 1
            if (event.shiftKey && index <= 0) {
                event.preventDefault()
                items[last].focus()
            } else if (!event.shiftKey && (index === -1 || index === last)) {
                event.preventDefault()
                items[0].focus()
            }
        },
    }"
    x-effect="document.body.classList.toggle('overflow-hidden', open)"
    x-on:keydown.escape.window="open && close()"
    x-on:keydown.tab.window="trap($event)"
    x-on:resize.window="if (window.innerWidth >= 768) open = false"
>
    <header class="fixed top-0 left-0 z-10 h-header w-full backdrop-blur-[10px]">
        <div class="flex items-center justify-between gap-2 bg-white/80 px-1 py-px font-maru md:gap-4">
            <div class="w-18 shrink-0 text-center md:w-24">
                <a class="no-underline" href="{{ route('top.index') }}">
                    <img class="mx-auto h-auto w-[62px] cursor-pointer" src="{{ asset('storage/image/tool/shioya-farm-logo-small.webp') }}" alt="" />
                    <span class="block cursor-pointer text-center text-xs font-medium text-primary">しおや農園</span>
                </a>
            </div>
            <nav class="flex flex-nowrap items-center justify-end gap-x-3 md:flex-wrap md:gap-x-4 md:gap-y-1" aria-label="メインナビゲーション">
                @foreach ($navItems as $item)
                    <x-nav-item :item="$item" />
                @endforeach

                {{-- メニューボタン（SPのみ） --}}
                @if (count($menuItems) > 0)
                    <button class="group flex cursor-pointer flex-col items-center gap-0.5 md:hidden" type="button" x-ref="menuButton" aria-controls="sp-menu" aria-expanded="false" x-bind:aria-expanded="open ? 'true' : 'false'" x-on:click="toggle()">
                        <svg class="size-6 text-ink" x-show="!open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                            <path d="M3.5 6h17M3.5 12h17M3.5 18h17" />
                        </svg>
                        <svg class="size-6 text-ink" x-show="open" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                            <path d="M4.5 4.5l15 15M19.5 4.5l-15 15" />
                        </svg>
                        <span class="text-[10px] whitespace-nowrap text-ink" x-show="!open">メニュー</span>
                        <span class="text-[10px] whitespace-nowrap text-ink" x-show="open" x-cloak>閉じる</span>
                    </button>
                @endif
            </nav>
        </div>
    </header>

    {{-- SPのメニュー（パネルとオーバーレイ）。header は backdrop-blur で fixed の基準になるため、外に置く --}}
    @if (count($menuItems) > 0)
        <div class="fixed inset-x-0 top-header bottom-0 z-10 bg-black/35 md:hidden" x-show="open" x-cloak x-on:click="close()" aria-hidden="true"></div>
        <nav class="fixed inset-x-0 top-header z-10 max-h-[calc(100dvh-var(--spacing-header))] overflow-y-auto overscroll-contain bg-white pt-2 pb-4 font-maru shadow-menu md:hidden" id="sp-menu" aria-label="メニュー" x-ref="menuPanel" x-show="open" x-cloak>
            <ul>
                @foreach ($menuItems as $item)
                    <x-nav-item :item="$item" variant="menu" />
                @endforeach
            </ul>
        </nav>
    @endif
</div>
