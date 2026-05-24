<?php
require 'spp/sppinit.php';
require_once 'src/lekhak/events/PageRenderHookEventHandler.php';
$res = \SPP\SPPEvent::registerHandler('event_spp_view_render_theme', '\\EventHandlers\\PageRenderHookEventHandler', false, 'onPostTheme', 100);
var_dump($res);
$params = ['html' => '<html></html>', 'pageData' => []];
\SPP\SPPEvent::fireEvent('event_spp_view_render_theme', $params);
print_r($params);
