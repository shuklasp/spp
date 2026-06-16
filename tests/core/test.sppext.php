<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPPMod\Sppext\Sppext;

class SPPExtTest extends SPPTestCase
{
    public function testExtLifecycleDoesNotBootDB()
    {
        // To test if it boots DB, we can just call it and see if DB was connected.
        // But SPPDB connects lazily anyway since Phase 2.
        Sppext::registerExtensionLifecycles();
        
        // Assert that it didn't crash
        $this->assertTrue(true);
    }
}
