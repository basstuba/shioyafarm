@props(['name', 'value', 'checked' => false])

{{--
    管理画面のラジオボタン（掲載商品の登録・修正で使う。Figma 174:56）。ネイティブの radio を見た目だけ整えたもの
    - name・value：input の属性。checked：最初から選ばれているか（Alpine の x-model などは $attributes で渡す）
    - 丸は24px・枠4px。未選択は枠 line（#dddddd）・文字 muted（#6b6b6b。Figmaの #acacac は読みにくいため濃くした）、選択は枠 ink・中に10pxの黒丸・文字 ink
    - ラベルを含めて押せる（SPは高さ44px以上、lg以上は32px）。無効（disabled）のときは押せない見た目にする
    - 文字は既定のスロット
--}}
<label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-base text-muted has-[:checked]:text-ink has-[:disabled]:cursor-not-allowed lg:min-h-8">
    <input
        type="radio"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked($checked)
        {{ $attributes->class('size-6 shrink-0 cursor-pointer appearance-none rounded-full border-4 border-line bg-white checked:border-ink checked:bg-[radial-gradient(circle,var(--color-ink)_5px,transparent_5.5px)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink disabled:cursor-not-allowed') }}
    >
    <span>{{ $slot }}</span>
</label>
