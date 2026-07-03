<?php

namespace App\Services\Product;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ProductQrCodeService
{
    public const LABEL_REDIRECT_URL = 'https://harnica.id/foredi';

    public function contentForLabel(string $serial, int $unitLevel): string
    {
        if ($unitLevel >= 1 && $unitLevel <= 3) {
            return self::LABEL_REDIRECT_URL;
        }

        return $serial;
    }

    public function toPngDataUri(string $content): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'scale' => 6,
            'quietzoneSize' => 1,
            'eccLevel' => EccLevel::M,
        ]);

        $png = (new QRCode($options))->render($content);

        return 'data:image/png;base64,'.base64_encode($this->invertPng($png));
    }

    public function toPngTempFile(string $content, string $tempDir): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'scale' => 4,
            'quietzoneSize' => 1,
            'eccLevel' => EccLevel::M,
        ]);

        $png = (new QRCode($options))->render($content);
        $png = $this->invertPng($png);

        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new \RuntimeException('Unable to create QR temp directory: '.$tempDir);
        }

        $filename = uniqid('qr_', true).'.png';
        $path = rtrim($tempDir, '/\\').DIRECTORY_SEPARATOR.$filename;

        if (file_put_contents($path, $png) === false) {
            throw new \RuntimeException('Unable to write QR temp file: '.$path);
        }

        return $filename;
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
