<?php

namespace SPP;

if (!class_exists('\\SPP\\Event\\EventRecord', false)) {
    $recFile = __DIR__ . '/class.eventrecord.php';
    if (file_exists($recFile)) {
        require_once $recFile;
    }
}
if (!class_exists('\\SPP\\Exceptions\\EventAbortedException', false)) {
    $exFile = __DIR__ . '/exceptions/EventAbortedException.php';
    if (file_exists($exFile)) {
        require_once $exFile;
    }
}

/**
 * Class EventParams
 * Generic container for passing event parameters by reference as an object.
 * Enhanced with enterprise :OLD and :NEW pseudorecords, dirty checking, mutations, and domain aborts.
 */
class EventParams implements \ArrayAccess
{
    /** @var array The dynamic payload parameters */
    protected array $payload = [];

    /** @var bool Flag to stop event propagation */
    protected bool $propagationStopped = false;

    /** @var array|null Pristine pre-change attribute values (:OLD) */
    protected ?array $oldRecord = null;

    /** @var array|null Pending post-change attribute values (:NEW) */
    protected ?array $newRecord = null;

    /** @var bool Flag indicating whether the event was explicitly aborted */
    protected bool $aborted = false;

    /** @var string|null Abort reason message */
    protected ?string $abortMessage = null;

    /** @var int Abort status/application error code */
    protected int $abortCode = 0;

    /**
     * Constructor
     * @param mixed $payload Optional initial payload
     * @param array|null $old Optional explicit :OLD record state
     * @param array|null $new Optional explicit :NEW record state
     */
    public function __construct(mixed $payload = null, ?array $old = null, ?array $new = null)
    {
        if ($payload !== null) {
            $this->setPayload($payload);
        }

        if ($old !== null) {
            $this->oldRecord = $old;
        } elseif (is_array($this->payload) && isset($this->payload['old']) && is_array($this->payload['old'])) {
            $this->oldRecord = $this->payload['old'];
        }

        if ($new !== null) {
            $this->newRecord = $new;
        } elseif (is_array($this->payload) && isset($this->payload['new']) && is_array($this->payload['new'])) {
            $this->newRecord = $this->payload['new'];
        }

        // Auto-extract :OLD and :NEW from attached SPPEntity if present
        if (isset($this->payload['entity']) && is_object($this->payload['entity'])) {
            $ent = $this->payload['entity'];
            if ($this->oldRecord === null && method_exists($ent, 'getSnapshot')) {
                $this->oldRecord = $ent->getSnapshot();
            }
            if ($this->newRecord === null && method_exists($ent, 'getValues')) {
                $this->newRecord = $ent->getValues();
            }
        }
    }

    /**
     * Access the pristine :OLD record attribute(s).
     */
    public function old(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->oldRecord ?? [];
        }
        return $this->oldRecord[$key] ?? $default;
    }

    /**
     * Access the pending :NEW record attribute(s).
     */
    public function new(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->newRecord ?? [];
        }
        return $this->newRecord[$key] ?? $default;
    }

    /**
     * Mutates an attribute in the pending :NEW state and syncs to attached entity.
     */
    public function setNew(string $key, mixed $value): self
    {
        if ($this->newRecord === null) {
            $this->newRecord = [];
        }
        $this->newRecord[$key] = $value;
        if (isset($this->payload['entity']) && is_object($this->payload['entity']) && method_exists($this->payload['entity'], 'set')) {
            $this->payload['entity']->set($key, $value);
        }
        return $this;
    }

    /**
     * Alias for old() matching standard ORM conventions.
     */
    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        return $this->old($key, $default);
    }

    /**
     * Determines if an attribute has changed between :OLD and :NEW.
     * If no attribute is specified, returns true if ANY attribute changed.
     */
    public function isDirty(?string $field = null): bool
    {
        if ($field === null) {
            return ($this->oldRecord ?? []) != ($this->newRecord ?? []);
        }
        return ($this->oldRecord[$field] ?? null) !== ($this->newRecord[$field] ?? null);
    }

    /**
     * Returns an associative array of all fields that changed with their old and new values.
     */
    public function getChanges(): array
    {
        $changes = [];
        $old = $this->oldRecord ?? [];
        $new = $this->newRecord ?? [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        foreach ($keys as $k) {
            $oVal = $old[$k] ?? null;
            $nVal = $new[$k] ?? null;
            if ($oVal !== $nVal) {
                $changes[$k] = ['old' => $oVal, 'new' => $nVal];
            }
        }
        return $changes;
    }

    /**
     * Explicitly aborts the event chain with an application business exception.
     * Stops propagation and optionally throws an EventAbortedException.
     *
     * @param string $message Business error explanation
     * @param int $code Application error code (default 400)
     * @param bool $throw Whether to immediately throw EventAbortedException
     * @throws \SPP\Exceptions\EventAbortedException
     */
    public function abort(string $message = 'Event execution was aborted.', int $code = 400, bool $throw = true): void
    {
        $this->aborted = true;
        $this->abortMessage = $message;
        $this->abortCode = $code;
        $this->stopPropagation();

        if ($throw) {
            throw new \SPP\Exceptions\EventAbortedException($message, $code);
        }
    }

    public function isAborted(): bool
    {
        return $this->aborted;
    }

    public function getAbortMessage(): ?string
    {
        return $this->abortMessage;
    }

    public function getAbortCode(): int
    {
        return $this->abortCode;
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
        if (isset($this->payload['entity']) && is_object($this->payload['entity']) && method_exists($this->payload['entity'], 'set')) {
            $this->payload['entity']->set($key, $value);
        }
        if ($this->newRecord !== null) {
            $this->newRecord[$key] = $value;
        }
    }

    /**
     * Check if a property exists in the payload.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->payload);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string)$offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string)$offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string)$offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->payload[$offset]);
    }

    public function __get(string $name): mixed
    {
        if ($name === 'old') {
            return new \SPP\Event\EventRecord($this->oldRecord ?? []);
        }
        if ($name === 'new') {
            return new \SPP\Event\EventRecord($this->newRecord ?? [], function(string $key, mixed $value) {
                if ($this->newRecord === null) {
                    $this->newRecord = [];
                }
                $this->newRecord[$key] = $value;
                if (isset($this->payload['entity']) && is_object($this->payload['entity']) && method_exists($this->payload['entity'], 'set')) {
                    $this->payload['entity']->set($key, $value);
                }
            });
        }
        if ($this->has($name)) {
            return $this->get($name);
        }
        if (isset($this->payload['entity']) && is_object($this->payload['entity'])) {
            return $this->payload['entity']->$name ?? null;
        }
        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    public function __isset(string $name): bool
    {
        if ($name === 'old') {
            return !empty($this->oldRecord);
        }
        if ($name === 'new') {
            return !empty($this->newRecord);
        }
        if ($this->has($name)) {
            return true;
        }
        if (isset($this->payload['entity']) && is_object($this->payload['entity'])) {
            return isset($this->payload['entity']->$name);
        }
        return false;
    }

    public function __call(string $method, array $args): mixed
    {
        if (isset($this->payload['entity']) && is_object($this->payload['entity']) && method_exists($this->payload['entity'], $method)) {
            return $this->payload['entity']->$method(...$args);
        }
        throw new \BadMethodCallException("Method {$method} does not exist on " . static::class);
    }
}
