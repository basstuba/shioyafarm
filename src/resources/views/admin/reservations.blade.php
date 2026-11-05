@extends('layouts.admin.app')

@section('page-title', '予約一覧 | 管理画面 | Shioya Farm')

{{--
    管理画面の予約一覧（本体は admin/partials/order-list。詳細モーダルは Livewire の AdminReservationDetail）。レイアウトは layouts/admin/app（サイドメニューあり）。
    - $reservations：予約のページネーター（予約者を Eager Load 済み）
    - $conditions：検索欄に戻す検索条件（shipped・date・name）
--}}
@section('content')
    @include('admin.partials.order-list', [
        'records' => $reservations,
        'conditions' => $conditions,
        'listType' => \App\Enums\AdminListType::Reservation,
    ])

    <livewire:admin-reservation-detail />
@endsection
