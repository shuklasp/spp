<?php

namespace App\School1\Events;

use SPP\EventHandler;

class MyHandler extends EventHandler 
{
    /**
     * Handle the event.
     * 
     * @param \SPP\EventParams $params The event payload and context.
     */
    public function __invoke(\SPP\EventParams $params) 
    {
        // Implement your event handler logic here
    }
}
