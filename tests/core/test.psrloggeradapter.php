<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\TestCase;
use SPP\Core\PsrLoggerAdapter;
use Psr\Log\LogLevel;

class PsrLoggerAdapterTest extends TestCase
{
    private $adapter;
    
    public function setUp(): void
    {
        // PsrLoggerAdapter initializes without dependencies, relying on SPP_Logger statically internally
        $this->adapter = new PsrLoggerAdapter();
    }

    public function testInstantiation()
    {
        $this->assertInstanceOf('Psr\Log\LoggerInterface', $this->adapter, 'PsrLoggerAdapter should implement PSR-3 LoggerInterface');
    }
    
    public function testLoggingLevels()
    {
        // By calling these, we are asserting they don't throw fatal errors.
        // We aren't testing SPP_Logger's internal file writing here.
        try {
            $this->adapter->info('Unit test info log', ['context' => 'test']);
            $this->adapter->error('Unit test error log', ['context' => 'test']);
            $this->adapter->debug('Unit test debug log');
            $this->assertTrue(true); // If we get here without Exception, it passed
        } catch (\Throwable $e) {
            $this->assertTrue(false, 'Adapter threw exception on valid log level calls: ' . $e->getMessage());
        }
    }
    
    public function testInvalidLevelThrowsException()
    {
        $this->expectException('Psr\Log\InvalidArgumentException', function() {
            $this->adapter->log('non_existent_level', 'This should fail');
        });
    }
}
