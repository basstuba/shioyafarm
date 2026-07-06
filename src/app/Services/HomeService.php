<?php

namespace App\Services;

use App\Repositories\Interfaces\HomeRepositoryInterface;

/**
 * トップページサービス
 */
class HomeService
{
    private HomeRepositoryInterface $homeRepository;

    public function __construct(HomeRepositoryInterface $homeRepository)
    {
        $this->homeRepository = $homeRepository;
    }

    /**
     * お知らせ一覧を取得
     */
    public function getNoticeList()
    {
        return $this->homeRepository->getNoticeList();
    }

    /**
     * おすすめ商品取得
     */
    public function getRecommendItems()
    {
        return $this->homeRepository->getRecommendItems();
    }
}
