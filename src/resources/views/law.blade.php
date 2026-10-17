@extends('layouts.app')

@section('body-class', 'legal-page')

@section('content')
    <div class="pt-header">
        <div class="mx-auto mb-20 max-w-[1520px] px-4">
            <x-page-title>特定商取引法に基づく表記</x-page-title>
            <hr class="my-20 border-line-strong">
            <section class="my-8 text-base">
                <dt class="mb-4 font-extrabold">事業者名</dt>
                <dd>しおや農園</dd>
            </section>
            <hr class="border-line">
            <section class="my-8 text-base">
                <dt class="mb-4 font-extrabold">代表者名</dt>
                <dd>塩谷貴良</dd>
            </section>
            <hr class="border-line">
            <section class="my-8 text-base">
                <dt class="mb-4 font-extrabold">所在地</dt>
                <dd class="flex flex-col gap-4">
                    <p>〒091-2345</p>
                    <p>北海道架空郡架空町567-34</p>
                </dd>
            </section>
            <hr class="border-line">
            <section class="my-8 text-base">
                <dt class="mb-4 font-extrabold">電話番号</dt>
                <dd>01234-5-6789</dd>
            </section>
            <hr class="border-line">
            <section class="my-8 text-base">
                <dt class="mb-4 font-extrabold">メールアドレス</dt>
                <dd>shioyafarm@example.com</dd>
            </section>
            <hr class="border-line">
            <section class="my-8 text-base">
                <dt class="mb-4 font-extrabold">販売価格</dt>
                <dd>すべて税込み価格になります。詳しくは商品一覧ページを参照してください。</dd>
            </section>
            <hr class="border-line">
            <section class="my-8 text-base">
                <dt class="mb-4 font-extrabold">その他必要になる費用</dt>
                <dd>別途送料がかかります。詳しくは送料案内ページを参照してください。</dd>
            </section>
            <hr class="border-line">
            <section class="my-8 text-base">
                <dt class="mb-4 font-extrabold">お支払い方法</dt>
                <dd class="flex flex-col gap-4">
                    <p>銀行振込</p>
                    <p>クレジットカード決済</p>
                    <p>代金引換</p>
                    <p>※銀行振込とクレジットカード決済につきましては、お支払いは前払いとなります。</p>
                </dd>
            </section>
            <hr class="border-line">
            <section class="my-8 text-base">
                <dt class="mb-4 font-extrabold">商品のお届け時期</dt>
                <dd class="flex flex-col gap-4">
                    <p>お支払い確定後3~5営業日以内に発送いたします。</p>
                    <p>代金引換はご注文確定後3~5営業日以内に発送いたします。</p>
                </dd>
            </section>
            <hr class="border-line">
            <section class="my-8 text-base">
                <dt class="mb-4 font-extrabold">返品について</dt>
                <dd class="flex flex-col gap-4">
                    <p>商品の特性上お客様都合による返品はお受けできません。</p>
                    <p>万が一不良品が届いた場合は、到着後3日以内にご連絡をいただいた場合のみ交換または返金をさせていただきます。</p>
                </dd>
            </section>
            <hr class="border-line">
        </div>
    </div>
@endsection
