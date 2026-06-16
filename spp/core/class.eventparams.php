<?php

namespace SPP;

/**
 * Class EventParams
 * Generic container for passing event parameters by reference as an object.
 */
class EventParams
{
    /** @var array The dynamic payload parameters */
    protected array $payload = [];

    /** @var bool Flag to stop event propagation */
    protected bool $propagationStopped = false;

    /**
     * Constructor
     * @param mixed $payload Optional initial payload
     */
    public function __construct(mixed $payload = null)
    {
        if ($payload !== null) {
            $this->setPayload($payload);
        }
    }

    /**
     * Halts the event chain. No further handlers will be executed.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Checks if propagation has been stopped.
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Set the entire payload. If an array is passed, it sets the data.
     * If a non-array is passed, it stores it under the 'data' key.
     */
    public function setPayload(mixed $payload): void
    {
        if (is_array($payload)) {
            $this->payload = $payload;
        } else {
            $this->payload = ['data' => $payload];
        }
    }

    /**
     * Get the entire payload array, or a single 'data' item if that's all there is.
     */
    public function getPayload(): mixed
    {
        if (count($this->payload) === 1 && array_key_exists('data', $this->payload)) {
            return $this->payload['data'];
        }
        return $this->payload;
    }

    /**
     * Get a specific property from the payload.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    /**
     * Set a specific property in the payload.
     */
    public function set(string $key, mixed $value): void
    {
        $this->payload[$key] = $value;
    }

    /**
     * Check if a property exists in the payload.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->payload);
    }
}
