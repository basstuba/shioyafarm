<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Notice;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Repositories\Interfaces\NoticeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * トップページサービス
 */
class HomeService
{
    /** トップページに表示するお知らせの件数 */
    private const int NOTICE_LIMIT = 5;

    /** トップページに表示するおすすめ商品の件数 */
    private const int RECOMMEND_ITEM_LIMIT = 3;

    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly NoticeRepositoryInterface $noticeRepository,
    ) {}

    /**
     * 最新のお知らせを取得
     *
     * @return Collection<int, Notice>
     */
    public function getNoticeList(): Collection
    {
        return $this->noticeRepository->getNoticeList(self::NOTICE_LIMIT);
    }

    /**
     * おすすめ商品を取得
     *
     * @return Collection<int, Item>
     */
    public function getRecommendItems(): Collection
    {
        return $this->itemRepository->getRecommendItems(self::RECOMMEND_ITEM_LIMIT);
    }
}
