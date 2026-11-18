<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Override;

/**
 * 公開デモの案内（ポートフォリオ用のデモサイトであることの注意書き）
 *
 * 環境変数 DEMO_NOTICE（config('demo.notice')）が true のときだけ表示する。公開側・管理画面のレイアウトの上部で使う。
 * - variant：public（公開側。既存の配色の桃色の帯）／admin（管理画面。白・黒・グレーの色調の濃い灰色の帯）
 */
class DemoNotice extends Component
{
    /** 案内の文言 */
    public const string MESSAGE = 'ポートフォリオ用のデモサイトです。実在の氏名・住所・メールアドレスなどは入力しないでください。決済はテストモードで、メールは送信されません。データは毎日午前4時に初期化されます。';

    public function __construct(
        public string $variant = 'public',
    ) {}

    /**
     * 表示するか（DEMO_NOTICE がオンのときだけ）
     */
    #[Override]
    public function shouldRender(): bool
    {
        return (bool) config('demo.notice');
    }

    /**
     * 案内の文言
     */
    public function message(): string
    {
        return self::MESSAGE;
    }

    /**
     * 帯のクラス（variant ごとの地・文字・フォント）
     */
    public function classes(): string
    {
        return $this->variant === 'admin'
            ? 'bg-admin-head font-sans text-white'
            : 'border-b border-primary bg-peach font-maru text-ink';
    }

    #[Override]
    public function render(): View
    {
        return view('components.demo-notice');
    }
}
