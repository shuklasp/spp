<?php
namespace SPP\Core\Interfaces;

/**
 * FrontendComponentInterface - Contract for SPP reactive frontend components.
 *
 * All LiveComponent implementations must adhere to this interface, ensuring
 * a consistent lifecycle for mount, hydrate, dehydrate, and render operations.
 */
interface FrontendComponentInterface
{
    /**
     * Render the component's view.
     * Return a file path (e.g. 'views/counter.html') or raw HTML string.
     *
     * @return string
     */
    public function render(): string;

    /**
     * Mount the component with initial parameters (called once on first render).
     *
     * @param array $params Key-value pairs to initialize public properties
     */
    public function mount(array $params = []): void;

    /**
     * Hydrate (restore) public properties from the incoming client state payload.
     *
     * @param array $state Key-value pairs from the client wire:state
     */
    public function hydrate(array $state): void;

    /**
     * Dehydrate (serialize) public properties into an array for the client.
     * Only public properties should be included — never leak protected/private data.
     *
     * @return array The serialized state
     */
    public function dehydrate(): array;
}
