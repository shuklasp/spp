<?php

namespace SPPMod\SPPMedia;

/**
 * Class ImageStyle
 * High-performance, on-demand responsive image derivative and transformation engine for SPP.
 * Emulates Drupal Image Styles / Cloudinary transforms using native PHP GD / Imagick.
 */
class ImageStyle
{
    public const STYLES = [
        'thumbnail' => [
            'name' => 'Thumbnail',
            'width' => 150,
            'height' => 150,
            'action' => 'crop',
        ],
        'medium' => [
            'name' => 'Medium Article Embed',
            'width' => 600,
            'height' => 400,
            'action' => 'fit',
        ],
        'banner' => [
            'name' => 'Wide Header Banner',
            'width' => 1200,
            'height' => 500,
            'action' => 'crop',
        ],
        'avatar' => [
            'name' => 'User Profile Avatar',
            'width' => 96,
            'height' => 96,
            'action' => 'crop',
        ],
    ];

    /**
     * Get secret key for derivative HMAC signature verification.
     */
    protected static function getSecretKey(): string
    {
        if (defined('SPP_SECRET_KEY') && !empty(SPP_SECRET_KEY)) {
            return SPP_SECRET_KEY;
        }
        return 'spp_enterprise_image_secret_salt_2026';
    }

    /**
     * Generate security hash token for an image derivative request.
     */
    public static function generateToken(string $sourcePath, string $styleName): string
    {
        return substr(hash_hmac('sha256', $sourcePath . ':' . $styleName, self::getSecretKey()), 0, 16);
    }

    /**
     * Validate an incoming derivative security token.
     */
    public static function validateToken(string $sourcePath, string $styleName, string $token): bool
    {
        return hash_equals(self::generateToken($sourcePath, $styleName), $token);
    }

    /**
     * Generate public derivative URL for an image.
     */
    public static function url(string $sourcePath, string $styleName, ?string $app = null): string
    {
        $app = $app ?: (class_exists('\\SPP\\Scheduler') ? (\SPP\Scheduler::getContext() ?: 'default') : 'default');
        $baseUrl = class_exists('\\SPP\\App') ? \SPP\App::getBaseUrl($app) : '';
        $token = self::generateToken($sourcePath, $styleName);

        return $baseUrl . '/spp-media/style?style=' . urlencode($styleName) . '&file=' . urlencode($sourcePath) . '&token=' . $token;
    }

    /**
     * Resolve derivative cache file path on disk.
     */
    public static function getDerivativePath(string $sourcePath, string $styleName): string
    {
        $cleanPath = preg_replace('/[^a-zA-Z0-9_\-\.\/]/', '_', $sourcePath);
        $baseDir = defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 4);
        return $baseDir . '/public/derivatives/' . $styleName . '/' . ltrim($cleanPath, '/');
    }

    /**
     * Generate or serve cached derivative image.
     */
    public static function generateDerivative(string $sourceFile, string $styleName, ?string $outputFile = null): ?string
    {
        if (!file_exists($sourceFile)) {
            return null;
        }

        $outputFile = $outputFile ?: self::getDerivativePath($sourceFile, $styleName);
        if (file_exists($outputFile) && filemtime($outputFile) >= filemtime($sourceFile)) {
            return $outputFile;
        }

        $style = self::STYLES[$styleName] ?? null;
        if (!$style) {
            return null;
        }

        $dir = dirname($outputFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $info = @getimagesize($sourceFile);
        if (!$info) {
            return null;
        }

        $srcW = $info[0];
        $srcH = $info[1];
        $mime = $info['mime'];

        switch ($mime) {
            case 'image/jpeg': $srcImg = @imagecreatefromjpeg($sourceFile); break;
            case 'image/png':  $srcImg = @imagecreatefrompng($sourceFile); break;
            case 'image/gif':  $srcImg = @imagecreatefromgif($sourceFile); break;
            case 'image/webp': $srcImg = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourceFile) : null; break;
            default: return null;
        }

        if (!$srcImg) {
            return null;
        }

        $targetW = $style['width'] ?? 600;
        $targetH = $style['height'] ?? 400;
        $action = $style['action'] ?? 'fit';

        if ($action === 'crop') {
            $srcAspect = $srcW / $srcH;
            $targetAspect = $targetW / $targetH;

            if ($srcAspect > $targetAspect) {
                $cropH = $srcH;
                $cropW = (int)($srcH * $targetAspect);
                $cropX = (int)(($srcW - $cropW) / 2);
                $cropY = 0;
            } else {
                $cropW = $srcW;
                $cropH = (int)($srcW / $targetAspect);
                $cropX = 0;
                $cropY = (int)(($srcH - $cropH) / 2);
            }

            $dstImg = imagecreatetruecolor($targetW, $targetH);
            self::preserveTransparency($dstImg, $mime);
            imagecopyresampled($dstImg, $srcImg, 0, 0, $cropX, $cropY, $targetW, $targetH, $cropW, $cropH);
        } else {
            // 'fit' or 'scale': maintain aspect ratio
            $scale = min($targetW / $srcW, $targetH / $srcH);
            $newW = (int)round($srcW * $scale);
            $newH = (int)round($srcH * $scale);

            $dstImg = imagecreatetruecolor($newW, $newH);
            self::preserveTransparency($dstImg, $mime);
            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        }

        // Output image
        switch ($mime) {
            case 'image/png':
                imagepng($dstImg, $outputFile, 8);
                break;
            case 'image/gif':
                imagegif($dstImg, $outputFile);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    imagewebp($dstImg, $outputFile, 85);
                    break;
                }
                // Fallthrough to jpeg if webp not available
            default:
                imagejpeg($dstImg, $outputFile, 85);
                break;
        }

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return $outputFile;
    }

    private static function preserveTransparency($image, string $mime): void
    {
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
        }
    }
}
