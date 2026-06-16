@extends('layouts.app')

@section('body-class', 'legal-page')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/law.css') }}">
@endsection

@section('content')
    <div class="law">
        <div class="container">
            <h2 class="title">特定商取引法に基づく表記</h2>
            <hr class="divider">
            <section class="section">
                <dt>事業者名</dt>
                <dd>しおや農園</dd>
            </section>
            <hr class="section-divider">
            <section class="section">
                <dt>代表者名</dt>
                <dd>塩谷貴良</dd>
            </section>
            <hr class="section-divider">
            <section class="section">
                <dt>所在地</dt>
                <dd>
                    <p class="section-text">〒091-2345</p>
                    <p class="section-text">北海道架空郡架空町567-34</p>
                </dd>
            </section>
            <hr class="section-divider">
            <section class="section">
                <dt>電話番号</dt>
                <dd>01234-5-6789</dd>
            </section>
            <hr class="section-divider">
            <section class="section">
                <dt>メールアドレス</dt>
                <dd>shioyafarm@example.com</dd>
            </section>
            <hr class="section-divider">
            <section class="section">
                <dt>販売価格</dt>
                <dd>すべて税込み価格になります。詳しくは商品一覧ページを参照してください。</dd>
            </section>
            <hr class="section-divider">
            <section class="section">
                <dt>その他必要になる費用</dt>
                <dd>別途送料がかかります。詳しくは送料案内ページを参照してください。</dd>
            </section>
            <hr class="section-divider">
            <section class="section">
                <dt>お支払い方法</dt>
                <dd>
                    <p class="section-text">銀行振込</p>
                    <p class="section-text">クレジットカード決済</p>
                    <p class="section-text">代金引換</p>
                    <p class="section-text">※銀行振込とクレジットカード決済につきましては、お支払いは前払いとなります。</p>
                </dd>
            </section>
            <hr class="section-divider">
            <section class="section">
                <dt>商品のお届け時期</dt>
                <dd>
                    <p class="section-text">お支払い確定後3~5営業日以内に発送いたします。</p>
                    <p class="section-text">代金引換はご注文確定後3~5営業日以内に発送いたします。</p>
                </dd>
            </section>
            <hr class="section-divider">
            <section class="section">
                <dt>返品について</dt>
                <dd>
                    <p class="section-text">商品の特性上お客様都合による返品はお受けできません。</p>
                    <p class="section-text">万が一不良品が届いた場合は、到着後3日以内にご連絡をいただいた場合のみ交換または返金をさせていただきます。</p>
                </dd>
            </section>
            <hr class="section-divider">
        </div>
    </div>
@endsection
