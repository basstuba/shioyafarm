@props(['blankLabel', 'blankSelected' => true])

{{--
    管理画面の一覧の検索欄の選択欄（select）。id・name などの属性は select に付く。大きさ・枠・フォーカスは admin-search-control（app.css。入力欄と共通）。
    - blankLabel：何も選ばない（value=""）選択肢のPCでの表示（例：発送済 / 未発送）。SP（lg未満）では「すべて」と表示する
    - blankSelected：何も選ばない選択肢を選んでいる状態にするか（検索欄に戻した値が空のとき true）
    その他の選択肢（<option>）はスロットに渡す。
--}}
<select {{ $attributes->class(['admin-search-control']) }} x-data="{ wide: matchMedia('(min-width: 1024px)').matches }" x-on:resize.window="wide = matchMedia('(min-width: 1024px)').matches">
    <option value="" x-text="wide ? '{{ $blankLabel }}' : 'すべて'" @selected($blankSelected)>{{ $blankLabel }}</option>
    {{ $slot }}
</select>
