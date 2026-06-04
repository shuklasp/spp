<?php

namespace App\Events_test\Events;

use SPP\EventHandler;

class TestHandler extends EventHandler 
{
    /**
     * Handle the event.
     * 
     * @param mixed $params The parameters passed to the event dispatcher.
     */
    public function overrideHandler(mixed &$params = []) 
    {
        // Implement your event handler logic here
    }
}
