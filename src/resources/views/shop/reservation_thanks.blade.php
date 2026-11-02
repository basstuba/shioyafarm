@extends('layouts.thanks')

@section('page-title', 'ご予約完了 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 見出しは1行だと幅840pxあるため、1行にするのは lg 以上だけ。md〜lg は2行、SPは3行に改行する --}}
    <x-thanks-page color-class="text-reserve-text" nowrap="lg">
        <x-slot:heading>ご予約ありがとうございます。<br class="lg:hidden">お届けまで、<br class="md:hidden">どうぞ楽しみにお待ちください。</x-slot:heading>
        商品は9月より、準備が整い次第順次発送いたします。
    </x-thanks-page>
@endsection
