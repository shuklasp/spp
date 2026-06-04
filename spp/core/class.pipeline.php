<?php

namespace SPP\Core;

/**
 * class Pipeline
 *
 * Orchestrates the execution of a stack of middlewares.
 */
class Pipeline
{
    private array $pipes = [];
    private $passable;

    /**
     * Set the object being sent through the pipeline.
     */
    public function send($passable): self
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * Set the stack of pipes.
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Run the pipeline with a final destination callback.
     */
    public function then(callable $destination)
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->passable);
    }

    /**
     * Get the closure that represents one layer of the onion.
     */
    private function carry(): \Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if (is_string($pipe)) {
                    $pipe = \SPP\Registry::make($pipe);
                }

                if ($pipe instanceof MiddlewareInterface) {
                    return $pipe->handle($passable, $stack);
                }

                return $pipe($passable, $stack);
            };
        };
    }

    /**
     * Prepare the final destination callback.
     */
    private function prepareDestination(callable $destination): \Closure
    {
        return function ($passable) use ($destination) {
            return $destination($passable);
        };
    }
}
