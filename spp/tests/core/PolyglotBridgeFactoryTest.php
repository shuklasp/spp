<?php

use SPP\Core\Polyglot\PolyglotBridgeFactory;
use SPP\Core\Polyglot\DefaultBridge;
use SPP\Core\Polyglot\JavaBridge;

class PolyglotBridgeFactoryTest extends \SPP\Parikshak\TestCase
{
    public function testGetBridge()
    {
        $javaBridge = PolyglotBridgeFactory::getBridge('java');
        $this->assertInstanceOf(JavaBridge::class, $javaBridge);

        $defaultBridge = PolyglotBridgeFactory::getBridge('python');
        $this->assertInstanceOf(DefaultBridge::class, $defaultBridge);
    }
}
