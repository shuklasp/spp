<?php
require 'spp/sppinit.php';


$html = '</body>';
\Lekhak\ModuleRegistry::invokeAlter('page_render', $html);
echo "Output:\n" . $html . "\n";
