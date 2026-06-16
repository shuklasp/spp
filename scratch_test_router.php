<?php
namespace App\school1\Controllers {
    require_once 'c:\projects\apache\school1\spp.php';
    \SPP\App::init('school1');
    use SPP\Attributes\Route;

    class DummyTestController {
        #[Route(path: '/api/test/dummy', method: 'GET', auth: true)]
        public function handleDummy() {
            return "Dummy response";
        }
    }
}

namespace {
    $routes = \SPPMod\SPPView\AttributeRouter::getRoutes('school1');
    print_r($routes);
}
