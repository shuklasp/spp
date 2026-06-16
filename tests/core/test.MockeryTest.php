<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;

class MockeryTest extends SPPTestCase
{
    public function testMockeryCanMockObjects()
    {
        $mock = $this->mock(\stdClass::class);
        $mock->shouldReceive('someMethod')->once()->andReturn('mocked_value');
        
        $result = $mock->someMethod();
        $this->assertEquals('mocked_value', $result);
    }
}
