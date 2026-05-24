<?php
require 'spp/sppinit.php';
require_once 'src/lekhak/modules/lekhak_drupal_api/src/Router.php';
require_once 'src/lekhak/modules/lekhak_drupal_api/src/Controller/GenericEntityController.php';
require_once 'src/lekhak/modules/lekhak_drupal_api/src/Controller/FileController.php';

use Lekhak\Modules\LekhakDrupalApi\Router;

// Fake a logged-in user
$_SESSION['uid'] = 1;

class MockPhpStream4 {
    public $context;
    private static $data = '';
    private $position = 0;

    public static function setString($string) {
        self::$data = $string;
    }

    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }

    public function stream_read($count) {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof() {
        return $this->position >= strlen(self::$data);
    }
}
stream_wrapper_unregister('php');
stream_wrapper_register('php', 'MockPhpStream4');

// TEST 1: Generic Entity (Media)
echo "--- TEST MEDIA CREATION ---\n";
$_SERVER['REQUEST_URI'] = '/jsonapi/media/image';
$mediaData = json_encode([
    'data' => [
        'type' => 'media--image',
        'attributes' => [
            'name' => 'My Uploaded Image',
            'status' => true
        ]
    ]
]);
MockPhpStream4::setString($mediaData);
ob_start();
Router::handle('/jsonapi/media/image', 'POST');
$mediaOut = ob_get_clean();
echo $mediaOut . "\n\n";

$mediaParsed = json_decode($mediaOut, true);
$mediaUuid = $mediaParsed['data']['id'] ?? null;

if ($mediaUuid) {
    echo "--- TEST MEDIA PATCH ---\n";
    $_SERVER['REQUEST_URI'] = "/jsonapi/media/image/{$mediaUuid}";
    $mediaPatchData = json_encode([
        'data' => [
            'type' => 'media--image',
            'attributes' => [
                'name' => 'Renamed Image'
            ]
        ]
    ]);
    MockPhpStream4::setString($mediaPatchData);
    ob_start();
    Router::handle("/jsonapi/media/image/{$mediaUuid}", 'PATCH');
    echo ob_get_clean() . "\n\n";

    echo "--- TEST MEDIA DELETE ---\n";
    ob_start();
    Router::handle("/jsonapi/media/image/{$mediaUuid}", 'DELETE');
    $delOut = ob_get_clean();
    echo "Delete Response: " . ($delOut === '' ? 'Empty (Success 204)' : $delOut) . "\n\n";
}

// TEST 2: File Upload (JSON Metadata fallback)
echo "--- TEST FILE METADATA CREATION ---\n";
$_SERVER['REQUEST_URI'] = '/jsonapi/file/file';
$fileData = json_encode([
    'data' => [
        'type' => 'file--file',
        'attributes' => [
            'filename' => 'testfile.pdf',
            'uri' => ['value' => 'public://testfile.pdf'],
            'filemime' => 'application/pdf',
            'filesize' => 1024
        ]
    ]
]);
MockPhpStream4::setString($fileData);
ob_start();
Router::handle('/jsonapi/file/file', 'POST');
$fileOut = ob_get_clean();
echo $fileOut . "\n\n";

$fileParsed = json_decode($fileOut, true);
$fileUuid = $fileParsed['data']['id'] ?? null;
if ($fileUuid) {
    echo "--- TEST FILE DELETE ---\n";
    ob_start();
    Router::handle("/jsonapi/file/file/{$fileUuid}", 'DELETE');
    $fDelOut = ob_get_clean();
    echo "File Delete Response: " . ($fDelOut === '' ? 'Empty (Success 204)' : $fDelOut) . "\n\n";
}

// TEST 3: File Upload (Raw Binary)
echo "--- TEST FILE BINARY CREATION ---\n";
$_SERVER['REQUEST_URI'] = '/jsonapi/file/file';
$_SERVER['HTTP_CONTENT_DISPOSITION'] = 'file; filename="binarytest.txt"';
MockPhpStream4::setString("This is fake binary content.");
ob_start();
Router::handle('/jsonapi/file/file', 'POST');
$binOut = ob_get_clean();
echo $binOut . "\n\n";

$binParsed = json_decode($binOut, true);
$binUuid = $binParsed['data']['id'] ?? null;
if ($binUuid) {
    echo "--- TEST BINARY DELETE ---\n";
    ob_start();
    Router::handle("/jsonapi/file/file/{$binUuid}", 'DELETE');
    $bDelOut = ob_get_clean();
    echo "Binary Delete Response: " . ($bDelOut === '' ? 'Empty (Success 204)' : $bDelOut) . "\n\n";
}

echo "All tests finished.\n";
