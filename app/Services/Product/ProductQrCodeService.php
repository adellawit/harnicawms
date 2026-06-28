<?php

namespace App\Services\Product;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ProductQrCodeService
{
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

    /**
     * Invert the colours of a PNG (dark <-> light) so the QR renders with a
     * black background and light modules. Falls back to the original image if
     * GD inversion is not possible.
     */
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
