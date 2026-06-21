<?php
namespace SPPMod\SPPLive;

/**
 * Handles multipart/form-data file uploads initiated by Live components.
 */
class UploadHandler {
    public static function handle(): array {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload failed or no file provided.'];
        }

        $uploadDir = SPP_BASE_DIR . '/var/tmp/spplive_uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $fileInfo = pathinfo($_FILES['file']['name']);
        $ext = isset($fileInfo['extension']) ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $fileInfo['extension']) : '';
        
        // Generate a secure token
        $token = bin2hex(random_bytes(16)) . $ext;
        $dest = $uploadDir . '/' . $token;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            // Live component state gets this token, component backend logic must handle it
            return [
                'success' => true,
                'token' => 'live_tmp://' . $token,
                'original_name' => $_FILES['file']['name']
            ];
        }

        return ['success' => false, 'message' => 'Failed to move uploaded file.'];
    }
}
