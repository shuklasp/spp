<?php
namespace SPPMod\SPPPjax;

use SPP\Module;

class SPPPjax extends Module
{
    public function __construct()
    {
        parent::__construct();
        
        // Register event handler to inject the PJAX engine script
        \SPP\SPPEvent::attachEvent('event_spp_view_before_augment', [$this, 'injectScript']);
    }

    /**
     * Injects the SPA engine into the page.
     */
    public function injectScript(&$params)
    {
        if (isset($params['js_list'])) {
            $params['js_list'][] = '/school1/spp/modules/spp/spppjax/js/spp.pjax.js';
        }
    }
}
