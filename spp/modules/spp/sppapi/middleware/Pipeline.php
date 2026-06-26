<?php
namespace SPPMod\SPPAPI\Middleware;

class Pipeline
{
    private array $pipes = [];

    public function send($request): self
    {
        $this->passable = $request;
        return $this;
    }

    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    public function then(\Closure $destination)
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->passable);
    }

    private function prepareDestination(\Closure $destination): \Closure
    {
        return function ($passable) use ($destination) {
            return $destination($passable);
        };
    }

    private function carry(): \Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if (is_string($pipe) && class_exists($pipe)) {
                    $pipe = new $pipe();
                }

                if ($pipe instanceof \SPP\Core\MiddlewareInterface || method_exists($pipe, 'handle')) {
                    return $pipe->handle($passable, $stack);
                }

                return $stack($passable);
            };
        };
    }
}
