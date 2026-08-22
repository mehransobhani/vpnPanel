<?php

namespace App\Support;

use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    public static function svg(string $data, int $size = 300): string
    {
        $writer = new Writer(
            new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)
        );

        return $writer->writeString($data, Encoder::DEFAULT_BYTE_MODE_ECODING);
    }
}
