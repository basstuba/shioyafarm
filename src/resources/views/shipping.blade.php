@extends('layouts.app')

@section('body-class', 'shipping-page')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/shipping.css') }}">
@endsection

@section('content')
    <div class="shipping">
        <div class="container">
            <h2 class="title">配送料金一覧表</h2>
            <section class="section">
                <div class="unit">
                    <dt class="unit__title">単位：</dt>
                    <dd class="unit__name">円(税込)</dd>
                </div>
                <div class="fee-list">
                    @include('shipping.fee-list')
                </div>
            </section>
            <h2 class="title">サイズ区分一覧表</h2>
            <section class="section">
                <div class="division-list">
                    @include('shipping.division-list')
                </div>
            </section>
        </div>
    </div>
@endsection
