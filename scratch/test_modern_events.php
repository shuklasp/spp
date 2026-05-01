<?php

require_once 'spp/sppinit.php';

use SPP\SPPEvent;
use SPP\SPPEventObject;
use SPP\EventHandler;

echo "SPP Modern Event System Test\n";
echo "============================\n\n";

// 1. Define a Modern Event
class UserProfileUpdatedEvent extends SPPEventObject {
    public string $username;
    public string $email;
    public bool $auditLogged = false;

    public function __construct(string $username, string $email) {
        $username = $username;
        $this->username = $username;
        $this->email = $email;
    }
}

// 2. Define a Handler for this event
class UserProfileHandler extends EventHandler {
    protected function initHandler() {
        $this->addBeforeHandler('checkPermission');
        $this->addAfterHandler('logActivity');
    }

    public function checkPermission($event) {
        if ($event instanceof SPPEventObject) {
            echo "  [Before] Checking permissions for event: " . $event->getName() . "\n";
            echo "  [Before] User: " . $event->username . "\n";
        } else {
            echo "  [Before] Legacy mode parameters received.\n";
        }
    }

    public function logActivity($event) {
        if ($event instanceof SPPEventObject) {
            echo "  [After] Logging activity for user: " . $event->username . "\n";
            $event->auditLogged = true;
        }
    }
}

// 3. Register the handler
SPPEvent::registerHandler('user_profile_updated', UserProfileHandler::class);

// 4. Dispatch the event
echo "Dispatching modern UserProfileUpdatedEvent...\n";
$event = new UserProfileUpdatedEvent("satya", "satya@example.com");
SPPEvent::dispatch($event);

echo "\nPost-dispatch checks:\n";
echo "  Event Name: " . $event->getName() . " (Expected: user_profile_updated)\n";
echo "  Audit Logged: " . ($event->auditLogged ? "YES" : "NO") . " (Expected: YES)\n";

// 5. Test Propagation Stop
class StopEvent extends SPPEventObject {}
class StopperHandler extends EventHandler {
    public function beforeHandler(mixed &$params = []) {
        echo "  [Stopper] Stopping propagation...\n";
        $params->stopPropagation();
    }
}
class NeverReachedHandler extends EventHandler {
    public function beforeHandler(mixed &$params = []) {
        echo "  [FAIL] This should not be reached!\n";
    }
}

SPPEvent::registerHandler('stop', StopperHandler::class, false, null, 1000);
SPPEvent::registerHandler('stop', NeverReachedHandler::class, false, null, 100);

echo "\nTesting propagation stop...\n";
$stopEvent = new StopEvent();
SPPEvent::dispatch($stopEvent);

echo "\nTests Completed.\n";
