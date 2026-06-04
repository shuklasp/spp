<?php
require_once dirname(__DIR__, 2) . '/core/class.eventmanager.php';

use SPP\Core\EventManager;

echo "Running EventManagerTest...\n";

EventManager::defineEvent('test:event', function($data) {
    return "default";
}, true);

// Test 1: Default Handler
$res = EventManager::trigger('test:event');
if ($res !== 'default') {
    throw new \Exception("EventManagerTest: Default handler failed. Got: $res");
}

// Test 2: Override Handler
EventManager::listen('test:event', function($data) {
    return "overridden";
}, true);

$res = EventManager::trigger('test:event');
if ($res !== 'overridden') {
    throw new \Exception("EventManagerTest: Override handler failed. Got: $res");
}

echo "EventManagerTest Passed.\n";
