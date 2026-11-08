@props(['blankLabel', 'blankSelected' => true])

{{--
    管理画面の一覧の検索欄の選択欄（select）。id・name などの属性は select に付く。大きさ・枠・フォーカスは admin-search-control（app.css。入力欄と共通）。
    - blankLabel：何も選ばない（value=""）選択肢の表示（例：発送状況）。PC・SPとも同じ表示にする（SPだけ「すべて」にはしない）
    - blankSelected：何も選ばない選択肢を選んでいる状態にするか（検索欄に戻した値が空のとき true）
    その他の選択肢（<option>）はスロットに渡す。
    矢印はブラウザ標準のものを使わず（appearance-none）、Figma（157:2）の下向きの三角形（色 line-strong＝#acacac、右端から16px内側）を、select の上に重ねた span で描く。
    押す操作は select が受けるため、三角形は pointer-events-none。強制カラー（Windows のハイコントラスト）では、背景色の三角形が描かれず矢印が消えるため、三角形を隠して select をブラウザ標準の矢印に戻す（forced-colors:appearance-auto・forced-colors:hidden）。右の余白（pr-10）で、選択中の文字が三角形と重ならないようにする。
--}}
<div class="relative">
    <select {{ $attributes->class(['admin-search-control appearance-none pr-10 forced-colors:appearance-auto']) }}>
        <option value="" @selected($blankSelected)>{{ $blankLabel }}</option>
        {{ $slot }}
    </select>
    <span class="pointer-events-none absolute top-1/2 right-4 h-2 w-3 -translate-y-1/2 bg-line-strong [clip-path:polygon(0_0,100%_0,50%_100%)] forced-colors:hidden" aria-hidden="true"></span>
</div>
