<?php

namespace App\Support;

/**
 * 商品一覧ページ（shop/items）の「ご案内」（アコーディオン）の項目
 *
 * 送料・支払方法・お届け・保存方法・返品の文言。金額と発送時期は config/shipping.php から取る（直書きしない）。
 */
final class ItemGuide
{
    /**
     * 項目の一覧を返す（lines は1文ずつ段落にする）
     *
     * @return list<array{title: string, lines: list<string>}>
     */
    public static function sections(): array
    {
        return [
            [
                'title' => '送料について',
                'lines' => [
                    '送料は別途ご負担となります。',
                    '送料は提携運送会社の価格に準じます。',
                ],
            ],
            [
                'title' => '支払方法について',
                'lines' => [
                    'クレジットカード・銀行振込・代金引換をご利用いただけます。',
                    '代金引換の場合は、別途手数料'.number_format(config('shipping.cod_fee')).'円（税込）がかかります。',
                    '予約限定商品は、クレジットカードまたは銀行振込による先払いとなります。',
                ],
            ],
            [
                'title' => 'お届けについて',
                'lines' => [
                    'お支払いの確定後、'.config('shipping.ship_days_text').'以内に発送いたします（代金引換はご注文の確定後）。',
                    '収穫状況により、発送が前後する場合がございます。',
                    '北の恵みセットは'.config('shipping.reservation_ship_period_text').'の発送となります。',
                    '発送元：北海道架空郡架空町567-34',
                ],
            ],
            [
                'title' => '保存方法',
                'lines' => [
                    '冷暗所で保存してください。',
                    '生鮮品のため、到着後はお早めにお召し上がりください。',
                    '人参は冷蔵での保存をおすすめします。',
                ],
            ],
            [
                'title' => '返品について',
                'lines' => [
                    '商品の特性上、お客様のご都合による返品はお受けできません。',
                    '万が一不良品が届いた場合は、商品の到着後3日以内にご連絡ください。交換または返金にて対応いたします。',
                ],
            ],
        ];
    }
}
