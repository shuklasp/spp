<?php
namespace App\test_api_app\Serv;

use SPP\Response;
use SPPMod\SPPView\LiveComponent;

class enterprise_live {
    public function execute(array $params) {
        $html = LiveComponent::renderComponent('\\App\\test_api_app\\Comp\\live_demo');
        Response::json([
            'status' => 'success',
            'html' => $html
        ]);
    }
}

$service = new \App\test_api_app\Serv\enterprise_live();
$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$service->execute($payload);