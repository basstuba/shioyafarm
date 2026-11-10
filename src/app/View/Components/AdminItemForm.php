<?php

namespace App\View\Components;

use App\Http\Requests\ItemStoreRequest;
use App\Models\Item;
use App\Support\OldInput;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Override;

/**
 * 管理画面の掲載商品の登録・修正の共通フォーム（Figma 掲載商品登録 174:56・掲載商品修正 180:21・item-update-button 182:28）
 *
 * - mode は create（登録。ラジオは最初すべて未選択。橙の「登録する」）／edit（修正。今の値を選択済みで開く。青の「修正する」）。これ以外の値は create と同じ
 * - item は修正する商品（登録は null）
 * - 入力欄の初期値は、old() の値（入力エラー・419で戻ったときに残す。画像は残らない）を優先し、なければ修正する商品の今の値（登録は空・未選択）にする。old() の整形は OldInput
 * - Alpine の x-data（プレビュー・ドロップ・二重送信防止など）は Blade に書く。ここは x-data に渡す初期値と、クラスの組み立てだけを持つ
 */
class AdminItemForm extends Component
{
    /** 商品名の初期値 */
    public readonly string $name;

    /** 商品重量（kg）の初期値。未選択は空文字 */
    public readonly string $weight;

    /** 商品金額の初期値 */
    public readonly string $price;

    /** 商品紹介文の初期値 */
    public readonly string $detail;

    /** おすすめ商品の初期値。'1'＝はい／'0'＝いいえ／''＝未選択 */
    public readonly string $recommend;

    /** 予約限定の初期値。'1'＝限定／'0'＝通常／''＝未選択 */
    public readonly string $reservedOnly;

    /** 予約可能数の種類の初期値。上限なし（LIMIT_UNLIMITED）／上限あり（LIMIT_LIMITED）／''＝未選択（予約限定でない商品も '' ） */
    public readonly string $limitType;

    /** 上限ありのときの予約可能数の初期値 */
    public readonly string $reservationLimit;

    /** 今の商品画像のURL（登録は空文字。プレビューの初期値と、選んだ画像を取り消したときの戻り先） */
    public readonly string $currentUrl;

    public function __construct(
        public string $mode = 'create',
        public ?Item $item = null,
    ) {
        $this->name = OldInput::scalar('name', $item->name ?? '');
        $this->weight = OldInput::scalar('weight', $item->weight ?? '');
        $this->price = OldInput::scalar('price', $item->price ?? '');
        $this->detail = OldInput::scalar('detail', $item->detail ?? '');
        $this->recommend = OldInput::scalar('recommend', $item === null ? '' : ($item->recommend ? '1' : '0'));
        $this->reservedOnly = OldInput::scalar('is_reserved_only', $item === null ? '' : ($item->is_reserved_only ? '1' : '0'));
        $this->limitType = OldInput::scalar('reservation_limit_type', $this->savedLimitType());
        $this->reservationLimit = OldInput::scalar('reservation_limit', $item->reservation_limit ?? '');
        $this->currentUrl = $item === null ? '' : asset('storage/'.$item->img_url);
    }

    /**
     * 修正する商品の今の予約可能数の種類（予約限定でなければ空文字）
     */
    private function savedLimitType(): string
    {
        if (! $this->item?->is_reserved_only) {
            return '';
        }

        return $this->item->reservation_limit === null ? ItemStoreRequest::LIMIT_UNLIMITED : ItemStoreRequest::LIMIT_LIMITED;
    }

    /**
     * 修正かどうか
     */
    public function isEdit(): bool
    {
        return $this->mode === 'edit';
    }

    /**
     * 送信先（登録・修正）
     */
    public function action(): string
    {
        return $this->isEdit() ? route('admin.items.update') : route('admin.items.store');
    }

    /**
     * 送信ボタンの文言
     */
    public function submitLabel(): string
    {
        return $this->isEdit() ? '修正する' : '登録する';
    }

    /**
     * 項目名のラベルのクラス
     */
    public function labelClass(): string
    {
        return 'text-base font-bold text-ink';
    }

    /**
     * 文字の入力欄（input・textarea）に共通のクラス（大きさと枠の色は含まない）
     */
    public function inputClass(): string
    {
        return 'rounded-[10px] border bg-white px-4 text-base text-ink placeholder:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink disabled:bg-admin-bar';
    }

    /**
     * 枠線の色のクラス（検証エラーのある項目は赤、ないときは灰色）
     */
    public function borderClass(bool $hasError): string
    {
        return $hasError ? 'border-danger' : 'border-line-strong';
    }

    /**
     * エラー文のクラス
     */
    public function errorClass(): string
    {
        return 'text-base text-danger';
    }

    /**
     * 送信ボタンの色のクラス（登録は橙の地・ink の文字、修正は青の地・白文字。ホバー・フォーカスで濃くなる）
     */
    public function submitClass(): string
    {
        return $this->isEdit()
            ? 'bg-mail-link text-white hover:bg-mail-link-hover focus-visible:bg-mail-link-hover'
            : 'bg-primary text-ink hover:bg-primary-dark focus-visible:bg-primary-dark';
    }

    #[Override]
    public function render(): View
    {
        return view('components.admin-item-form');
    }
}
