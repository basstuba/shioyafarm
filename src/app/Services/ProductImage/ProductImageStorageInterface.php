<?php

namespace App\Services\ProductImage;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * 商品画像の保存のインターフェース（管理画面の掲載商品の登録・修正でアップロードされた画像）
 *
 * 重い処理（読み込み・縮小・向きの補正・書き出し）の prepare と、ファイルを書くだけの save に分ける。
 * 呼び出し側は、prepare をトランザクションの前に済ませ、確認が通ってから save する。
 * テストでは、このインターフェースをモックに差し替えられる。
 */
interface ProductImageStorageInterface
{
    /** 保存先（public ディスクの中。img_url は image/product/uploads/xxx.webp の形になる。シードの画像は image/product/ 直下） */
    public const string DIRECTORY = 'image/product/uploads';

    /**
     * アップロードされた画像を読み込み、縮小・向きの補正をして、保存できる形にする（まだファイルは書かない）
     *
     * @throws ValidationException 画像を読み込めない・縦横が大きすぎるとき（image の欄のエラー）
     */
    public function prepare(UploadedFile $file): PreparedImage;

    /**
     * 用意した画像をファイルに書き、public ディスクの中のパス（image/product/uploads/xxx.webp）を返す
     *
     * @throws RuntimeException 書けなかったとき
     */
    public function save(PreparedImage $image): string;

    /**
     * 保存した画像のファイルを消す（DIRECTORY の中のファイルだけ。シードの画像など、それ以外のパスは何もしない）
     */
    public function delete(string $path): void;
}
