<?php
namespace SPPMod\SPPLive;

/**
 * Handles multipart/form-data file uploads initiated by Live components.
 */
class UploadHandler {
    public static function handle(): array {
        if (empty($_FILES)) {
            return ['success' => false, 'message' => 'Upload failed or no file provided.'];
        }

        $maxSize = defined('SPP_LIVE_MAX_UPLOAD_SIZE') ? SPP_LIVE_MAX_UPLOAD_SIZE : 10485760;
        $deniedMimes = ['application/x-php', 'application/x-httpd-php', 'text/x-php'];
        $deniedExts = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'phps', 'php7'];

        $uploadDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR . '/var/tmp/spplive_uploads' : '/tmp/spplive_uploads';
        $useS3 = defined('SPP_LIVE_UPLOAD_DISK') && SPP_LIVE_UPLOAD_DISK === 's3' && class_exists('\SPPMod\SPPStorage\S3Adapter');

        if (!$useS3 && !is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $tokens = [];

        foreach ($_FILES as $fileKey => $fileData) {
            $names = is_array($fileData['name']) ? $fileData['name'] : [$fileData['name']];
            $tmpNames = is_array($fileData['tmp_name']) ? $fileData['tmp_name'] : [$fileData['tmp_name']];
            $errors = is_array($fileData['error']) ? $fileData['error'] : [$fileData['error']];
            $sizes = is_array($fileData['size']) ? $fileData['size'] : [$fileData['size']];
            
            for ($i = 0; $i < count($names); $i++) {
                if ($errors[$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                if ($sizes[$i] > $maxSize) {
                    continue;
                }

                $mimeType = mime_content_type($tmpNames[$i]);
                if ($mimeType && in_array(strtolower((string)$mimeType), $deniedMimes)) {
                    continue;
                }

                $fileInfo = pathinfo($names[$i]);
                $extRaw = isset($fileInfo['extension']) ? strtolower($fileInfo['extension']) : '';
                if (in_array($extRaw, $deniedExts)) {
                    continue;
                }

                $ext = $extRaw ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $extRaw) : '';
                $tokenName = bin2hex(random_bytes(16)) . $ext;

                if ($useS3) {
                    $s3Path = 'spplive_uploads/' . $tokenName;
                    $s3 = new \SPPMod\SPPStorage\S3Adapter();
                    if ($s3->upload($tmpNames[$i], $s3Path)) {
                        $tokens[] = [
                            'token' => 's3://' . $s3Path,
                            'original_name' => $names[$i]
                        ];
                    }
                } else {
                    $dest = $uploadDir . '/' . $tokenName;
                    if (move_uploaded_file($tmpNames[$i], $dest)) {
                        $tokens[] = [
                            'token' => 'live_tmp://' . $tokenName,
                            'original_name' => $names[$i]
                        ];
                    }
                }
            }
        }

        if (empty($tokens)) {
            return ['success' => false, 'message' => 'Failed to move uploaded file(s) or validation failed.'];
        }

        return [
            'success' => true,
            'tokens' => $tokens
        ];
    }
}
