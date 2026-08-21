<?php
namespace SPPMod\SPPView\Tests;

use PHPUnit\Framework\TestCase;
use SPPMod\SPPView\LiveComponent;

/**
 * Base Test Case for SPP LiveComponents.
 * Provides helper methods to simulate the LiveComponent lifecycle.
 */
abstract class LiveComponentTestCase extends TestCase
{
    /**
     * Helper to test a LiveComponent.
     *
     * @param string $componentClass The fully qualified class name of the component
     * @param array $initialParams The parameters passed to mount()
     * @return TestableLiveComponent
     */
    protected function live(string $componentClass, array $initialParams = []): TestableLiveComponent
    {
        return new TestableLiveComponent($componentClass, $initialParams);
    }
}

/**
 * Wrapper for testing LiveComponents fluent API.
 */
class TestableLiveComponent
{
    protected LiveComponent $instance;
    protected string $html;

    public function __construct(string $componentClass, array $initialParams = [])
    {
        $this->instance = new $componentClass();
        // Simulate initial render
        $this->instance->mount($initialParams);
        $this->instance->boot();
        $this->instance->booted();
        $this->instance->rendering();
        $this->html = $this->instance->render();
        $this->instance->rendered();
    }

    /**
     * Simulate setting a property via wire:model.
     */
    public function set(string $property, $value): self
    {
        $this->instance->updating($property, $value);
        $this->instance->$property = $value;
        $this->instance->updated($property, $value);
        return $this;
    }

    /**
     * Simulate calling a method via wire:click etc.
     */
    public function call(string $method, ...$params): self
    {
        $this->instance->boot();
        $this->instance->booted();
        $this->instance->hydrate($this->instance->dehydrate());
        $this->instance->$method(...$params);
        $this->instance->rendering();
        $this->html = $this->instance->render();
        $this->instance->rendered();
        return $this;
    }

    /**
     * Assert that the component's rendered HTML contains a string.
     */
    public function assertSee(string $value): self
    {
        TestCase::assertStringContainsString($value, $this->html);
        return $this;
    }
    
    /**
     * Assert that the component's rendered HTML does not contain a string.
     */
    public function assertDontSee(string $value): self
    {
        TestCase::assertStringNotContainsString($value, $this->html);
        return $this;
    }

    /**
     * Assert that a property has a specific value.
     */
    public function assertSet(string $property, $value): self
    {
        TestCase::assertEquals($value, $this->instance->$property);
        return $this;
    }
    
    /**
     * Assert that the component has validation errors.
     */
    public function assertHasErrors(array $keys = []): self
    {
        TestCase::assertNotEmpty($this->instance->getErrorBag());
        if (!empty($keys)) {
            foreach ($keys as $key) {
                TestCase::assertArrayHasKey($key, $this->instance->getErrorBag());
            }
        }
        return $this;
    }
}
