{{--
    管理画面の一覧の検索欄の入力欄（input）。type・id・name・value・placeholder などの属性は input に付く。
    大きさ・枠・フォーカスは admin-search-control（app.css。選択欄と共通）。プレースホルダーの色はここだけ。
--}}
<input {{ $attributes->class(['admin-search-control placeholder:text-line-strong']) }}>
