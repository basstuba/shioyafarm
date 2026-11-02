@extends('layouts.thanks')

@section('page-title', 'ご注文完了 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 見出しは約696pxで1行に収まるため、md から1行にする。SPは2行に改行する --}}
    <x-thanks-page color-class="text-leaf" nowrap="md">
        <x-slot:heading>ご注文ありがとうございました。<br class="md:hidden">心を込めてお届けいたします。</x-slot:heading>
        商品はお支払い確定後3~5営業日以内、<br class="hidden md:inline">代金引換の場合はご注文確定後3~5営業日以内に発送いたします。
    </x-thanks-page>
@endsection
