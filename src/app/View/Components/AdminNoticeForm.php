<?php

namespace App\View\Components;

use App\Models\Notice;
use App\Support\OldInput;
use App\View\Components\Concerns\AdminFormStyle;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Override;

/**
 * 管理画面のお知らせの作成・修正の共通フォーム（Figma お知らせ作成 172:2・notice-create-button 172:24。修正は設計書・Figma にない追加で、作成の形を流用する）
 *
 * - mode は create（作成。空で開く。橙の「作成する」）／edit（修正。今の値が入った状態で開く。青の「修正する」）。これ以外の値は create と同じ
 * - notice は修正するお知らせ（作成は null）
 * - 入力欄の初期値は、old() の値（入力エラー・419で戻ったときに残す）を優先し、なければ修正するお知らせの今の値（作成は空）にする。old() の整形は OldInput
 * - Alpine の x-data（二重送信防止）は Blade に書く。ここは x-data に渡す値を持たず、送信先・文言・入力欄の初期値だけを持つ。クラスの組み立ては AdminFormStyle（掲載商品のフォームと共通）
 */
class AdminNoticeForm extends Component
{
    use AdminFormStyle;

    /** お知らせタイトルの初期値 */
    public readonly string $title;

    /** お知らせ内容の初期値 */
    public readonly string $text;

    public function __construct(
        public string $mode = 'create',
        public ?Notice $notice = null,
    ) {
        $this->title = OldInput::scalar('title', $notice->title ?? '');
        $this->text = OldInput::scalar('text', $notice->text ?? '');
    }

    /**
     * 修正かどうか
     */
    #[Override]
    public function isEdit(): bool
    {
        return $this->mode === 'edit';
    }

    /**
     * 送信先（作成・修正）
     */
    public function action(): string
    {
        return $this->isEdit() ? route('admin.notices.update') : route('admin.notices.store');
    }

    /**
     * 送信ボタンの文言
     */
    public function submitLabel(): string
    {
        return $this->isEdit() ? '修正する' : '作成する';
    }

    #[Override]
    public function render(): View
    {
        return view('components.admin-notice-form');
    }
}
