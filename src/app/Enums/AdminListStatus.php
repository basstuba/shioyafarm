<?php

namespace App\Enums;

/**
 * 管理画面の一覧（注文一覧・予約一覧）の発送状況の検索条件
 *
 * 値は検索欄の発送状況（shipped）の値。何も選ばないときは空で、この enum では表さない（絞り込まない）。
 */
enum AdminListStatus: string
{
    /** 未発送（未発送で、取り消されていないものだけ） */
    case Unshipped = '0';

    /** 発送済 */
    case Shipped = '1';

    /** 取消済 */
    case Canceled = 'canceled';
}
