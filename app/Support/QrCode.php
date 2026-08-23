<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    /** 生成二维码，返回可直接放进 <img src> 的 data URI（SVG） */
    public static function dataUri(string $text, int $size = 180): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString($text);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
