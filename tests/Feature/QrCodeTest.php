<?php

use App\Support\QrCode;

it('generates an svg data uri qr code', function () {
    $uri = QrCode::dataUri('https://example.com/sub/abc');
    expect($uri)->toStartWith('data:image/svg+xml;base64,');
    // 解码后应是有效 SVG
    $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
    expect($svg)->toContain('<svg');
});
