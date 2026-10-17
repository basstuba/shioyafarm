@extends('layouts.app')

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
                <div class="mb-15 overflow-x-auto">
                    @include('shipping.fee-list')
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
