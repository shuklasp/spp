<?php

namespace SPPMod\SPPView;

class ViewRenderer
{
    /**
     * Renders a file using the appropriate engine based on extension.
     * Now overridable via event_spp_view_render.
     */
    public static function renderFile(string $filename, array $pageData): void
    {
        $params = ['filename' => $filename, 'pageData' => $pageData];
        $evtParams = new \SPP\EventParams($params);
        \SPP\SPPEvent::fireEvent('event_spp_view_render', $evtParams, 'DefaultViewRenderHandler');
    }
}
