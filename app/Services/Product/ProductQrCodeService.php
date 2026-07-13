<?php

namespace App\Services\Product;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ProductQrCodeService
{
    public const LABEL_REDIRECT_URL = 'https://harnica.id/foredi';

    public const INK_BLACK = 'black';

    public const INK_WHITE_TRANSPARENT = 'white_transparent';

    /** @var array<string, string> */
    private array $tempFileCache = [];

    /** @var array<string, string> */
    private array $dataUriCache = [];

    public function inkStyleForUnitLevel(int $unitLevel): string
    {
        return $unitLevel === 3 ? self::INK_WHITE_TRANSPARENT : self::INK_BLACK;
    }

    public function contentForLabel(string $serial, int $unitLevel): string
    {
        if ($unitLevel >= 1 && $unitLevel <= 3) {
            return self::LABEL_REDIRECT_URL;
        }

        return $serial;
    }

    public function toPngDataUri(string $content, string $inkStyle = self::INK_BLACK): string
    {
        $cacheKey = $content.'|'.$inkStyle;

        if (isset($this->dataUriCache[$cacheKey])) {
            return $this->dataUriCache[$cacheKey];
        }

        $png = $this->renderPng($content, 6);
        $png = $this->applyInkStyle($png, $inkStyle);

        $this->dataUriCache[$cacheKey] = 'data:image/png;base64,'.base64_encode($png);

        return $this->dataUriCache[$cacheKey];
    }

    public function toPngTempFile(string $content, string $tempDir, string $inkStyle = self::INK_BLACK): string
    {
        $cacheKey = $content.'|'.$inkStyle;

        if (isset($this->tempFileCache[$cacheKey])) {
            return $this->tempFileCache[$cacheKey];
        }

        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new \RuntimeException('Unable to create QR temp directory: '.$tempDir);
        }

        $filename = 'qr_'.md5($cacheKey).'.png';
        $path = rtrim($tempDir, '/\\').DIRECTORY_SEPARATOR.$filename;

        if (! file_exists($path)) {
            $png = $this->renderPng($content, 4);
            $png = $this->applyInkStyle($png, $inkStyle);

            if (file_put_contents($path, $png) === false) {
                throw new \RuntimeException('Unable to write QR temp file: '.$path);
            }
        }

        $this->tempFileCache[$cacheKey] = $filename;

        return $filename;
    }

    protected function renderPng(string $content, int $scale): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'scale' => $scale,
            'quietzoneSize' => 1,
            'eccLevel' => EccLevel::M,
        ]);

        return (new QRCode($options))->render($content);
    }

    protected function applyInkStyle(string $png, string $inkStyle): string
    {
        return match ($inkStyle) {
            self::INK_WHITE_TRANSPARENT => $this->toWhiteInkTransparentPng($png),
            default => $this->invertPng($png),
        };
    }

    protected function toWhiteInkTransparentPng(string $png): string
    {
        $image = @imagecreatefromstring($png);

        if ($image === false) {
            return $png;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $result = imagecreatetruecolor($width, $height);

        if ($result === false) {
            imagedestroy($image);

            return $png;
        }

        imagealphablending($result, false);
        imagesavealpha($result, true);

        $transparent = imagecolorallocatealpha($result, 0, 0, 0, 127);
        imagefill($result, 0, 0, $transparent);

        $white = imagecolorallocatealpha($result, 255, 255, 255, 0);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $red = ($rgb >> 16) & 0xFF;

                if ($red < 128) {
                    imagesetpixel($result, $x, $y, $white);
                }
            }
        }

        ob_start();
        imagepng($result);
        $processed = ob_get_clean();

        imagedestroy($image);
        imagedestroy($result);

        return $processed !== false ? $processed : $png;
    }

    protected function invertPng(string $png): string
    {
        $image = @imagecreatefromstring($png);

        if ($image === false) {
            return $png;
        }

        imagefilter($image, IMG_FILTER_NEGATE);

        ob_start();
        imagepng($image);
        $inverted = ob_get_clean();
        imagedestroy($image);

        return $inverted !== false ? $inverted : $png;
    }
}
