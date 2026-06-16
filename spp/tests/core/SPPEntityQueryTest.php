<?php
// Note: Requires full SPP framework boot to test DB and entities fully.
// This is a basic signature check test.

require_once dirname(__DIR__, 2) . '/modules/spp/sppentity/class.sppentityquery.php';

use SPPMod\SppDb\SppEntityQuery;

echo "Running SPPEntityQueryTest...\n";

class MockEntity extends \SPPMod\SppDb\SPPEntity {}

$query = new SppEntityQuery('MockEntity');
if (!method_exists($query, 'with')) {
    throw new \Exception("SPPEntityQueryTest: with() method missing.");
}

$query->with('comments');

echo "SPPEntityQueryTest Passed.\n";
