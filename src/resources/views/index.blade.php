@extends('layouts.app')

@section('body-class', 'fv-page')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}">
@endsection

@section('content')
    <div class="index">
        <div class="container">
            <section class="first-view">
                <div class="slide">
                    <img src="{{ asset('image/tool/fv-maine-photo.webp') }}" alt="誰が作ったかまで、おいしい理由になる">
                    <p>“誰が作ったか”まで、おいしい理由になる。</p>
                    <p>ー野菜のことを誰より知る人から、まっすぐあなたへ。</p>
                </div>
                <div class="slide">
                    <img src="{{ asset('image/tool/fv-onion-photo.webp') }}" alt="誰が作ったかまで、おいしい理由になる">
                    <p>“誰が作ったか”まで、おいしい理由になる。</p>
                    <p>ー野菜のことを誰より知る人から、まっすぐあなたへ。</p>
                </div>
                <div class="slide">
                    <img src="{{ asset('image/tool/fv-agriculture-photo.webp') }}" alt="誰が作ったかまで、おいしい理由になる">
                    <p>“誰が作ったか”まで、おいしい理由になる。</p>
                    <p>ー野菜のことを誰より知る人から、まっすぐあなたへ。</p>
                </div>
                <div class="slide">
                    <img src="{{ asset('image/tool/fv-potatoes-photo.webp') }}" alt="誰が作ったかまで、おいしい理由になる">
                    <p>“誰が作ったか”まで、おいしい理由になる。</p>
                    <p>ー野菜のことを誰より知る人から、まっすぐあなたへ。</p>
                </div>
            </section>
            <section class="information">
                <h2 class="info-title">お知らせ</h2>
                <div class="info-container">
                    @foreach( $notices as $notice )
                        <div class="notice-wrapper">
                            <div class="notice-title">{{ $notice->title }}</div>
                            <div class="notice-date">{{ $notice->date }}</div>
                        </div>
                        <p class="notice-text">{{ $notice->text }}</p>

                        <hr class="divider">
                    @endforeach
                </div>
            </section>
            <section class="introduction">
                <div class="introduction-wrapper">
                    <h2 class="introduction-title">しおや農園について</h2>
                    <img src="{{ asset('image/tool/farmer-introduction-photo.webp') }}" alt="しおや農園について">
                    <p class="introduction-text">
                        私たち「しおや農園」は、代々続く小さな家族農家です。<br>
                        土づくりから収穫まで、すべてを自分たちの手で行い、<br>
                        「自分の子どもに食べさせたい野菜」を合言葉に、<br>
                        安心・安全な旬の野菜を育てています。
                    </p>
                </div>
            </section>
            <section class="store">
                <h2>おすすめ商品</h2>
                <div>
                    @foreach( $recommendItems as $recommendItem )
                        <div>
                            <img src="{{ $recommendItem->img_url }}" alt="商品画像">
                            @if( $recommendItem->isReservedOnly === true )
                                <div>予約限定</div>
                            @endif
                            <div>{{ $recommendItem->name }}</div>
                            <div>{{ $recommendItem->detail }}</div>
                            <div>{{ $recommendItem->price }}</div>
                        </div>
                </div>
            </section>
            <section class="inquiry">
                <h2>お問い合わせ</h2>
                <p>お問い合わせはこちらからお願いいたします</p>
                <div>
                    <a href="{{ route('inquiry') }}">お問い合わせフォームへ</a>
                </div>
            </section>
        </div>
    </div>
@endsection
