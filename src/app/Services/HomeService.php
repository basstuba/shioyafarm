<?php

namespace App\Services;

use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Repositories\Interfaces\NoticeRepositoryInterface;

/**
 * トップページサービス
 */
class HomeService
{
    private ItemRepositoryInterface $itemRepository;
    private NoticeRepositoryInterface $noticeRepository;

    public function __construct(ItemRepositoryInterface $itemRepository, NoticeRepositoryInterface $noticeRepository)
    {
        $this->itemRepository = $itemRepository;
        $this->noticeRepository = $noticeRepository;
    }

    /**
     * お知らせ一覧を取得
     */
    public function getNoticeList()
    {
        return $this->noticeRepository->getNoticeList();
    }

    /**
     * おすすめ商品取得
     */
    public function getRecommendItems()
    {
        return $this->itemRepository->getRecommendItems();
    }
}
