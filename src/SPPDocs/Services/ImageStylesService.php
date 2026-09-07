<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;

class ImageStylesService extends \SPPMod\SPPMedia\ImageStyle
{
    /**
     * Default core image styles.
     */
    public static function getStyles(): array
    {
        return [
            'thumbnail' => [
                'name' => 'Thumbnail',
                'description' => 'Square thumbnail for cards, tables, and avatar lists (150x150 crop)',
                'width' => 150,
                'height' => 150,
                'mode' => 'crop',
            ],
            'medium' => [
                'name' => 'Medium Preview',
                'description' => 'Standard content preview scaled proportionally (600x400 fit)',
                'width' => 600,
                'height' => 400,
                'mode' => 'fit',
            ],
            'banner' => [
                'name' => 'Hero Banner',
                'description' => 'Landscape social sharing and hero header crop (1200x630 crop)',
                'width' => 1200,
                'height' => 630,
                'mode' => 'crop',
            ],
            'avatar' => [
                'name' => 'Avatar Icon',
                'description' => 'Compact circular/square profile icon (96x96 crop)',
                'width' => 96,
                'height' => 96,
                'mode' => 'crop',
            ],
        ];
    }

    /**
     * Get single style definition.
     */
    public static function getStyle(string $styleKey): ?array
    {
        $styles = self::getStyles();
        return $styles[$styleKey] ?? null;
    }

    /**
     * Resolve project media directory.
     */
    public static function resolveMediaDir(string $projectId): string
    {
        $configFile = 'C:/projects/apache/school1/etc/apps/SPPDocs/config.yml';
        $mediaDir = 'public/uploads';
        if (file_exists($configFile)) {
            $config = Yaml::parseFile($configFile);
            if (isset($config['projects'][$projectId]['media_dir'])) {
                $mediaDir = $config['projects'][$projectId]['media_dir'];
            }
        }

        $root = 'C:/projects/apache/school1';
        $cleanMediaDir = str_replace('\\', '/', $mediaDir);

        if (stripos($cleanMediaDir, $root . '/') === 0) {
            $cleanMediaDir = substr($cleanMediaDir, strlen($root) + 1);
        } elseif (preg_match('#^[a-zA-Z]:/#', $cleanMediaDir)) {
            return $cleanMediaDir;
        }

        return $root . '/' . ltrim($cleanMediaDir, '/');
    }

    /**
     * Get derivative storage path on disk.
     */
    public static function getDerivativePath(string $projectId, string $style, string $filename): string
    {
        $mediaDir = self::resolveMediaDir($projectId);
        $cleanFile = basename($filename);
        $cleanStyle = preg_replace('/[^a-z0-9_-]/i', '', $style);
        return $mediaDir . '/derivatives/' . $cleanStyle . '/' . $cleanFile;
    }

    /**
     * Get URL for the on-demand derivative endpoint.
     */
    public static function getDerivativeUrl(string $projectId, string $style, string $filename): string
    {
        $cleanFile = basename($filename);
        $cleanStyle = preg_replace('/[^a-z0-9_-]/i', '', $style);
        return \SPP\App::getBaseUrl() . '/media/style?project=' . urlencode($projectId) . '&style=' . urlencode($cleanStyle) . '&file=' . urlencode($cleanFile);
    }

    /**
     * Generate responsive image derivative using GD (with SVG and non-GD fallbacks).
     */
    public static function generateDerivative(string $projectId, string $style, string $filename): ?string
    {
        $styleDef = self::getStyle($style);
        if (!$styleDef) {
            return null;
        }

        $mediaDir = self::resolveMediaDir($projectId);
        $cleanFile = basename($filename);
        $origPath = $mediaDir . '/' . $cleanFile;

        if (!file_exists($origPath) || !is_file($origPath)) {
            return null;
        }

        $destPath = self::getDerivativePath($projectId, $style, $cleanFile);

        // Check if cached derivative exists and is fresher than original
        if (file_exists($destPath) && filemtime($destPath) >= filemtime($origPath)) {
            return $destPath;
        }

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $ext = strtolower(pathinfo($origPath, PATHINFO_EXTENSION));

        // SVG is vector: simply copy or return original path
        if ($ext === 'svg') {
            copy($origPath, $destPath);
            return $destPath;
        }

        // If GD extension is not loaded, fallback to original copy
        if (!extension_loaded('gd')) {
            copy($origPath, $destPath);
            return $destPath;
        }

        // Load image resource
        $src = null;
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $src = @imagecreatefromjpeg($origPath);
                break;
            case 'png':
                $src = @imagecreatefrompng($origPath);
                break;
            case 'webp':
                if (function_exists('imagecreatefromwebp')) {
                    $src = @imagecreatefromwebp($origPath);
                }
                break;
            case 'gif':
                $src = @imagecreatefromgif($origPath);
                break;
        }

        if (!$src) {
            // Unrecognized or corrupted image, copy original
            copy($origPath, $destPath);
            return $destPath;
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        if ($origW <= 0 || $origH <= 0) {
            imagedestroy($src);
            copy($origPath, $destPath);
            return $destPath;
        }

        $mode = $styleDef['mode'] ?? 'crop';
        $reqW = (int)$styleDef['width'];
        $reqH = (int)$styleDef['height'];

        if ($mode === 'crop') {
            // Center crop to exact dimensions
            $scale = max($reqW / $origW, $reqH / $origH);
            $cropW = (int)round($reqW / $scale);
            $cropH = (int)round($reqH / $scale);
            $srcX = max(0, (int)round(($origW - $cropW) / 2));
            $srcY = max(0, (int)round(($origH - $cropH) / 2));

            $dst = imagecreatetruecolor($reqW, $reqH);
            if ($ext === 'png' || $ext === 'webp' || $ext === 'gif') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagefilledrectangle($dst, 0, 0, $reqW, $reqH, $transparent);
            }

            imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $reqW, $reqH, $cropW, $cropH);
        } else {
            // Fit proportionally within bounding box
            $scale = min($reqW / $origW, $reqH / $origH);
            $dstW = max(1, (int)round($origW * $scale));
            $dstH = max(1, (int)round($origH * $scale));

            $dst = imagecreatetruecolor($dstW, $dstH);
            if ($ext === 'png' || $ext === 'webp' || $ext === 'gif') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $origW, $origH);
        }

        // Save derivative matching original extension
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($dst, $destPath, 88);
                break;
            case 'png':
                imagepng($dst, $destPath, 8);
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    imagewebp($dst, $destPath, 85);
                } else {
                    imagejpeg($dst, $destPath, 88);
                }
                break;
            case 'gif':
                imagegif($dst, $destPath);
                break;
            default:
                imagejpeg($dst, $destPath, 88);
                break;
        }

        imagedestroy($dst);
        imagedestroy($src);

        return $destPath;
    }
}