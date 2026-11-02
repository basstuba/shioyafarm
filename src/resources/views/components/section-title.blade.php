{{-- マイページのセクション見出し（h2。緑・Kiwi Maru 24px・中央）。余白などは呼び出し側から class で追加できる --}}
<h2 {{ $attributes->merge(['class' => 'text-center font-maru text-2xl text-accent']) }}>{{ $slot }}</h2>
