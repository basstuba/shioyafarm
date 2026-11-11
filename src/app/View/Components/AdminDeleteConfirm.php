<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Override;

/**
 * 管理画面の一覧（掲載商品・お知らせ）の削除の確認モーダル
 *
 * - action：削除の送信先（DELETE のフォームの action）
 * - idPrefix：見出しと注記の id の前につける文字（「{idPrefix}-title」「{idPrefix}-note」。aria-labelledby・aria-describedby が指す）
 * - note：確認の2行目の注記（削除でどうなるか）
 * - titleBefore・titleAfter：確認の1行目（見出し）の、削除するものの名前（Alpine の deleteLabel）の前後の文。見出しは x-text で入れるため、名前に含まれるHTMLの記号は文字のまま出る。前に付ける文がなければ空にする
 */
class AdminDeleteConfirm extends Component
{
    public function __construct(
        public string $action,
        public string $idPrefix,
        public string $note,
        public string $titleBefore = '',
        public string $titleAfter = '',
    ) {}

    /**
     * 見出しの x-text に入れる Alpine の式（前後の文は文字列として囲み、削除するものの名前 deleteLabel を挟む）
     *
     * 前後の文は呼び出し側の固定の文言で、HTMLの記号だけエスケープする（Blade の二重のエスケープを避けるため HtmlString で返す）。
     */
    public function titleExpression(): HtmlString
    {
        $before = $this->titleBefore === '' ? '' : "'".e($this->titleBefore)."' + ";

        return new HtmlString($before.'deleteLabel'." + '".e($this->titleAfter)."'");
    }

    #[Override]
    public function render(): View
    {
        return view('components.admin-delete-confirm');
    }
}
