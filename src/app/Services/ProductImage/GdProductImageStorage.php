<?php

namespace App\Services\ProductImage;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Override;
use RuntimeException;

/**
 * GD で商品画像を処理して public ディスクに保存する
 *
 * ファイルのパスから読み込み、長辺を 1200px 以下に縮小（小さい画像は拡大しない）してから、jpeg の EXIF の向きを補正し、
 * webp（品質80）にして image/product/uploads/ にハッシュ名で保存する。
 * 縮小を先にするのは、大きな画像の元の大きさの GD 画像を2枚同時に持たないため（メモリ不足を防ぐ）。
 * jpeg の EXIF の向きは、exif 拡張があれば補正し、なければそのまま保存する。
 * GD に webp の書き出しがない環境では、縮小した画像を元の形式（png は png、それ以外は jpeg）で保存する。
 */
class GdProductImageStorage implements ProductImageStorageInterface
{
    /** 縮小したあとの長辺の上限（px） */
    public const int MAX_EDGE = 1200;

    /** webp・jpeg の品質 */
    public const int QUALITY = 80;

    /** 読み込む画像の画素数の上限（GD は画素数×4バイト程度のメモリを使うため。8000×8000 まで） */
    public const int MAX_PIXELS = 64_000_000;

    /** 画像を読み込めなかったときの案内 */
    public const string UNREADABLE_MESSAGE = '画像を読み込めませんでした。別の画像を選んでください';

    /** 画像の縦横が大きすぎるときの案内 */
    public const string TOO_LARGE_MESSAGE = '画像の縦横が大きすぎます。小さい画像を選んでください';

    #[Override]
    public function prepare(UploadedFile $file): PreparedImage
    {
        $path = $file->getRealPath();
        $info = $path === false ? false : @getimagesize($path);
        if ($path === false || $info === false) {
            throw $this->invalid(self::UNREADABLE_MESSAGE);
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw $this->invalid(self::TOO_LARGE_MESSAGE);
        }

        $source = $this->load($path, $info[2]);
        if (! $source instanceof GdImage) {
            throw $this->invalid(self::UNREADABLE_MESSAGE);
        }

        // 縮小してから回転する（元の大きさの画像を2枚同時に持たない）。縮小しても長辺は変わらないので、回転の前後で上限は同じ
        $resized = $this->shrink($source, self::MAX_EDGE);
        unset($source);
        $image = $this->orient($resized, $this->orientation($path, $info[2]));
        [$binary, $extension] = $this->encode($image, $info[2]);

        return new PreparedImage($binary, $extension);
    }

    #[Override]
    public function save(PreparedImage $image): string
    {
        $path = self::DIRECTORY.'/'.Str::random(40).'.'.$image->extension;
        if (! Storage::disk('public')->put($path, $image->binary)) {
            throw new RuntimeException('商品画像を保存できませんでした。');
        }

        return $path;
    }

    #[Override]
    public function delete(string $path): void
    {
        // シードの画像（uploads の外）や、上の階層へたどるパスは消さない
        if (! str_starts_with($path, self::DIRECTORY.'/') || str_contains($path, '..')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * 画像の形式に合わせて、ファイルのパスから GD の画像に読み込む（読み込めなければ false）
     */
    private function load(string $path, int $type): GdImage|false
    {
        return match (true) {
            $type === IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            $type === IMAGETYPE_PNG => @imagecreatefrompng($path),
            $type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($path),
            default => false,
        };
    }

    /**
     * jpeg の EXIF の向き（1〜8。exif 拡張がない・jpeg でない・情報がないときは 1）
     */
    private function orientation(string $path, int $type): int
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path, 'IFD0');
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    /**
     * EXIF の向きに合わせて、正しい向きに直す（2・4・5・7 は鏡像を含むので、反転と回転を組み合わせる）
     *
     * imagerotate の角度は反時計回り。
     */
    private function orient(GdImage $image, int $orientation): GdImage
    {
        // 2：左右反転、3：180度、4：上下反転、5：左右反転して反時計回りに90度、6：時計回りに90度、7：左右反転して時計回りに90度、8：反時計回りに90度
        $flip = match ($orientation) {
            2, 5, 7 => IMG_FLIP_HORIZONTAL,
            4 => IMG_FLIP_VERTICAL,
            default => null,
        };
        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };

        if ($flip !== null) {
            imageflip($image, $flip);
        }
        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * 長辺が上限を超えているときだけ、縦横比を保って縮小する（透明は保つ）
     */
    private function shrink(GdImage $image, int $maxEdge): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);
        if ($longest <= $maxEdge) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $maxEdge / $longest));
        $newHeight = max(1, (int) round($height * $maxEdge / $longest));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if (! $resized instanceof GdImage) {
            return $image;
        }
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, (int) imagecolorallocatealpha($resized, 255, 255, 255, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    /**
     * 画像を書き出す（webp。GD に webp の書き出しがなければ、png は png、それ以外は jpeg）
     *
     * @return array{0: string, 1: string} 画像のバイト列と拡張子
     */
    private function encode(GdImage $image, int $sourceType): array
    {
        // パレット形式の画像は、書き出す前にトゥルーカラーにする
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($image, null, self::QUALITY);
            $extension = 'webp';
        } elseif ($sourceType === IMAGETYPE_PNG) {
            imagepng($image);
            $extension = 'png';
        } else {
            imagejpeg($image, null, self::QUALITY);
            $extension = 'jpg';
        }

        return [(string) ob_get_clean(), $extension];
    }

    /**
     * 画像の欄のエラー
     */
    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['image' => $message]);
    }
}
