<?php

namespace App\Services\ProductImage;

/**
 * 縮小・書き出しまで済ませた商品画像（メモリ上のバイト列。ファイルに書く前の状態）
 *
 * @see ProductImageStorageInterface::prepare()
 */
final readonly class PreparedImage
{
    /**
     * @param  string  $binary  画像のバイト列
     * @param  string  $extension  拡張子（ドットなし。webp・png・jpg）
     */
    public function __construct(
        public string $binary,
        public string $extension,
    ) {}
}
