@props(['form'])

{{--
    管理画面の資材一覧の、追加・修正のモーダル（追加と修正は同じ形。Figma にない追加の部品で、削除の確認 admin-delete-confirm と詳細モーダルの枠に合わせた）。
    props：form は App\Support\MaterialFormState の初期の状態（モーダルの開閉・追加／修正・入力欄の値・エラーの有無）。
    Alpine の状態は呼び出し側（admin/materials）の x-data が持ち、この部品はそれを読む（formOpen・mode・id・name・stock・location・threshold・showErrors・invalid・sending。開く処理・フォーカスは openCreate()・openEdit()・focusInitial()。各入力欄は x-ref（name・stock・location・threshold）で指す）。呼び出し側は、この部品を x-data の要素の中に置くこと。
    - 追加は POST admin.materials.store、修正は POST admin.materials.update（hidden の id）。送り先と hidden の id は mode で切り替える
    - モーダルは x-trap でフォーカスを閉じ込める。開いたときの最初のフォーカスは、呼び出し側の openCreate()（資材名）・openEdit()（在庫数。数字を選択）・focusInitial()（エラー・419 で開いた状態で読み込まれたとき。最初のエラーの項目、なければ追加は資材名・修正は在庫数）が決めるため、x-trap の自動のフォーカスは切る（noautofocus）。背景のクリックと「閉じる」で閉じる（Esc で閉じるのは呼び出し側の x-data の要素）
    - 在庫数・基準値は inputmode="numeric"（SPで数字のキーボードが出る）。type="number" にしないのは、全角の数字や 1.5 などもサーバー側の検証でエラーの文を出すため
    - 入力の誤り・419 で戻ってきたときのエラーは、サーバーが項目の下に出す（開き直したときは showErrors が false になり隠れる）。枠線の色は、エラーのある項目だけ赤
    - 送信中はボタンを押せなくして二重送信を防ぐ（sending）
    色：「追加する」は橙の地・ink の文字、「修正する」は青の地・白文字。ホバー・フォーカスで濃くなり影がつく（掲載商品の「登録する」「修正する」と同じ）。aria-describedby は、エラーを表示しているときだけエラー文の id を指す（基準値の補足は常に指す）。入力欄は角丸10px・枠 #acacac・プレースホルダー #acacac、補足は muted。
--}}
@php
    $labelClass = 'text-base font-bold text-ink';
    $inputClass = 'h-11 w-full rounded-[10px] border bg-white px-4 text-base text-ink placeholder:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink lg:h-10';
    $buttonClass = 'mt-6 flex h-11 w-full cursor-pointer items-center justify-center rounded-[30px] text-base transition-[background-color,box-shadow] duration-200 hover:shadow-button focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink disabled:cursor-wait disabled:opacity-60';
    $isEdit = $form['mode'] === 'edit';
@endphp
<div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4"
    x-show="formOpen"
    x-cloak
    x-trap.noautofocus="formOpen"
    x-on:click.self="formOpen = false"
    role="dialog"
    aria-modal="true"
    aria-labelledby="admin-material-form-title"
