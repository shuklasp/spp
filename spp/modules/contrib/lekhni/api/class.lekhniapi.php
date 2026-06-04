<?php

namespace SPPMod\Lekhni\Api;

/**
 * Lekhni API Handler
 * Manages media uploads and specialized generic editor services out of contrib layer.
 */
class LekhniApi
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'pdf'];

    public static function handleRequest($action, $params)
    {
        switch ($action) {
            case 'get_settings':
                return self::getSettings();
            case 'upload_media':
                return self::uploadMedia();
            case 'list_media':
                return self::listMedia();
            case 'suggest_alias':
                return ['success' => true, 'alias' => self::slugify($params['title'] ?? '')];
            default:
                return ['success' => false, 'message' => "Unknown lekhni action: $action"];
        }
    }

    private static function getSettings()
    {
        $settings = [
            'default_mode' => 'document',
            'code_language' => 'html',
            'theme' => 'dark',
            'categories' => ['General', 'News', 'Tutorial', 'Engineering', 'Documentation']
        ];

        $etcConfig = __DIR__ . '/../etc/config.yml';
        $modConfig = __DIR__ . '/../module.yml';

        $targetFile = file_exists($etcConfig) ? $etcConfig : (file_exists($modConfig) ? $modConfig : null);
        if ($targetFile) {
            $parsed = [];
            if (class_exists('\Symfony\Component\Yaml\Yaml')) {
                $parsed = \Symfony\Component\Yaml\Yaml::parseFile($targetFile) ?: [];
            } elseif (function_exists('yaml_parse_file')) {
                $parsed = yaml_parse_file($targetFile) ?: [];
            }
            if (!empty($parsed['editor'])) {
                $settings = array_merge($settings, $parsed['editor']);
            } elseif (!empty($parsed['settings']['editor'])) {
                $settings = array_merge($settings, $parsed['settings']['editor']);
            }
        }
        return ['success' => true, 'settings' => $settings];
    }

    private static function uploadMedia()
    {
        if (empty($_FILES['file'])) {
            return ['success' => false, 'message' => 'No file uploaded.'];
        }

        $file = $_FILES['file'];

        if (!empty($file['error'])) {
            return ['success' => false, 'message' => self::uploadErrorMessage((int)$file['error'])];
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['success' => false, 'message' => 'File type not allowed.'];
        }

        $uploadDir = self::getUploadDir();
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['success' => false, 'message' => 'Upload directory could not be created.'];
        }

        $filename = self::uniqueFilename($file['name']);
        $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            return [
                'success' => true,
                'url' => self::publicUrl($target),
                'filename' => $filename,
                'name' => $file['name'],
                'type' => $file['type']
            ];
        }

        return ['success' => false, 'message' => 'Upload failed.'];
    }

    private static function listMedia()
    {
        $uploadDir = self::getUploadDir();
        if (!is_dir($uploadDir)) {
            return ['success' => true, 'files' => []];
        }

        $files = array_diff(scandir($uploadDir), ['.', '..']);

        $result = [];
        foreach ($files as $f) {
            $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $f;
            if (!is_file($fullPath)) {
                continue;
            }
            $result[] = [
                'name' => $f,
                'url' => self::publicUrl($fullPath),
                'size' => filesize($fullPath),
                'modified' => filemtime($fullPath)
            ];
        }

        return ['success' => true, 'files' => $result];
    }

    private static function getUploadDir()
    {
        $app = \SPP\App::getApp();
        $lekhniConfig = \SPP\App::getAppConf('lekhni') ?: [];
        $customPath = is_array($lekhniConfig) ? ($lekhniConfig['media_path'] ?? '') : '';

        if ($customPath) {
            if (str_starts_with($customPath, '/') || str_starts_with($customPath, '\\') || (strlen($customPath) > 1 && $customPath[1] === ':')) {
                return $customPath;
            }
            return $app->getAppSrcDir() . DIRECTORY_SEPARATOR . trim($customPath, '/\\');
        }

        return $app->getDataDir() . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'lekhni';
    }

    private static function uniqueFilename($name)
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $base);
        $base = trim($base, '.-_') ?: 'upload';
        $random = bin2hex(random_bytes(6));
        return time() . '_' . $random . '_' . $base . '.' . $ext;
    }

    private static function publicUrl($path)
    {
        $baseUrl = defined('APP_BASE_URI') ? APP_BASE_URI : '';
        $root = rtrim(str_replace('\\', '/', SPP_APP_DIR), '/');
        $normalized = str_replace('\\', '/', $path);
        $relPath = str_starts_with($normalized, $root)
            ? substr($normalized, strlen($root))
            : '/' . basename($normalized);
        return rtrim($baseUrl, '/') . '/' . ltrim($relPath, '/');
    }

    private static function uploadErrorMessage($code)
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file is too large.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file uploaded.',
            default => 'Upload failed.'
        };
    }

    private static function slugify($text)
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $converted = function_exists('iconv') ? @iconv('utf-8', 'us-ascii//TRANSLIT', $text) : false;
        if ($converted !== false) {
            $text = $converted;
        }
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a';
        }
        return $text;
    }
}
