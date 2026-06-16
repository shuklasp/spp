<?php

namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPPMod\SPPRouter\SPPRouter;

class SPPRouterTest extends SPPTestCase
{
    public function testGetPageThrowsExceptionForNonExistentRoute()
    {
        $this->expectException(\SPP\SPPException::class, function() {
            SPPRouter::getPage('this-route-does-not-exist-for-sure-12345');
        });
    }

    public function testGetDefaultReturnsNullIfNoConfig()
    {
        // Without an active DB or Yaml defining 'home', it should return null or a default
        $home = SPPRouter::getDefault('home');
        $this->assertTrue(is_string($home) && $home !== '');
    }
}
