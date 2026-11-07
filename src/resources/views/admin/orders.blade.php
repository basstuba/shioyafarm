@extends('layouts.admin.app')

@section('page-title', '注文一覧 | 管理画面 | Shioya Farm')

{{--
    管理画面の注文一覧（本体は admin/partials/order-list。詳細モーダルは Livewire の AdminPurchaseDetail）。レイアウトは layouts/admin/app（サイドメニューあり）。
    - $purchases：注文のページネーター（注文者を Eager Load 済み）
    - $conditions：検索欄に戻す検索条件（shipped・date・name）
    - $exportCount：エクスポートで出力される件数
--}}
@section('content')
    @include('admin.partials.order-list', [
        'records' => $purchases,
        'conditions' => $conditions,
        'exportCount' => $exportCount,
        'listType' => \App\Enums\AdminListType::Purchase,
    ])

    <livewire:admin-purchase-detail />
@endsection
