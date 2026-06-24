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
                <div class="slide"></div>
                <div class="slide"></div>
                <div></div>
            </section>
            <section class="information"></section>
            <section class="introduction"></section>
            <section class="store"></section>
            <section class="inquiry"></section>
        </div>
    </div>
@endsection
