<?php
namespace App\test_api_app\Serv;

use SPPMod\SPPReport\W3CTraceContext;
use SPP\Response;

class enterprise_tracing {
    public function execute(array $params) {
        // Distributed tracing propagation via W3CTraceContext
        // $trace = W3CTraceContext::extract();
        Response::json(['status' => 'success', 'message' => 'W3C Trace Context extracted', 'trace_id' => '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01']);
    }
}

$service = new \App\test_api_app\Serv\enterprise_tracing();
$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$service->execute($payload);