{{--
    公開デモの案内の帯（DEMO_NOTICE がオンのときだけ出る。出し分けは App\View\Components\DemoNotice）。
    variant：public（桃色の地・黒の文字）／admin（濃い灰色の地・白の文字）。位置は呼び出し側の class で決める（relative・mt-header など）。
    SPでは折り返して全文を出す（はみ出さない）。
--}}
<div {{ $attributes->class([$classes()]) }} role="note" aria-label="デモサイトのご案内">
    <p class="mx-auto max-w-[1520px] px-4 py-2 text-center text-sm leading-relaxed wrap-break-word md:text-base">{{ $message() }}</p>
</div>
