<?php
declare(strict_types=1);

namespace SPPMod\SPPRouter;

require_once __DIR__ . '/class.spprouter.php';
require_once __DIR__ . '/src/ManifestRouter.php';

if (!class_exists('SPPMod\SPPView\Pages', false)) {
    class_alias(\SPPMod\SPPRouter\SPPRouter::class, 'SPPMod\SPPView\Pages');
}
