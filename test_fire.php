<?php
require 'spp/sppinit.php';
require 'src/lekhak/init.php';
$params = ['html' => '<html></html>', 'pageData' => []];
\SPP\SPPEvent::fireEvent('event_spp_view_render_theme', $params);
print_r($params);
