{{-- ページの見出し（h2）。余白などは呼び出し側から class で追加できる --}}
<h2 {{ $attributes->merge(['class' => 'my-5 text-center text-2xl font-semibold text-ink']) }}>{{ $slot }}</h2>
