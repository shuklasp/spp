<?php require 'spp/sppinit.php'; require 'src/lekhak/init.php'; $html = '<html><body>Hello</body></html>'; \Lekhak\ModuleRegistry::invokeAlter('page_render', $html); echo $html;
