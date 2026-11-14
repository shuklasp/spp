<?php
namespace SPPMod\Parikshak;

/**
 * Trait InteractsWithMockery
 * Provides helpers to create mocks and automatically hook into Mockery's global teardown.
 */
trait InteractsWithMockery
{
    /** @var NativeMock[] */
    protected array $activeNativeMocks = [];

    /**
     * Create a Mock (using Mockery if available, otherwise native sovereign mock).
     *
     * @param string $class
     * @return \Mockery\MockInterface|NativeMock
     */
    protected function mock(string $class = 'stdClass')
    {
        if (class_exists('\Mockery')) {
            return \Mockery::mock($class);
        }
        $mock = new NativeMock($class);
        $this->activeNativeMocks[] = $mock;
        return $mock;
    }

    /**
     * Create a Spy (using Mockery if available, otherwise native sovereign mock).
     *
     * @param string $class
     * @return \Mockery\MockInterface|NativeMock
     */
    protected function spy(string $class = 'stdClass')
    {
        if (class_exists('\Mockery')) {
            return \Mockery::spy($class);
        }
        $spy = new NativeMock($class);
        $this->activeNativeMocks[] = $spy;
        return $spy;
    }

    /**
     * PHPUnit-style createMock helper.
     */
    protected function createMock(string $class): object
    {
        return $this->mock($class);
    }

    /**
     * PHPUnit-style createStub helper.
     */
    protected function createStub(string $class): object
    {
        return $this->mock($class);
    }

    /**
     * Verify all native mocks during test tearDown.
     */
    protected function verifyNativeMocks(): void
    {
        foreach ($this->activeNativeMocks as $mock) {
            $mock->verify();
        }
        $this->activeNativeMocks = [];
    }
}
