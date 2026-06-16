<?php

namespace SPP\Tests\Core;

class SPPEventTest
{
    public function testEventParamsAndExecution()
    {
        // Define an event
        \SPP\SPPEvent::defineEvent('test_event', null, true);
        
        // Add listeners
        \SPP\SPPEvent::listen('before_test_event', function($params) {
            $data = $params->getPayload();
            $data['before'] = true;
            $params->setPayload($data);
        });

        \SPP\SPPEvent::listen('test_event', function($params) {
            $data = $params->getPayload();
            $data['main'] = true;
            $params->setPayload($data);
        });

        \SPP\SPPEvent::listen('after_test_event', function($params) {
            $data = $params->getPayload();
            $data['after'] = true;
            $params->setPayload($data);
        });

        $payload = ['initial' => true];
        $evtParams = new \SPP\EventParams($payload);

        \SPP\SPPEvent::fireEvent('test_event', $evtParams);

        $result = $evtParams->getPayload();

        return isset($result['initial']) && isset($result['before']) && isset($result['main']) && isset($result['after']);
    }

    public function testStopPropagation()
    {
        \SPP\SPPEvent::listen('before_stop_event', function($params) {
            $data = $params->getPayload();
            $data['before'] = true;
            $params->setPayload($data);
            $params->stopPropagation();
        });

        \SPP\SPPEvent::listen('stop_event', function($params) {
            $data = $params->getPayload();
            $data['main'] = true;
            $params->setPayload($data);
        });

        $payload = ['initial' => true];
        $evtParams = new \SPP\EventParams($payload);

        \SPP\SPPEvent::fireEvent('stop_event', $evtParams);

        $result = $evtParams->getPayload();

        // Should have before, but NOT main, because propagation was stopped
        return isset($result['before']) && !isset($result['main']);
    }

    public function testInlineHandler()
    {
        \SPP\SPPEvent::listen('before_inline_event', function($params) {
            $data = $params->getPayload();
            $data['before'] = true;
            $params->setPayload($data);
        });

        $payload = ['initial' => true];
        $evtParams = new \SPP\EventParams($payload);

        // No instead hook is registered, so the inline handler should run
        \SPP\SPPEvent::fireEvent('inline_event', $evtParams, function($params) {
            $data = $params->getPayload();
            $data['inline'] = true;
            $params->setPayload($data);
        });

        $result = $evtParams->getPayload();

        return isset($result['before']) && isset($result['inline']);
    }

    public function testInsteadHandler()
    {
        \SPP\SPPEvent::listen('instead_override_event', function($params) {
            $data = $params->getPayload();
            $data['instead'] = true;
            $params->setPayload($data);
        });

        $payload = ['initial' => true];
        $evtParams = new \SPP\EventParams($payload);

        // Instead hook IS registered, so the inline handler should NOT run
        \SPP\SPPEvent::fireEvent('override_event', $evtParams, function($params) {
            $data = $params->getPayload();
            $data['inline'] = true;
            $params->setPayload($data);
        });

        $result = $evtParams->getPayload();

        return isset($result['instead']) && !isset($result['inline']);
    }
}
