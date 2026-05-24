<?php
require 'spp/sppinit.php';
require 'src/lekhak/modules/sankhyaki/src/Controller/StatsController.php';

$controller = new \Lekhak\Modules\Sankhyaki\Controller\StatsController();
echo $controller->getStats();
