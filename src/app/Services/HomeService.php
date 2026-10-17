<?php

namespace App\Services;

use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Repositories\Interfaces\NoticeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * トップページサービス
 */
class HomeService
{
    /** トップページに表示するお知らせの件数 */
    private const NOTICE_LIMIT = 5;

    /** トップページに表示するおすすめ商品の件数 */
    private const RECOMMEND_ITEM_LIMIT = 3;

    private ItemRepositoryInterface $itemRepository;

    private NoticeRepositoryInterface $noticeRepository;

    public function __construct(ItemRepositoryInterface $itemRepository, NoticeRepositoryInterface $noticeRepository)
    {
        $this->itemRepository = $itemRepository;
        $this->noticeRepository = $noticeRepository;
    }

    /**
     * 最新のお知らせを取得
     */
    public function getNoticeList(): Collection
    {
        return $this->noticeRepository->getNoticeList(self::NOTICE_LIMIT);
    }

    /**
     * おすすめ商品を取得
     */
    public function getRecommendItems(): Collection
    {
        return $this->itemRepository->getRecommendItems(self::RECOMMEND_ITEM_LIMIT);
    }
}
