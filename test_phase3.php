<?php
require 'spp/sppinit.php';
\Lekhak\ModuleRegistry::invokeAll('request_init');

$_SESSION['uid'] = 1; // mock authenticated user
$_SESSION['uname'] = 'admin';

// 1. Test POST
stream_wrapper_unregister("php");
class MockPhpStream {
    private $position = 0;
    public static $data = '';
    public function stream_open($path, $mode, $options, &$opened_path) {
        $this->position = 0;
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
    public function stream_stat() {
        return [];
    }
}
stream_wrapper_register("php", "MockPhpStream");

echo "--- TEST POST ---\n";
MockPhpStream::$data = json_encode([
    'data' => [
        'type' => 'node--page',
        'attributes' => [
            'title' => 'Test Page POST',
            'body' => ['value' => 'This is a test POST body'],
            'status' => 1
        ]
    ]
]);

ob_start();
$_SERVER['REQUEST_METHOD'] = 'POST';
$controller = new \Lekhak\Modules\LekhakDrupalApi\Controller\NodeController();
$post_response = $controller->createNode('page');
echo $post_response . "\n";
ob_end_flush();

$created_node = json_decode($post_response, true);
$uuid = $created_node['data']['id'] ?? null;

if ($uuid) {
    echo "\n--- TEST PATCH ---\n";
    MockPhpStream::$data = json_encode([
        'data' => [
            'type' => 'node--page',
            'id' => $uuid,
            'attributes' => [
                'title' => 'Updated Page Title via PATCH'
            ]
        ]
    ]);
    $_SERVER['REQUEST_METHOD'] = 'PATCH';
    $patch_response = $controller->updateNode('page', $uuid);
    echo $patch_response . "\n";
    
    echo "\n--- TEST DELETE ---\n";
    $_SERVER['REQUEST_METHOD'] = 'DELETE';
    $delete_response = $controller->deleteNode('page', $uuid);
    echo "Delete Response: [" . $delete_response . "]\n";
} else {
    echo "POST Failed, no UUID returned.\n";
}
