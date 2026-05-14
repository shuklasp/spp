<?php
namespace SPPMod\Lekhni\Api;

/**
 * Lekhni API Handler
 * Manages media uploads and specialized generic editor services out of contrib layer.
 */
class LekhniApi {
    public static function handleRequest($action, $params) {
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

    private static function getSettings() {
        $settings = [
            'default_mode' => 'document',
            'code_language' => 'html',
            'theme' => 'dark',
            'categories' => ['General', 'News', 'Tutorial', 'Engineering', 'Documentation']
        ];
        
        $etcConfig = __DIR__ . '/../etc/config.yml';
        $modConfig = __DIR__ . '/../module.yml';
        
        $targetFile = file_exists($etcConfig) ? $etcConfig : (file_exists($modConfig) ? $modConfig : null);
        if ($targetFile && function_exists('yaml_parse_file')) {
            $parsed = yaml_parse_file($targetFile);
            if (!empty($parsed['editor'])) {
                $settings = array_merge($settings, $parsed['editor']);
            } elseif (!empty($parsed['settings']['editor'])) {
                $settings = array_merge($settings, $parsed['settings']['editor']);
            }
        }
        return ['success' => true, 'settings' => $settings];
    }

    private static function uploadMedia() {
        if (empty($_FILES['file'])) {
            return ['success' => false, 'message' => 'No file uploaded.'];
        }

        $file = $_FILES['file'];
        
        // Resolve Upload Directory from Active App's Data Dir (var/)
        $dataDir = \SPP\App::getApp()->getDataDir();
        $customPath = \SPP\App::getGlobalSettings('lekhni.media_path');
        
        if ($customPath) {
            // If custom path starts with /, assume absolute, otherwise relative to App Src Root
            if (str_starts_with($customPath, '/') || str_starts_with($customPath, '\\') || (strlen($customPath) > 1 && $customPath[1] === ':')) {
                $uploadDir = $customPath;
            } else {
                $uploadDir = \SPP\App::getApp()->getAppSrcDir() . DIRECTORY_SEPARATOR . $customPath;
            }
        } else {
            $uploadDir = $dataDir . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'lekhni';
        }
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $filename = time() . '_' . basename($file['name']);
        $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $baseUrl = defined('APP_BASE_URI') ? APP_BASE_URI : '';
            
            // Resolve public URL: Need to make sure it's accessible. 
            // In self-contained mode, var/ might be inside src/lekhak.
            // We need a path relative to project root for the browser.
            $relPath = substr($target, strlen(SPP_APP_DIR));
            $publicUrl = rtrim($baseUrl, '/') . '/' . ltrim(str_replace('\\', '/', $relPath), '/');

            return [
                'success' => true,
                'url' => $publicUrl,
                'name' => $file['name'],
                'type' => $file['type']
            ];
        }

        return ['success' => false, 'message' => 'Upload failed.'];
    }

    private static function listMedia() {
        $dataDir = \SPP\App::getApp()->getDataDir();
        $uploadDir = $dataDir . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'lekhni';
        if (!is_dir($uploadDir)) return ['success' => true, 'files' => []];

        $files = array_diff(scandir($uploadDir), ['.', '..']);
        $baseUrl = defined('APP_BASE_URI') ? APP_BASE_URI : '';
        
        $result = [];
        foreach ($files as $f) {
            $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $f;
            $relPath = substr($fullPath, strlen(SPP_APP_DIR));
            $result[] = [
                'name' => $f,
                'url' => rtrim($baseUrl, '/') . '/' . ltrim(str_replace('\\', '/', $relPath), '/')
            ];
        }

        return ['success' => true, 'files' => $result];
    }

    private static function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) return 'n-a';
        return $text;
    }
}
