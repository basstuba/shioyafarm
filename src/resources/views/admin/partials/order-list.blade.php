{{--
    管理画面の注文一覧・予約一覧の本体（見出し・検索・エクスポート・表・ページ送り）。両方のページから @include する。
    - $records：注文または予約のページネーター（注文者・予約者を Eager Load 済み）
    - $conditions：検索欄に戻す検索条件（shipped・date・name。エクスポートにも同じ条件を渡す）
    - $exportCount：エクスポートで出力される件数（発送状況が「取消済」のとき以外は取消済みを含めない。0件のときはエクスポートを押せない）
    - $listType：一覧の種類（App\Enums\AdminListType）。見出し・ラベル・案内文の語（注文・予約）、リセット・検索・発送済みの更新のルート、「詳細」ボタンのイベント名はここから決まる
    検索フォームと入力欄・ボタンは <x-admin-search-form> などの部品（会員一覧と共通）。
    PC（lg以上）は最大幅960pxの表（列の幅は割合）、SPはカード形式（同じ table 要素を、グリッドでカードの形にする）。
    発送済みのチェックは、押した瞬間に Alpine から、発送済みの更新のルートへ送る（ページは移動しない）。保存できなかったときは、チェックと行の色を戻して案内を出す。
    取消済みの行は、チェックの代わりに「取消済」と出し、文字を灰色にする（地は未発送と同じ。発送済みの色にしない）。取消の操作は詳細のモーダルにある（admin/partials/order-detail-modal）。
--}}
@php
    $subject = $listType->subject();
    $focusClass = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';
    // 検索欄に戻す値。配列など文字列でない入力は戻さない（TypeError にしない）。不正な日付は type=date の欄に戻さない（コンソール警告になる）
    $restore = fn (string $key): string => \App\Support\OldInput::scalar($key, $conditions[$key]);
    $dateValue = $errors->has('date') ? '' : $restore('date');
    $shippedValue = $restore('shipped');
    $cellClass = 'lg:table-cell lg:border-b lg:border-line lg:px-3 lg:align-middle';
@endphp

