<?php
require 'spp/sppinit.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/lekhak/user/login';
$_GET['_format'] = 'json';
// Hack to mock php://input by overriding the method and writing to a file, but since AuthController directly calls file_get_contents('php://input'), we can't do that simply.
// I will just instantiate the controller and mock the data inside.
\Lekhak\ModuleRegistry::invokeAll('request_init'); // registers autoloader

// Instead of router, just call it directly but we need to mock file_get_contents.
// Wait, I can just use a stream wrapper to mock php://input!

stream_wrapper_unregister("php");
class MockPhpStream {
    private $position;
    private $data = '{"name":"admin","pass":"admin_pass"}';
    public function stream_open($path, $mode, $options, &$opened_path) {
        $this->position = 0;
        return true;
    }
    public function stream_read($count) {
        $ret = substr($this->data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    public function stream_eof() {
        return $this->position >= strlen($this->data);
    }
    public function stream_stat() {
        return [];
    }
}
stream_wrapper_register("php", "MockPhpStream");

\Lekhak\Modules\LekhakDrupalApi\Router::handle('/user/login', 'POST');