>
    <form
        class="relative max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px] bg-white px-6 py-8 text-ink shadow-panel"
        action="{{ $isEdit ? route('admin.materials.update') : route('admin.materials.store') }}"
        x-bind:action="mode === 'edit' ? @js(route('admin.materials.update')) : @js(route('admin.materials.store'))"
        method="POST"
        novalidate
        x-on:submit="if (sending) { $event.preventDefault(); return }; sending = true"
    >
        @csrf
        <input type="hidden" name="id" value="{{ $form['id'] }}" x-bind:value="id" @disabled(! $isEdit) x-bind:disabled="mode !== 'edit'">

        <h2 id="admin-material-form-title" class="text-center text-xl font-bold" x-text="mode === 'edit' ? '資材の修正' : '資材の追加'">{{ $isEdit ? '資材の修正' : '資材の追加' }}</h2>

        <div class="mt-6 flex flex-col gap-6">
            {{-- 資材名 --}}
            <div class="flex flex-col gap-2">
                <label class="{{ $labelClass }}" for="material-name">資材名</label>
                <input
                    class="{{ $inputClass }} {{ $form['invalid']['name'] ? 'border-danger' : 'border-line-strong' }}"
                    id="material-name"
                    name="name"
                    type="text"
                    value="{{ $form['name'] }}"
                    placeholder="例：化粧箱 5kg用"
                    autocomplete="off"
                    x-ref="name"
                    x-model="name"
                    x-bind:class="{ 'border-danger': showErrors && invalid.name, 'border-line-strong': ! (showErrors && invalid.name) }"
                    x-bind:aria-invalid="showErrors && invalid.name ? 'true' : null"
                    @if ($form['invalid']['name']) aria-describedby="material-name-error" @endif
                    x-bind:aria-describedby="showErrors && invalid.name ? 'material-name-error' : null"
                />
                @error('name')
                    <p id="material-name-error" class="text-base text-danger" x-show="showErrors">{{ $message }}</p>
                @enderror
            </div>

            {{-- 在庫数（修正のモーダルは、ここにフォーカスして数字を選択した状態で開く） --}}
            <div class="flex flex-col gap-2">
                <label class="{{ $labelClass }}" for="material-stock">在庫数</label>
                <input
                    class="{{ $inputClass }} {{ $form['invalid']['stock'] ? 'border-danger' : 'border-line-strong' }}"
                    id="material-stock"
                    name="stock"
                    type="text"
                    inputmode="numeric"
                    value="{{ $form['stock'] }}"
                    autocomplete="off"
                    x-ref="stock"
                    x-model="stock"
                    x-bind:class="{ 'border-danger': showErrors && invalid.stock, 'border-line-strong': ! (showErrors && invalid.stock) }"
                    x-bind:aria-invalid="showErrors && invalid.stock ? 'true' : null"
                    @if ($form['invalid']['stock']) aria-describedby="material-stock-error" @endif
                    x-bind:aria-describedby="showErrors && invalid.stock ? 'material-stock-error' : null"
                />
                @error('stock')
                    <p id="material-stock-error" class="text-base text-danger" x-show="showErrors">{{ $message }}</p>
                @enderror
            </div>

            {{-- 置き場所 --}}
            <div class="flex flex-col gap-2">
                <label class="{{ $labelClass }}" for="material-location">置き場所</label>
                <input
                    class="{{ $inputClass }} {{ $form['invalid']['location'] ? 'border-danger' : 'border-line-strong' }}"
                    id="material-location"
                    name="location"
                    type="text"
                    value="{{ $form['location'] }}"
                    placeholder="例：作業小屋の棚の上段"
                    autocomplete="off"
                    x-ref="location"
                    x-model="location"
                    x-bind:class="{ 'border-danger': showErrors && invalid.location, 'border-line-strong': ! (showErrors && invalid.location) }"
                    x-bind:aria-invalid="showErrors && invalid.location ? 'true' : null"
                    @if ($form['invalid']['location']) aria-describedby="material-location-error" @endif
                    x-bind:aria-describedby="showErrors && invalid.location ? 'material-location-error' : null"
                />
                @error('location')
                    <p id="material-location-error" class="text-base text-danger" x-show="showErrors">{{ $message }}</p>
                @enderror
            </div>

            {{-- 基準値（補足つき） --}}
            <div class="flex flex-col gap-2">
                <label class="{{ $labelClass }}" for="material-threshold">基準値</label>
                <input
                    class="{{ $inputClass }} {{ $form['invalid']['threshold'] ? 'border-danger' : 'border-line-strong' }}"
                    id="material-threshold"
                    name="threshold"
                    type="text"
                    inputmode="numeric"
                    value="{{ $form['threshold'] }}"
                    autocomplete="off"
                    x-ref="threshold"
                    x-model="threshold"
                    x-bind:class="{ 'border-danger': showErrors && invalid.threshold, 'border-line-strong': ! (showErrors && invalid.threshold) }"
                    x-bind:aria-invalid="showErrors && invalid.threshold ? 'true' : null"
                    aria-describedby="material-threshold-note{{ $form['invalid']['threshold'] ? ' material-threshold-error' : '' }}"
                    x-bind:aria-describedby="showErrors && invalid.threshold ? 'material-threshold-note material-threshold-error' : 'material-threshold-note'"
                />
                <p id="material-threshold-note" class="text-base text-muted">在庫数がこの数を下回ると「残りわずか」と表示します。0にすると表示しません。</p>
                @error('threshold')
                    <p id="material-threshold-error" class="text-base text-danger" x-show="showErrors">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- 419（ページの有効期限切れ）で戻ってきたときの案内（赤字） --}}
        @error('form')
            <p class="mt-6 text-base text-danger" role="alert" x-show="showErrors">{{ $message }}</p>
        @enderror

        {{-- 追加は橙の地・ink の文字、修正は青の地・白文字（どちらも押せるのは mode が合うほうだけ） --}}
        <button type="submit" class="{{ $buttonClass }} bg-primary text-ink hover:bg-primary-dark focus-visible:bg-primary-dark" x-show="mode === 'create'" x-bind:disabled="sending">追加する</button>
        <button type="submit" class="{{ $buttonClass }} bg-mail-link text-white hover:bg-mail-link-hover focus-visible:bg-mail-link-hover" x-show="mode === 'edit'" x-bind:disabled="sending">修正する</button>

        {{-- 文字だけの「閉じる」 --}}
        <div class="mt-4 flex justify-center">
            <button type="button" class="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center px-4 text-base hover:underline focus-visible:underline focus-visible:outline-none" x-on:click="formOpen = false">閉じる</button>
        </div>
    </form>
</div>
