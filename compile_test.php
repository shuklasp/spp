<?php
require 'spp/sppinit.php';
$compiler = new \SPP\Core\ModuleCompiler('default');
$compiler->compile();
echo "Compiled successfully.\n";
