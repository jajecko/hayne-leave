#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}
if (!extension_loaded('gd')) {
    fwrite(STDERR, "PHP GD extension is required.\n");
    exit(2);
}

$outDir = $argv[1] ?? '/var/www/html/assets/hayne';
if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    throw new RuntimeException('Cannot create icon output directory: ' . $outDir);
}

function strokeLine(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $color, int $width): void
{
    imagesetthickness($image, $width);
    imageline($image, $x1, $y1, $x2, $y2, $color);
}

function roundedRect(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color, int $width): void
{
    $radius = max(1, min($radius, intdiv($x2 - $x1, 2), intdiv($y2 - $y1, 2)));
    imagesetthickness($image, $width);
    imageline($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
    imageline($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
    imageline($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
    imageline($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagearc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color);
    imagearc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color);
    imagearc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color);
    imagearc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color);
}

function drawHayneLeaveIcon(int $size, bool $transparentBackground = false, bool $maskable = false, bool $badge = false): GdImage
{
    $image = imagecreatetruecolor($size, $size);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
    imagefill($image, 0, 0, $transparent);
    imagealphablending($image, true);
    imageantialias($image, true);

    if (!$transparentBackground && !$badge) {
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, $white);
    }

    $black = imagecolorallocatealpha($image, 0, 0, 0, 0);
    $scale = $maskable ? 0.78 : 0.88;
    if ($badge) {
        $scale = 0.82;
    }
    $offset = (1.0 - $scale) / 2.0;
    $X = static fn(float $v): int => (int) round($size * ($offset + ($v * $scale)));
    $Y = $X;
    $line = max(1, (int) round($size * ($badge ? 0.055 : 0.038) * $scale));
    $thin = max(1, (int) round($line * 0.82));

    // Calendar shell.
    roundedRect($image, $X(0.16), $Y(0.18), $X(0.84), $Y(0.84), max(2, (int) round($size * 0.055 * $scale)), $black, $line);
    strokeLine($image, $X(0.16), $Y(0.34), $X(0.84), $Y(0.34), $black, $line);

    // Calendar binders.
    strokeLine($image, $X(0.32), $Y(0.11), $X(0.32), $Y(0.25), $black, $line);
    strokeLine($image, $X(0.68), $Y(0.11), $X(0.68), $Y(0.25), $black, $line);

    // Umbrella canopy inside the calendar.
    imagesetthickness($image, $line);
    imagearc($image, $X(0.50), $Y(0.61), $X(0.44) - $X(0.00), $Y(0.32) - $Y(0.00), 180, 360, $black);
    strokeLine($image, $X(0.28), $Y(0.61), $X(0.72), $Y(0.61), $black, $thin);
    strokeLine($image, $X(0.50), $Y(0.45), $X(0.50), $Y(0.77), $black, $thin);
    strokeLine($image, $X(0.50), $Y(0.45), $X(0.37), $Y(0.61), $black, $thin);
    strokeLine($image, $X(0.50), $Y(0.45), $X(0.63), $Y(0.61), $black, $thin);

    // Umbrella handle.
    imagesetthickness($image, $thin);
    imagearc($image, $X(0.55), $Y(0.75), $X(0.10) - $X(0.00), $Y(0.10) - $Y(0.00), 0, 100, $black);

    return $image;
}

function savePng(GdImage $image, string $path): string
{
    if (!imagepng($image, $path, 9)) {
        throw new RuntimeException('Cannot write PNG: ' . $path);
    }
    imagedestroy($image);
    return $path;
}

function pngBytes(int $size): string
{
    $image = drawHayneLeaveIcon($size);
    ob_start();
    imagepng($image, null, 9);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);
    return $bytes;
}

function writeIco(string $path, array $pngImages): void
{
    $count = count($pngImages);
    $header = pack('vvv', 0, 1, $count);
    $entries = '';
    $payload = '';
    $offset = 6 + ($count * 16);

    foreach ($pngImages as $size => $png) {
        $dimension = $size >= 256 ? 0 : $size;
        $entries .= pack('CCCCvvVV', $dimension, $dimension, 0, 0, 1, 32, strlen($png), $offset);
        $payload .= $png;
        $offset += strlen($png);
    }

    if (file_put_contents($path, $header . $entries . $payload) === false) {
        throw new RuntimeException('Cannot write ICO: ' . $path);
    }
}

$outputs = [];
$outputs[] = savePng(drawHayneLeaveIcon(16), $outDir . '/favicon-16x16.png');
$outputs[] = savePng(drawHayneLeaveIcon(32), $outDir . '/favicon-32x32.png');
$outputs[] = savePng(drawHayneLeaveIcon(180), $outDir . '/apple-touch-icon.png');
$outputs[] = savePng(drawHayneLeaveIcon(192), $outDir . '/pwa-icon-192.png');
$outputs[] = savePng(drawHayneLeaveIcon(512), $outDir . '/pwa-icon-512.png');
$outputs[] = savePng(drawHayneLeaveIcon(192, false, true), $outDir . '/pwa-icon-maskable-192.png');
$outputs[] = savePng(drawHayneLeaveIcon(512, false, true), $outDir . '/pwa-icon-maskable-512.png');
$outputs[] = savePng(drawHayneLeaveIcon(128, true, false, true), $outDir . '/notification-badge-128.png');
writeIco($outDir . '/favicon.ico', [16 => pngBytes(16), 32 => pngBytes(32)]);
$outputs[] = $outDir . '/favicon.ico';

foreach ($outputs as $output) {
    if (!is_file($output) || filesize($output) === 0) {
        throw new RuntimeException('Generated icon is missing or empty: ' . $output);
    }
    fwrite(STDOUT, 'HAYNE icon: ' . basename($output) . " READY\n");
}
