<?php

namespace App\School1\Events;

use SPP\EventHandler;

class MyHandler extends EventHandler 
{
    /**
     * Handle the event.
     * 
     * @param \SPP\EventParams|mixed $params The event payload and context.
     */
    public function __invoke(mixed $params = []) 
    {
        // Implement your event handler logic here
    }
}
