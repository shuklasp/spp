<?php
define('SPP_BASE_DIR', __DIR__ . '/../spp');
require_once __DIR__ . '/../spp/sppinit.php';

echo "SPPUser table: " . \SPPMod\SPPAuth\SPPUser::getMetadata('table', 'NULL') . "\n";
echo "SPPRole table: " . \SPPMod\SPPAuth\SPPRole::getMetadata('table', 'NULL') . "\n";
echo "SPPRight table: " . \SPPMod\SPPAuth\SPPRight::getMetadata('table', 'NULL') . "\n";
