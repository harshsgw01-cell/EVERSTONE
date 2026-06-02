<?php

function tcs_unified_css_block(string $blockName): string
{
    // If a global purchase PDF CSS exists, return it so the 'purchase-pdf' block
    // becomes the global styling for all PDFs that call this helper.
    if ($blockName === 'purchase-pdf') {
        $globalPath = __DIR__ . '/../assets/css/purchase-global.css';
        if (is_file($globalPath)) {
            $g = file_get_contents($globalPath);
            if ($g !== false) return $g;
        }
    }
    $cssPath = __DIR__ . '/../assets/css/tcs-unified.css';
    if (!is_file($cssPath)) {
        return '';
    }

    $css = file_get_contents($cssPath);
    if ($css === false) {
        return '';
    }

    $markers = [
        'purchase-pdf' => [
            'PURCHASE ORDER PDF CSS - MERGED FROM purchase_order_pdf.css',
            'END PURCHASE ORDER PDF CSS',
        ],
    ];

    if (!isset($markers[$blockName])) {
        return '';
    }

    [$startNeedle, $endNeedle] = $markers[$blockName];
    $needlePos = strpos($css, $startNeedle);
    if ($needlePos === false) {
        return '';
    }

    $start = strrpos(substr($css, 0, $needlePos), '/*');
    if ($start === false) {
        return '';
    }

    $endNeedlePos = strpos($css, $endNeedle, $start);
    if ($endNeedlePos === false) {
        return '';
    }

    $end = strpos($css, '*/', $endNeedlePos);
    if ($end === false) {
        return '';
    }

    $block = substr($css, $start, ($end + 2) - $start);

    if ($blockName === 'purchase-pdf') {
        $block = str_replace('@page purchase-pdf-page', '@page', $block);
        $block = str_replace('body.purchase-pdf-page', 'body', $block);
        $block = str_replace('.purchase-pdf-page *', '*', $block);
        $block = str_replace('.purchase-pdf-page ', '', $block);
        $block = str_replace("    page: purchase-pdf-page;\n", '', $block);
        $block = str_replace("    page: purchase-pdf-page;\r\n", '', $block);
    }

    return $block;
}

function pdf_asset_b64(string $path, string $mime, int $maxWidth = 400, int $quality = 80): string
{
    if (!is_file($path)) {
        return '';
    }

    $data = file_get_contents($path);
    if ($data === false) {
        return '';
    }

    if (!function_exists('imagecreatefromstring')) {
        return "data:$mime;base64," . base64_encode($data);
    }

    $src = @imagecreatefromstring($data);
    if (!$src) {
        return "data:$mime;base64," . base64_encode($data);
    }

    $width = imagesx($src);
    $height = imagesy($src);
    $shouldResize = $maxWidth > 0 && $width > $maxWidth;
    $targetWidth = $shouldResize ? $maxWidth : $width;
    $targetHeight = $shouldResize ? (int) round($height * ($targetWidth / $width)) : $height;

    $dst = imagecreatetruecolor($targetWidth, $targetHeight);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    ob_start();
    imagejpeg($dst, null, $quality);
    $optimized = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);

    if ($optimized === false || $optimized === '') {
        return "data:$mime;base64," . base64_encode($data);
    }

    return 'data:image/jpeg;base64,' . base64_encode($optimized);
}
