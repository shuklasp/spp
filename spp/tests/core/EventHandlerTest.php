<?php
require_once dirname(__DIR__, 2) . '/core/class.eventmanager.php';

use SPP\Core\EventManager;

echo "Running EventHandlerTest...\n";

EventManager::defineEvent('test:event', function($data) {
    return "default";
}, true);

// Test 1: Default Handler
$res = \SPP\SPPEvent::triggerHook('test:event');
if ($res !== 'default') {
    throw new \Exception("EventHandlerTest: Default handler failed. Got: $res");
}

// Test 2: Override Handler
EventManager::listen('test:event', function($data) {
    return "overridden";
}, true);

$res = \SPP\SPPEvent::triggerHook('test:event');
if ($res !== 'overridden') {
    throw new \Exception("EventHandlerTest: Override handler failed. Got: $res");
}

echo "EventHandlerTest Passed.\n";