<div class="flex flex-col items-center">
    <h1 class="text-center text-[28px] font-normal text-accent-hover lg:text-[32px]">{{ $subject }}一覧</h1>

    {{-- 検索（GET）。ラベルはPCでは読み上げ専用、SPでは表示する。入力の誤り（注文日が日付でないなど）は、検索欄の近くに出す --}}
    <x-admin-search-form :action="route($listType->searchRoute())" :label="$subject.'の検索'" :reset-href="route($listType->listRoute())">
        <x-admin-search-field label="発送状況" for="shipped" width="lg:w-[200px]">
            {{-- 何も選ばないときの表示は、PCは「発送状況」、SPは「すべて」 --}}
            <x-admin-search-select id="shipped" name="shipped" blank-label="発送状況" :blank-selected="$shippedValue === ''">
                <option value="{{ \App\Enums\AdminListStatus::Unshipped->value }}" @selected($shippedValue === \App\Enums\AdminListStatus::Unshipped->value)>未発送</option>
                <option value="{{ \App\Enums\AdminListStatus::Shipped->value }}" @selected($shippedValue === \App\Enums\AdminListStatus::Shipped->value)>発送済</option>
                <option value="{{ \App\Enums\AdminListStatus::Canceled->value }}" @selected($shippedValue === \App\Enums\AdminListStatus::Canceled->value)>取消済</option>
            </x-admin-search-select>
        </x-admin-search-field>
        <x-admin-search-field :label="$subject.'日'" for="date" width="lg:w-[190px]">
            <x-admin-search-input id="date" name="date" type="date" :value="$dateValue" />
        </x-admin-search-field>
        <x-admin-search-field :label="$subject.'者名'" for="name" width="lg:w-[300px]">
            <x-admin-search-input id="name" name="name" type="text" maxlength="100" :value="$restore('name')" :placeholder="$subject.'者名を入力してください'" autocomplete="off" />
        </x-admin-search-field>
    </x-admin-search-form>

    <div
        class="mt-6 w-full max-w-[960px]"
        x-data="{
            message: '',
            async save(id, shipped) {
                this.message = '';
                let expired = false;
                let conflict = false;
                try {
                    const response = await fetch('{{ route($listType->updateRoute()) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ id: id, shipped: shipped }),
                    });
                    if (response.ok) {
                        return true;
                    }
                    // ログインの期限切れ（401）・ページの期限切れ（419）は、再試行しても直らない
                    expired = response.status === 401 || response.status === 419;
                    // 別の画面で取り消された（取消済みは発送済みにできない）
                    conflict = response.status === 409;
                } catch (error) {
                    // 通信エラーも、保存できなかった扱いにする
                }
                this.message = expired ? 'ページの有効期限が切れました。ページを再読み込みしてください。' : conflict ? '取り消されているため、発送状況を変更できません。ページを再読み込みしてください。' : '保存できませんでした。もう一度お試しください。';
                return false;
            },
        }"
    >
        {{-- 発送済みの保存に失敗したときの案内 --}}
        <p class="mb-4 rounded-[10px] border border-line-strong bg-surface px-4 py-3 text-base text-ink" role="status" x-text="message" x-show="message" x-cloak></p>

        {{-- エクスポート（検索中は、その条件に合う全件を出力する。発送状況が「取消済」のとき以外は取消済みを含めない）。出力が0件のとき、検索欄に入力エラーがあるとき（検索欄の値と、一覧・出力の条件がずれるため）は押せない。取消の案内（errors の cancel）は検索条件の誤りではないため、押せなくしない --}}
        <form class="flex justify-end" action="{{ route('admin.export') }}" method="POST">
            @csrf
            <input type="hidden" name="type" value="{{ $listType->value }}">
            <input type="hidden" name="shipped" value="{{ $conditions['shipped'] }}">
            <input type="hidden" name="date" value="{{ $conditions['date'] }}">
            <input type="hidden" name="name" value="{{ $conditions['name'] }}">
            <x-admin-search-button variant="export" :disabled="$exportCount === 0 || collect($errors->keys())->diff(['cancel'])->isNotEmpty()">エクスポート</x-admin-search-button>
        </form>

        @if ($records->total() === 0)
            <p class="mt-6 text-center text-base">該当する{{ $subject }}はありません。</p>
        @else
            <table class="mt-4 block w-full lg:table lg:table-fixed">
                <caption class="sr-only">{{ $subject }}一覧</caption>
                <colgroup>
                    <col class="lg:w-[10%]">
                    <col class="lg:w-[24%]">
                    <col class="lg:w-[20%]">
                    <col class="lg:w-[34%]">
                    <col class="lg:w-[12%]">
                </colgroup>
                <thead class="hidden lg:table-header-group">
                    <tr class="h-[35px] bg-admin-head text-left text-white">
                        <th scope="col" class="px-3 text-center text-base font-normal">発送済</th>
                        <th scope="col" class="px-3 text-base font-normal">{{ $subject }}管理番号</th>
                        <th scope="col" class="px-3 text-base font-normal">{{ $subject }}日</th>
                        <th scope="col" class="px-3 text-base font-normal">{{ $subject }}者</th>
                        <th scope="col" class="px-3 text-base font-normal"><span class="sr-only">詳細</span></th>
                    </tr>
                </thead>
                <tbody class="flex flex-col gap-3 lg:table-row-group">
                    @foreach ($records as $record)
                        @php
                            $canceled = $record->canceled_at !== null;
                            // 取消済みの行の SP のラベルも、値と同じ灰色にする（ふだんは濃い灰色）
                            $labelClass = $canceled ? 'text-admin-gray' : 'text-cancel-text';
                        @endphp
                        {{-- 発送済みの行は地が admin-shipped（白と灰色の交互より優先）。文字色は変えない。取消済みの行は地を未発送と同じにして、文字を灰色にする --}}
                        <tr
                            class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1 rounded-[10px] border border-line px-4 py-3 lg:table-row lg:h-[45px] lg:rounded-none lg:border-0 lg:p-0 {{ ! $canceled && $record->shipped ? 'bg-admin-shipped' : 'bg-white lg:even:bg-admin-bar' }} {{ $canceled ? 'text-admin-gray' : '' }}"
                            @unless ($canceled)
                                x-data="{ shipped: @js($record->shipped), saving: false }"
                                x-bind:class="{ 'bg-admin-shipped': shipped, 'bg-white lg:even:bg-admin-bar': ! shipped }"
                            @endunless
                        >
                            <td class="col-start-2 row-start-1 {{ $cellClass }} lg:text-center">
                                @if ($canceled)
                                    <span class="flex h-11 items-center justify-end text-base lg:justify-center">取消済</span>
                                @else
                                    <label class="flex h-11 cursor-pointer items-center justify-end gap-2 text-base lg:justify-center">
                                        <span class="lg:sr-only">発送済</span>
                                        <input
                                            class="size-6 cursor-pointer accent-admin-gray aria-busy:cursor-wait {{ $focusClass }}"
                                            type="checkbox"
                                            aria-label="{{ $subject }}{{ $record->id }}を発送済みにする"
                                            @checked($record->shipped)
                                            x-model="shipped"
                                            x-bind:aria-busy="saving"
                                            x-on:change="if (saving) { shipped = ! shipped; return } saving = true; if (! await save({{ $record->id }}, shipped)) { shipped = ! shipped } saving = false"
                                        >
                                    </label>
                                @endif
                            </td>
                            <td class="col-start-1 row-start-1 self-center text-base {{ $cellClass }}">
                                <span class="mr-2 {{ $labelClass }} lg:hidden">{{ $subject }}管理番号</span>{{ $record->id }}
                            </td>
                            <td class="col-start-1 row-start-2 text-base {{ $cellClass }}">
                                <span class="mr-2 {{ $labelClass }} lg:hidden">{{ $subject }}日</span>{{ $record->created_at?->format('Y/m/d') }}
                            </td>
                            <td class="col-start-1 row-start-3 min-w-0 text-base break-words {{ $cellClass }}">
                                <span class="mr-2 {{ $labelClass }} lg:hidden">{{ $subject }}者</span>{{ $record->user->full_name }}
                            </td>
                            <td class="col-start-2 row-span-2 row-start-2 self-start {{ $cellClass }}">
                                <x-admin-detail-button :label="$subject.$record->id.'の詳細を見る'" :event="$listType->detailEvent()" :id="$record->id" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @include('admin.partials.pager', ['paginator' => $records])
</div>
