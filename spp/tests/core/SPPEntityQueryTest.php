<?php
// Note: Requires full SPP framework boot to test DB and entities fully.
// This is a basic signature check test.

require_once dirname(__DIR__, 2) . '/modules/spp/sppentity/class.sppentityquery.php';

use SPPMod\SPPDB\SppEntityQuery;

echo "Running SPPEntityQueryTest...\n";

class MockEntity extends \SPPMod\SPPDB\SPPEntity
{
}

$query = new SppEntityQuery('MockEntity');
if (!method_exists($query, 'with')) {
    throw new \Exception("SPPEntityQueryTest: with() method missing.");
}

$query->with('comments');

echo "SPPEntityQueryTest Passed.\n";
