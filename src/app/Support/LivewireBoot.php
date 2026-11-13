<?php

namespace App\Support;

use Illuminate\View\ComponentAttributeBag;
use Livewire\Livewire;

/**
 * Livewire が起動する前に押せないようにするボタン・入力欄の属性
 *
 * 回線の遅い環境では、Livewire（と同梱の Alpine）が起動する前に wire:click や wire:change の要素を操作しても反応しない。
 * そこで、ページを最初に描画するときは disabled を付けておき、Alpine が起動したら x-bind:disabled で外す（素のJSは書かない）。
 * - 起動後の Livewire の更新リクエストの描画では disabled を付けない。付けると、更新（morph）で外した disabled が付き直され、押せなくなる。
 * - もともと押せない条件（ページ送りの端など）のときは、起動しても外さず、更新のたびにその条件で disabled を付ける。
 * 使い方：<button {{ \App\Support\LivewireBoot::attributes() }}>。押せない条件があるときは attributes($paginator->onFirstPage())。
 *
 * 入力欄（<input>）は inputAttributes() を使う。disabled の欄はフォームの送信に含まれないため、
 * 購入・予約のフォームの中の数量欄が起動前の確定で抜けてしまう。readonly なら変更は止まり、値は送信される。
 * 起動したら x-bind:readonly="false" で外し、更新リクエストの描画では readonly を付けない（disabled と同じ理由）。
 * 見た目は Tailwind の read-only: で薄くする（readonly の欄は :read-only に一致する）。
 * .live のない wire:model の入力欄（お届け先氏名・電話番号）も同じ。Livewire は起動時に wire:model の値を欄へ書き戻すため、起動前に打った文字が消えるおそれがある。
 *
 * ラジオボタン・選択欄（<select>）は readonly が効かず、disabled にすると送信されなくなる（購入・予約の確定フォームの支払方法・お届け先住所）。
 * そのため lockAttributes() を使い、起動前だけ inert にする。inert はクリック・タップ・キーボード・フォーカスをすべて止めるが、値は送信される。
 * 起動したら x-bind:inert="false" で外し、更新リクエストの描画では付けない（disabled と同じ理由）。見た目は inert: で薄くする。
 * ラジオボタンは枠（<label>）ごと押せる作りなので、枠には LOCKED_LABEL_CLASSES を付ける。
 *
 * 押せないときの見た目のクラスは、ここの定数にまとめる（Blade・コンポーネントに手で書かない）。
 */
final class LivewireBoot
{
    /** disabled のボタンの見た目（薄くして、カーソルを禁止にする） */
    public const string DISABLED_CLASSES = 'disabled:cursor-not-allowed disabled:opacity-60';

    /** 起動前 readonly の入力欄の見た目（inputAttributes() の欄に付ける） */
    public const string READONLY_CLASSES = 'read-only:opacity-60';

    /** 起動前 inert の要素（lockAttributes() を付けた select など）の見た目 */
    public const string LOCKED_CLASSES = 'inert:cursor-not-allowed inert:opacity-60';

    /** inert の入力欄を中に持つ枠（<label>）の見た目。枠ごと押せる作りのラジオボタンで、枠を押しても反応しないようにする */
    public const string LOCKED_LABEL_CLASSES = 'has-[[inert]]:pointer-events-none has-[[inert]]:opacity-60';

    /**
     * ボタン・入力欄に足す属性
     *
     * @param  bool  $disabled  もともと押せない条件（true のときは起動しても押せないまま）
     */
    public static function attributes(bool $disabled = false): ComponentAttributeBag
    {
        return new ComponentAttributeBag([
            'x-data' => '',
            'x-bind:disabled' => $disabled ? 'true' : 'false',
            'disabled' => ($disabled || ! Livewire::isLivewireRequest()) ? true : null,
        ]);
    }

    /**
     * 入力欄に足す属性（起動前は readonly。値は送信される）
     */
    public static function inputAttributes(): ComponentAttributeBag
    {
        return new ComponentAttributeBag([
            'x-data' => '',
            'x-bind:readonly' => 'false',
            'readonly' => Livewire::isLivewireRequest() ? null : true,
        ]);
    }

    /**
     * ラジオボタン・選択欄に足す属性（起動前は inert。値は送信される）
     */
    public static function lockAttributes(): ComponentAttributeBag
    {
        $booting = ! Livewire::isLivewireRequest();

        return new ComponentAttributeBag([
            'x-data' => '',
            'x-bind:inert' => 'false',
            'x-bind:aria-disabled' => 'false',
            'inert' => $booting ? true : null,
            'aria-disabled' => $booting ? 'true' : null,
        ]);
    }
}
