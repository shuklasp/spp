<?php
namespace SPPMod\Parikshak;

/**
 * Trait InteractsWithMockery
 * Provides helpers to create mocks and automatically hook into Mockery's global teardown.
 */
trait InteractsWithMockery
{
    /**
     * Create a Mockery mock.
     *
     * @param string $class
     * @return \Mockery\MockInterface
     */
    protected function mock(string $class)
    {
        if (!class_exists('\Mockery')) {
            throw new \Exception("Mockery is not installed. Run `composer require --dev mockery/mockery`.");
        }
        return \Mockery::mock($class);
    }

    /**
     * Create a Mockery spy.
     *
     * @param string $class
     * @return \Mockery\MockInterface
     */
    protected function spy(string $class)
    {
        if (!class_exists('\Mockery')) {
            throw new \Exception("Mockery is not installed.");
        }
        return \Mockery::spy($class);
    }
}
