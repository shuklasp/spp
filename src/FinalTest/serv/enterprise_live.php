<?php
namespace App\FinalTest\Serv;

use SPP\Response;

class enterprise_live {
    public function execute(array $params) {
        $action = $params['action'] ?? null;
        $component = new \App\FinalTest\Comp\live_demo();
        
        if ($action === 'increment') {
            $component->increment();
        } elseif ($action === 'decrement') {
            $component->decrement();
        }
        
        Response::html($component->render());
    }
}