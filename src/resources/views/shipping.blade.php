@extends('layouts.app')

@section('page-title', '送料案内 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    <div class="pt-header">
        <div class="mx-auto max-w-[1760px] px-4 pt-10 pb-15 text-ink">
            <x-page-title>配送料金一覧表</x-page-title>
            <section>
                <dl class="mb-3.5 ml-auto grid w-fit grid-cols-[max-content_1fr] gap-y-3.5 text-sm [&_dt]:after:content-['：']">
                    <dt>単位</dt>
                    <dd>円(税込)</dd>
                </dl>
                {{-- PC（md以上）は12地域を1つの表に並べ、SPは3地域ずつの表4つを縦に並べる（同じ fee-list を地域を絞って使う） --}}
                <div class="mb-15 hidden overflow-x-auto md:block">
                    @include('shipping.fee-list', ['areas' => config('shipping.areas'), 'compact' => false])
                </div>
                <div class="mb-15 flex flex-col gap-8 md:hidden">
                    @foreach (array_chunk(config('shipping.areas'), 3, true) as $areaGroup)
                        @include('shipping.fee-list', ['areas' => $areaGroup, 'compact' => true])
                    @endforeach
                </div>
            </section>
            <x-page-title>サイズ区分一覧表</x-page-title>
            <section>
                <div class="pt-5">
                    @include('shipping.division-list')
                </div>
            </section>
        </div>
    </div>
@endsection
