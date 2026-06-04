<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\TestCase;
use SPP\SPPConfig;

class SPPConfigTest extends TestCase
{
    public function testGetEnvValue()
    {
        $_ENV['TEST_VAR'] = 'hello_world';
        $this->assertEquals('hello_world', SPPConfig::get('env:TEST_VAR'), 'Should fetch value from ENV');
        
        $this->assertEquals('hello_world', SPPConfig::get('TEST_VAR'), 'Should fallback to ENV if not found elsewhere');
    }

    public function testDefaultValue()
    {
        $this->assertEquals('default_val', SPPConfig::get('env:NON_EXISTENT', 'default_val'), 'Should return default value if not found');
    }

    public function testSetAndGetAppCache()
    {
        // Because set() also affects the file system if 'app:' is used, we'll avoid writing to actual settings.yml 
        // to prevent test side-effects on a real codebase. We will just test validation schema memory.
        
        SPPConfig::registerSchema('test_schema', [
            'my_num' => ['type' => 'integer']
        ]);

        $exceptionCaught = false;
        try {
            SPPConfig::validate('my_num', 'not a number', 'test_schema');
        } catch (\SPP\SPPException $e) {
            $exceptionCaught = true;
        }

        $this->assertTrue($exceptionCaught, 'Validation should fail for non-integer');
        
        $exceptionCaught2 = false;
        try {
            SPPConfig::validate('my_num', 123, 'test_schema');
        } catch (\SPP\SPPException $e) {
            $exceptionCaught2 = true;
        }

        $this->assertFalse($exceptionCaught2, 'Validation should pass for integer');
    }
}
