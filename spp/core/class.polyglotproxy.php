<?php

namespace SPP;

/**
 * Class PolyglotProxy
 * Base class for native PHP wrappers around Polyglot Bridge services.
 */
abstract class PolyglotProxy extends SPPObject
{
    /** @var string The target language runtime (e.g. 'python', 'node') */
    protected string $polyglotLang = '';

    /** @var string The target module name to execute */
    protected string $polyglotModule = '';

    /** @var bool Whether to run this service in a persistent background daemon (stateful/fast) */
    protected bool $daemon = false;

    /**
     * Intercept method calls and route them through the Polyglot Bridge synchronously.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws \Exception
     */
    public function __call(string $method, array $args)
    {
        return $this->callPolyglot($method, $args, $this->daemon);
    }

    protected function callPolyglot(string $func, array $args = [], bool $daemon = false)
    {
        $this->polyglotLang = $this->polyglotLang ?? \SPP\Module::getConfig('polyglot_lang');
        $this->polyglotModule = $this->polyglotModule ?? \SPP\Module::getConfig('polyglot_module');

        if (empty($this->polyglotLang) || empty($this->polyglotModule)) {
            throw new \Exception("PolyglotProxy: Language and Module must be defined in the proxy class.");
        }

        $result = \SPP\PolyglotBridge::call($this->polyglotLang, $this->polyglotModule, $func, $args, $daemon);

        if (!$result['success']) {
            throw new \Exception("PolyglotBridge Error [{$this->polyglotLang}]: " . ($result['error'] ?? 'Unknown Error'));
        }

        return $result['data'] ?? null;
    }

    /**
     * Dispatch the method call asynchronously (fire and forget).
     *
     * @param string $method
     * @param array $args
     * @return void
     */
    public function dispatchAsync(string $method, array $args = []): void
    {
        if (empty($this->polyglotLang) || empty($this->polyglotModule)) {
            throw new \Exception("PolyglotProxy: Language and Module must be defined in the proxy class.");
        }

        \SPP\PolyglotBridge::callAsync($this->polyglotLang, $this->polyglotModule, $method, $args, $this->daemon);
    }
}
